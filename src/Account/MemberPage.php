<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Account;

use DateTimeInterface;
use ForgeBench\InductionRecords\Archive\DownloadController;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Induction\InductionSummary;
use ForgeBench\InductionRecords\Induction\SignOff;
use ForgeBench\InductionRecords\Support\Formatting;

/**
 * Renders a member's inductions: waiting for confirmation, current,
 * re-induction required and earlier records, with certificate downloads.
 *
 * Shared by the WooCommerce My Account tab and the [induction_records]
 * shortcode. Themes can override templates/member-inductions.php by copying
 * it to induction-records/member-inductions.php in the theme.
 */
final class MemberPage {

	public function __construct(
		private readonly SignOffRepository $signoffs,
		private readonly VersionRepository $versions
	) {
	}

	/**
	 * @param int    $user_id    Member whose inductions are shown.
	 * @param string $return_url Page to come back to after confirming an induction.
	 */
	public function render( int $user_id, string $return_url ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $return_url is used by the template.
		$signoffs = $this->signoffs->for_member( $user_id );
		$versions = $this->versions->find_many(
			array_map( static fn( SignOff $signoff ): int => $signoff->briefing_version_id, $signoffs )
		);
		$current  = $this->versions->latest_for_briefings(
			array_values( array_map( static fn( BriefingVersion $version ): int => $version->briefing_id, $versions ) )
		);

		$summary = InductionSummary::build( $signoffs, $versions, $current );
		$notice  = $this->notice();
		$view    = $this;

		ob_start();
		include $this->template();

		return (string) ob_get_clean();
	}

	public function download_url( SignOff $signoff ): string {
		return DownloadController::url( $signoff->id );
	}

	public function date( ?DateTimeInterface $date ): string {
		return Formatting::date( $date );
	}

	public function person( int $user_id ): string {
		return Formatting::person( $user_id );
	}

	/**
	 * Message for the induction_notice query argument set after a confirmation.
	 *
	 * @return array{type: string, text: string}
	 */
	private function notice(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which fixed message to show.
		$code = isset( $_GET['induction_notice'] ) ? sanitize_key( wp_unslash( $_GET['induction_notice'] ) ) : '';

		$messages = array(
			'confirmed'   => array( 'success', __( 'Thank you. Your induction is confirmed and your certificate has been emailed to you.', 'induction-records' ) ),
			'acknowledge' => array( 'error', __( 'Please tick the box to confirm you received and understood the briefing.', 'induction-records' ) ),
			'not_pending' => array( 'error', __( 'This induction can no longer be confirmed because the briefing has changed. Please ask a supervisor to induct you again.', 'induction-records' ) ),
			'not_found'   => array( 'error', __( 'That induction could not be found.', 'induction-records' ) ),
			'failed'      => array( 'error', __( 'Something went wrong while confirming your induction. Please try again or contact the makerspace team.', 'induction-records' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return array(
				'type' => '',
				'text' => '',
			);
		}

		return array(
			'type' => $messages[ $code ][0],
			'text' => $messages[ $code ][1],
		);
	}

	private function template(): string {
		$template = locate_template( 'induction-records/member-inductions.php' );
		if ( '' === $template ) {
			$template = dirname( __DIR__, 2 ) . '/templates/member-inductions.php';
		}

		/**
		 * Filters the path of the member inductions template.
		 *
		 * @param string $template Absolute path.
		 */
		return (string) apply_filters( 'induction_records_member_template', $template );
	}
}
