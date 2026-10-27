<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Induction;

use ForgeBench\InductionRecords\Briefing\BriefingVersion;

/**
 * Groups one member's sign-offs the way the member page shows them.
 *
 * A machine is listed under "re-induction required" only while the member has
 * no pending or valid induction on it; once they are inducted again, the old
 * flagged record moves to the history list.
 */
final class InductionSummary {

	/**
	 * @param list<InductionEntry> $awaiting_confirmation Recorded by a supervisor, not yet confirmed.
	 * @param list<InductionEntry> $valid                 Confirmed on the current major version.
	 * @param list<InductionEntry> $reinduction_required  Newest flagged record per machine without a newer induction.
	 * @param list<InductionEntry> $history               Superseded and older flagged records.
	 */
	private function __construct(
		public readonly array $awaiting_confirmation,
		public readonly array $valid,
		public readonly array $reinduction_required,
		public readonly array $history
	) {
	}

	/**
	 * @param list<SignOff>               $signoffs A member's sign-offs, in any order.
	 * @param array<int, BriefingVersion> $versions Versions the sign-offs refer to, keyed by version id.
	 * @param array<int, BriefingVersion> $current  Current version of each briefing, keyed by briefing id.
	 */
	public static function build( array $signoffs, array $versions, array $current ): self {
		usort(
			$signoffs,
			static fn( SignOff $a, SignOff $b ): int => array( $b->inducted_at, $b->id ) <=> array( $a->inducted_at, $a->id )
		);

		$entries = array();
		$active  = array();
		foreach ( $signoffs as $signoff ) {
			$version = $versions[ $signoff->briefing_version_id ] ?? null;
			if ( null === $version ) {
				continue;
			}

			$entries[] = new InductionEntry( $signoff, $version, $current[ $version->briefing_id ] ?? null );
			if ( $signoff->status->is_active() ) {
				$active[ $version->briefing_id ] = true;
			}
		}

		$awaiting = array();
		$valid    = array();
		$flagged  = array();
		$history  = array();
		foreach ( $entries as $entry ) {
			$briefing_id = $entry->version->briefing_id;

			switch ( $entry->signoff->status ) {
				case SignOffStatus::Pending:
					$awaiting[] = $entry;
					break;

				case SignOffStatus::Confirmed:
					$valid[] = $entry;
					break;

				case SignOffStatus::ReinductionRequired:
					if ( isset( $active[ $briefing_id ] ) || isset( $flagged[ $briefing_id ] ) ) {
						$history[] = $entry;
					} else {
						$flagged[ $briefing_id ] = $entry;
					}
					break;

				default:
					$history[] = $entry;
			}
		}

		return new self( $awaiting, $valid, array_values( $flagged ), $history );
	}

	public function is_empty(): bool {
		return array() === $this->awaiting_confirmation
			&& array() === $this->valid
			&& array() === $this->reinduction_required
			&& array() === $this->history;
	}
}
