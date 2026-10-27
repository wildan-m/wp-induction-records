<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Induction;

use ForgeBench\InductionRecords\Induction\InductionEntry;
use ForgeBench\InductionRecords\Induction\InductionSummary;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class InductionSummaryTest extends TestCase {

	public function test_groups_a_members_sign_offs_by_status(): void {
		$laser = self::version( 10, 1, '1.0', 'Laser cutter' );
		$lathe = self::version( 20, 2, '2.0', 'Wood lathe' );
		$cnc   = self::version( 30, 3, '1.0', 'CNC router' );

		$summary = InductionSummary::build(
			array(
				self::signoff( 1, 10, SignOffStatus::Confirmed, '2024-01-10 10:00:00' ),
				self::signoff( 2, 20, SignOffStatus::Pending, '2024-02-10 10:00:00' ),
				self::signoff( 3, 30, SignOffStatus::ReinductionRequired, '2024-03-10 10:00:00' ),
			),
			array(
				10 => $laser,
				20 => $lathe,
				30 => $cnc,
			),
			array(
				1 => $laser,
				2 => $lathe,
				3 => self::version( 31, 3, '2.0', 'CNC router' ),
			)
		);

		$this->assertSame( array( 2 ), self::ids( $summary->awaiting_confirmation ) );
		$this->assertSame( array( 1 ), self::ids( $summary->valid ) );
		$this->assertSame( array( 3 ), self::ids( $summary->reinduction_required ) );
		$this->assertSame( array(), $summary->history );
		$this->assertSame( '2.0', $summary->reinduction_required[0]->current_version?->version->to_string() );
		$this->assertSame( 'CNC router', $summary->reinduction_required[0]->machine_name() );
	}

	public function test_machine_leaves_the_reinduction_list_once_the_member_is_inducted_again(): void {
		$first  = self::version( 10, 1, '1.0' );
		$second = self::version( 11, 1, '2.0' );

		$summary = InductionSummary::build(
			array(
				self::signoff( 1, 10, SignOffStatus::ReinductionRequired, '2023-05-01 10:00:00' ),
				self::signoff( 2, 11, SignOffStatus::Pending, '2024-05-01 10:00:00' ),
			),
			array(
				10 => $first,
				11 => $second,
			),
			array( 1 => $second )
		);

		$this->assertSame( array(), $summary->reinduction_required );
		$this->assertSame( array( 2 ), self::ids( $summary->awaiting_confirmation ) );
		$this->assertSame( array( 1 ), self::ids( $summary->history ) );
	}

	public function test_only_the_newest_flagged_induction_per_machine_needs_action(): void {
		$summary = InductionSummary::build(
			array(
				self::signoff( 1, 10, SignOffStatus::ReinductionRequired, '2021-05-01 10:00:00' ),
				self::signoff( 2, 11, SignOffStatus::ReinductionRequired, '2023-05-01 10:00:00' ),
				self::signoff( 3, 10, SignOffStatus::Superseded, '2020-05-01 10:00:00' ),
			),
			array(
				10 => self::version( 10, 1, '1.0' ),
				11 => self::version( 11, 1, '2.0' ),
			),
			array( 1 => self::version( 12, 1, '3.0' ) )
		);

		$this->assertSame( array( 2 ), self::ids( $summary->reinduction_required ) );
		$this->assertSame( array( 1, 3 ), self::ids( $summary->history ) );
	}

	public function test_sign_offs_on_unknown_versions_are_left_out(): void {
		$summary = InductionSummary::build( array( self::signoff( 1, 99, SignOffStatus::Confirmed ) ), array(), array() );

		$this->assertTrue( $summary->is_empty() );
	}

	/**
	 * @param list<InductionEntry> $entries
	 * @return list<int>
	 */
	private static function ids( array $entries ): array {
		return array_map( static fn( InductionEntry $entry ): int => $entry->signoff->id, $entries );
	}
}
