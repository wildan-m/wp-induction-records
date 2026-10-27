<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use ForgeBench\InductionRecords\Briefing\BriefingVersion;

/**
 * The briefing version a legacy record is mapped to.
 */
final class VersionMatch {

	/**
	 * @param BriefingVersion $version          Version in force on the induction date.
	 * @param bool            $predates_history True when the induction is older than every
	 *                                          published version and the earliest one was used.
	 */
	public function __construct(
		public readonly BriefingVersion $version,
		public readonly bool $predates_history
	) {
	}
}
