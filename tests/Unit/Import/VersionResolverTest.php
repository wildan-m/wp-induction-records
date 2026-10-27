<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Import;

use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Import\VersionResolver;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class VersionResolverTest extends TestCase {

	private static function day( string $date ): DateTimeImmutable {
		return new DateTimeImmutable( $date, new DateTimeZone( 'Europe/London' ) );
	}

	/**
	 * Laser cutter history, deliberately out of order.
	 *
	 * @return list<BriefingVersion>
	 */
	private static function history(): array {
		return array(
			self::version( 3, 1, '2.0', 'Laser cutter', '2022-09-01 08:00:00' ),
			self::version( 1, 1, '1.0', 'Laser cutter', '2018-01-10 08:00:00' ),
			self::version( 2, 1, '1.1', 'Laser cutter', '2020-05-01 10:00:00' ),
		);
	}

	public function test_picks_the_latest_version_published_on_or_before_the_date(): void {
		$match = ( new VersionResolver() )->in_force_on( self::history(), self::day( '2021-06-15' ) );

		$this->assertSame( '1.1', $match?->version->version->to_string() );
		$this->assertFalse( $match?->predates_history );
	}

	public function test_a_version_published_on_the_induction_day_counts(): void {
		$match = ( new VersionResolver() )->in_force_on( self::history(), self::day( '2020-05-01' ) );

		$this->assertSame( '1.1', $match?->version->version->to_string() );
	}

	public function test_a_version_published_after_the_induction_day_does_not_count(): void {
		$match = ( new VersionResolver() )->in_force_on( self::history(), self::day( '2020-04-30' ) );

		$this->assertSame( '1.0', $match?->version->version->to_string() );
	}

	public function test_the_day_is_judged_in_the_site_timezone(): void {
		// 23:30 UTC on 1 May 2020 was already 2 May in London (BST).
		$versions = array(
			self::version( 1, 1, '1.0', 'Laser cutter', '2018-01-10 08:00:00' ),
			self::version( 2, 1, '1.1', 'Laser cutter', '2020-05-01 23:30:00' ),
		);
		$resolver = new VersionResolver();

		$this->assertSame( '1.0', $resolver->in_force_on( $versions, self::day( '2020-05-01' ) )?->version->version->to_string() );
		$this->assertSame( '1.1', $resolver->in_force_on( $versions, self::day( '2020-05-02' ) )?->version->version->to_string() );
	}

	public function test_records_older_than_the_history_map_to_the_earliest_version(): void {
		$match = ( new VersionResolver() )->in_force_on( self::history(), self::day( '2016-02-29' ) );

		$this->assertSame( '1.0', $match?->version->version->to_string() );
		$this->assertTrue( $match?->predates_history );
	}

	public function test_no_versions_means_no_match(): void {
		$this->assertNull( ( new VersionResolver() )->in_force_on( array(), self::day( '2020-01-01' ) ) );
	}
}
