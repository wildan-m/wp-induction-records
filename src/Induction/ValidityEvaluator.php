<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Induction;

use ForgeBench\InductionRecords\Briefing\VersionNumber;

/**
 * Decides whether an induction is still valid after its briefing changes.
 *
 * Minor versions are wording fixes, so an induction stays valid for every
 * version with the same major number. A newer major version means the safety
 * content changed and the member has to be inducted again.
 */
final class ValidityEvaluator {

	public function requires_reinduction( VersionNumber $inducted_on, VersionNumber $current ): bool {
		return $inducted_on->major() < $current->major();
	}

	/**
	 * Status a sign-off should have when its briefing's current version is $current.
	 * Only active sign-offs change; flagged and superseded ones keep their status.
	 */
	public function assess( SignOffStatus $status, VersionNumber $inducted_on, VersionNumber $current ): SignOffStatus {
		if ( $status->is_active() && $this->requires_reinduction( $inducted_on, $current ) ) {
			return SignOffStatus::ReinductionRequired;
		}

		return $status;
	}
}
