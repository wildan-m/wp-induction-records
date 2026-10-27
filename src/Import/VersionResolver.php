<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use DateTimeImmutable;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;

/**
 * Finds the briefing version that was in force on a given date.
 */
final class VersionResolver {

	/**
	 * The latest version published on or before the induction date, judged in
	 * the timezone of $date. Paper records often predate the version history;
	 * those map to the earliest version and are marked as such.
	 *
	 * @param list<BriefingVersion> $versions All versions of one briefing, in any order.
	 * @param DateTimeImmutable     $date     Induction date in the site's timezone.
	 */
	public function in_force_on( array $versions, DateTimeImmutable $date ): ?VersionMatch {
		if ( array() === $versions ) {
			return null;
		}

		usort(
			$versions,
			static fn( BriefingVersion $a, BriefingVersion $b ): int => array( $a->published_at, $a->version->major(), $a->version->minor() )
				<=> array( $b->published_at, $b->version->major(), $b->version->minor() )
		);

		$end_of_day = $date->setTime( 0, 0 )->modify( '+1 day' );
		$in_force   = null;
		foreach ( $versions as $version ) {
			if ( $version->published_at >= $end_of_day ) {
				break;
			}
			$in_force = $version;
		}

		if ( null === $in_force ) {
			return new VersionMatch( $versions[0], true );
		}

		return new VersionMatch( $in_force, false );
	}
}
