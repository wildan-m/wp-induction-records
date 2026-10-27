<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use ForgeBench\InductionRecords\Induction\ValidityEvaluator;
use RuntimeException;
use Throwable;
use WP_Post;
use wpdb;

/**
 * Publishes a briefing's saved title and content as a new immutable version,
 * and flags inductions that a new major version invalidates.
 */
final class VersionPublisher {

	public function __construct(
		private readonly wpdb $db,
		private readonly VersionRepository $versions,
		private readonly SignOffRepository $signoffs,
		private readonly VersionPolicy $policy,
		private readonly BodyNormaliser $normaliser,
		private readonly ValidityEvaluator $evaluator
	) {
	}

	/**
	 * @throws VersionPolicyException When the request breaks a versioning rule.
	 * @throws RuntimeException       When the briefing does not exist or the database write fails.
	 */
	public function publish( int $briefing_id, BumpType $bump, string $change_note, int $user_id ): PublishOutcome {
		$post = get_post( $briefing_id );
		if ( ! $post instanceof WP_Post || BriefingPostType::POST_TYPE !== $post->post_type ) {
			throw new RuntimeException( 'Unknown machine briefing.' );
		}

		$note = $this->policy->change_note( $change_note );

		list( $title, $body ) = $this->snapshot( $post );

		if ( '' === $title || '' === trim( wp_strip_all_tags( $body ) ) ) {
			throw VersionPolicyException::empty_content();
		}

		$checksum = $this->normaliser->checksum( $body );
		$current  = $this->versions->latest_for_briefing( $briefing_id );
		$changed  = null === $current || $current->body_checksum !== $checksum || $current->title !== $title;
		$number   = $this->policy->next_version( $current?->version, $bump, $changed );

		$version = new BriefingVersion(
			0,
			$briefing_id,
			$number,
			$title,
			$body,
			$checksum,
			$note,
			new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ),
			$user_id
		);

		// Storing the version and flagging old inductions succeed or fail together.
		$this->db->query( 'START TRANSACTION' );
		try {
			$version = $this->versions->insert( $version );
			$flagged = $this->flag_outdated_inductions( $briefing_id, $version->version );
			$this->db->query( 'COMMIT' );
		} catch ( Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw $error;
		}

		/**
		 * Fires after a briefing version was published.
		 *
		 * @param BriefingVersion $version Stored version.
		 * @param list<int>       $flagged Ids of sign-offs that now need re-induction.
		 */
		do_action( 'induction_records_version_published', $version, $flagged );

		return new PublishOutcome( $version, count( $flagged ) );
	}

	/**
	 * Whether the saved briefing differs from its current published version.
	 */
	public function has_unpublished_changes( WP_Post $post ): bool {
		$current = $this->versions->latest_for_briefing( $post->ID );
		if ( null === $current ) {
			return true;
		}

		list( $title, $body ) = $this->snapshot( $post );

		return $current->title !== $title || $current->body_checksum !== $this->normaliser->checksum( $body );
	}

	/**
	 * The title and body exactly as they would be stored in a version. Shortcodes
	 * are deliberately not expanded: a snapshot must not depend on other content.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function snapshot( WP_Post $post ): array {
		return array(
			trim( wp_strip_all_tags( $post->post_title ) ),
			trim( wp_kses_post( wpautop( $post->post_content ) ) ),
		);
	}

	/**
	 * @return list<int> Ids of the sign-offs that were flagged.
	 */
	private function flag_outdated_inductions( int $briefing_id, VersionNumber $published ): array {
		$flagged = array();

		foreach ( $this->signoffs->active_on_briefing( $briefing_id ) as $signoff ) {
			$status = $this->evaluator->assess( $signoff['status'], $signoff['version'], $published );
			if ( SignOffStatus::ReinductionRequired === $status ) {
				$flagged[] = $signoff['id'];
			}
		}

		$this->signoffs->set_status( $flagged, SignOffStatus::ReinductionRequired );

		return $flagged;
	}
}
