<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

/**
 * How much a new briefing version changes.
 *
 * A minor version fixes wording and keeps existing inductions valid. A major
 * version changes the safety content, so everyone inducted on an earlier major
 * version has to be inducted again.
 */
enum BumpType: string {

	case Major = 'major';
	case Minor = 'minor';
}
