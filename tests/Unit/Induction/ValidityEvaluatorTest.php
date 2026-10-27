<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Induction;

use ForgeBench\InductionRecords\Briefing\VersionNumber;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use ForgeBench\InductionRecords\Induction\ValidityEvaluator;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidityEvaluatorTest extends TestCase {

	#[DataProvider( 'publications' )]
	public function test_status_after_a_new_version_is_published( SignOffStatus $status, string $inducted_on, string $published, SignOffStatus $expected ): void {
		$assessed = ( new ValidityEvaluator() )->assess( $status, VersionNumber::parse( $inducted_on ), VersionNumber::parse( $published ) );

		$this->assertSame( $expected, $assessed );
	}

	/**
	 * @return array<string, array{0: SignOffStatus, 1: string, 2: string, 3: SignOffStatus}>
	 */
	public static function publications(): array {
		return array(
			'minor bump keeps a confirmed induction valid' => array( SignOffStatus::Confirmed, '1.0', '1.1', SignOffStatus::Confirmed ),
			'many minor bumps keep it valid'               => array( SignOffStatus::Confirmed, '1.0', '1.7', SignOffStatus::Confirmed ),
			'major bump flags a confirmed induction'       => array( SignOffStatus::Confirmed, '1.3', '2.0', SignOffStatus::ReinductionRequired ),
			'major bump flags an unconfirmed induction'    => array( SignOffStatus::Pending, '1.0', '2.0', SignOffStatus::ReinductionRequired ),
			'minor bump leaves an unconfirmed one pending' => array( SignOffStatus::Pending, '2.0', '2.1', SignOffStatus::Pending ),
			'skipping majors still flags'                  => array( SignOffStatus::Confirmed, '1.0', '3.2', SignOffStatus::ReinductionRequired ),
			'flagged stays flagged after a minor bump'     => array( SignOffStatus::ReinductionRequired, '1.0', '2.1', SignOffStatus::ReinductionRequired ),
			'major bump leaves superseded alone'           => array( SignOffStatus::Superseded, '1.0', '2.0', SignOffStatus::Superseded ),
			'republishing the same number changes nothing' => array( SignOffStatus::Confirmed, '2.0', '2.0', SignOffStatus::Confirmed ),
		);
	}

	public function test_reinduction_depends_only_on_the_major_number(): void {
		$evaluator = new ValidityEvaluator();

		$this->assertFalse( $evaluator->requires_reinduction( VersionNumber::of( 2, 0 ), VersionNumber::of( 2, 9 ) ) );
		$this->assertTrue( $evaluator->requires_reinduction( VersionNumber::of( 2, 9 ), VersionNumber::of( 3, 0 ) ) );
	}
}
