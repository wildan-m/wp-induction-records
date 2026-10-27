<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

/**
 * Result of publishing a briefing version.
 */
final class PublishOutcome {

	/**
	 * @param BriefingVersion $version       The version that was stored.
	 * @param int             $flagged_count Sign-offs that now need re-induction.
	 */
	public function __construct(
		public readonly BriefingVersion $version,
		public readonly int $flagged_count
	) {
	}
}
