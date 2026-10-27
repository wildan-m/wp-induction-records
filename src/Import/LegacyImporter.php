<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use DateTimeImmutable;
use ForgeBench\InductionRecords\Archive\ArchiveStorage;
use ForgeBench\InductionRecords\Briefing\BriefingPostType;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Induction\SignOff;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use ForgeBench\InductionRecords\Induction\ValidityEvaluator;
use Throwable;
use WP_User;

/**
 * Imports paper induction records: one confirmed sign-off per CSV row, on the
 * briefing version in force on the induction date, with the scanned form
 * copied into the member's archive folder.
 *
 * Idempotent on legacy_ref, so a run can be repeated after fixing errors.
 * Every row is handled on its own; a bad row is reported and the run carries on.
 */
final class LegacyImporter {

	/**
	 * @var array<string, int> Email => user id (0 when there is no account), for this run.
	 */
	private array $user_ids = array();

	/**
	 * @var array<string, int> Machine slug => briefing id (0 when unknown), for this run.
	 */
	private array $briefing_ids = array();

	/**
	 * @var array<int, list<BriefingVersion>> Briefing id => versions, oldest first, for this run.
	 */
	private array $versions_by_briefing = array();

	public function __construct(
		private readonly LegacyCsvReader $reader,
		private readonly ScanLocator $scans,
		private readonly VersionResolver $resolver,
		private readonly ValidityEvaluator $evaluator,
		private readonly VersionRepository $versions,
		private readonly SignOffRepository $signoffs,
		private readonly ArchiveStorage $archive
	) {
	}

	/**
	 * @throws ImportFileException When the file cannot be read or its header is unusable.
	 */
	public function run( string $csv_path, string $files_dir, bool $dry_run ): ImportReport {
		$timezone = wp_timezone();
		$parser   = new LegacyRowParser( $timezone, new DateTimeImmutable( 'today', $timezone ) );
		$report   = new ImportReport( $dry_run );
		$header   = false;

		foreach ( $this->reader->rows( $csv_path ) as $row => $cells ) {
			if ( ! $header ) {
				$parser->read_header( $cells );
				$header = true;
				continue;
			}

			try {
				$this->import( $parser->parse( $cells, $row ), $files_dir, $report );
			} catch ( Throwable $error ) {
				$report->error( $row, $parser->reference_in( $cells ), $error->getMessage() );
			}
		}

		if ( ! $header ) {
			throw new ImportFileException( 'The CSV file is empty.' );
		}

		return $report;
	}

	private function import( LegacyRecord $record, string $files_dir, ImportReport $report ): void {
		$existing = $this->signoffs->find_by_legacy_ref( $record->legacy_ref );
		if ( null !== $existing ) {
			$report->skipped( $record->row, $record->legacy_ref, sprintf( 'already imported as %s', $existing->certificate_id() ) );
			return;
		}

		$member_id     = $this->user_id( $record->member_email, 'member' );
		$supervisor_id = $this->user_id( $record->supervisor_email, 'supervisor' );
		$briefing_id   = $this->briefing_id( $record->machine_slug );
		$versions      = $this->versions_of( $briefing_id );

		$match = $this->resolver->in_force_on( $versions, $record->inducted_on );
		if ( null === $match ) {
			throw InvalidRowException::because( sprintf( 'machine "%s" has no published briefing version', $record->machine_slug ) );
		}

		$scan   = $this->scans->locate( $files_dir, $record->scan_filename );
		$status = $this->status_for( $record, $member_id, $briefing_id, $match->version, $versions[ count( $versions ) - 1 ] );

		$detail = sprintf( '%1$s %2$s, %3$s', $record->machine_slug, $match->version->version->to_string(), $status->value );
		if ( $match->predates_history ) {
			$detail .= ' (inducted before the first published version; mapped to the earliest)';
		}

		if ( $report->dry_run ) {
			$report->created( $record->row, $record->legacy_ref, $detail );
			return;
		}

		$pdf_path = $this->archive->import_file( $member_id, $scan );

		try {
			$signoff_id = $this->signoffs->create_signoff(
				$member_id,
				$match->version->id,
				$supervisor_id,
				$record->inducted_on,
				$status,
				$record->inducted_on,
				$pdf_path,
				$record->legacy_ref
			);
		} catch ( Throwable $error ) {
			$this->archive->delete( $pdf_path );

			if ( null !== $this->signoffs->find_by_legacy_ref( $record->legacy_ref ) ) {
				$report->skipped( $record->row, $record->legacy_ref, 'imported by another process' );
				return;
			}

			throw $error;
		}

		if ( SignOffStatus::Superseded !== $status ) {
			$this->signoffs->supersede_others( $member_id, $briefing_id, $signoff_id );
		}

		$report->created( $record->row, $record->legacy_ref, SignOff::format_certificate_id( $signoff_id ) . ': ' . $detail );
	}

	/**
	 * A paper record is confirmed (or flagged, if a newer major version exists)
	 * unless the member already holds a more recent induction on the machine,
	 * in which case it goes straight into their history.
	 */
	private function status_for( LegacyRecord $record, int $member_id, int $briefing_id, BriefingVersion $inducted_on, BriefingVersion $current ): SignOffStatus {
		$newest = $this->signoffs->newest_settled( $member_id, $briefing_id );
		if ( null !== $newest && $newest->inducted_at >= $record->inducted_on ) {
			return SignOffStatus::Superseded;
		}

		return $this->evaluator->assess( SignOffStatus::Confirmed, $inducted_on->version, $current->version );
	}

	private function user_id( string $email, string $role ): int {
		if ( ! array_key_exists( $email, $this->user_ids ) ) {
			$user                     = get_user_by( 'email', $email );
			$this->user_ids[ $email ] = $user instanceof WP_User ? $user->ID : 0;
		}

		if ( 0 === $this->user_ids[ $email ] ) {
			throw InvalidRowException::because( sprintf( 'no user account for %1$s %2$s', $role, $email ) );
		}

		return $this->user_ids[ $email ];
	}

	private function briefing_id( string $slug ): int {
		if ( ! array_key_exists( $slug, $this->briefing_ids ) ) {
			$ids = get_posts(
				array(
					'post_type'   => BriefingPostType::POST_TYPE,
					'name'        => $slug,
					'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);

			$this->briefing_ids[ $slug ] = isset( $ids[0] ) ? (int) $ids[0] : 0;
		}

		if ( 0 === $this->briefing_ids[ $slug ] ) {
			throw InvalidRowException::because( sprintf( 'no machine briefing with the slug "%s"', $slug ) );
		}

		return $this->briefing_ids[ $slug ];
	}

	/**
	 * @return list<BriefingVersion> Oldest first.
	 */
	private function versions_of( int $briefing_id ): array {
		if ( ! isset( $this->versions_by_briefing[ $briefing_id ] ) ) {
			$this->versions_by_briefing[ $briefing_id ] = $this->versions->all_for_briefing( $briefing_id );
		}

		return $this->versions_by_briefing[ $briefing_id ];
	}
}
