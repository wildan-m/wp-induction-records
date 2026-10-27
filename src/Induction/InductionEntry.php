<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Induction;

use ForgeBench\InductionRecords\Briefing\BriefingVersion;

/**
 * A sign-off together with the version it was given on and the machine's
 * current version.
 */
final class InductionEntry {

	public function __construct(
		public readonly SignOff $signoff,
		public readonly BriefingVersion $version,
		public readonly ?BriefingVersion $current_version
	) {
	}

	public function machine_name(): string {
		return null !== $this->current_version ? $this->current_version->title : $this->version->title;
	}
}
