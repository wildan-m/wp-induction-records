<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Briefing;

use ForgeBench\InductionRecords\Briefing\BumpType;
use ForgeBench\InductionRecords\Briefing\VersionNumber;
use ForgeBench\InductionRecords\Briefing\VersionPolicy;
use ForgeBench\InductionRecords\Briefing\VersionPolicyException;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class VersionPolicyTest extends TestCase {

	#[DataProvider( 'bump_types' )]
	public function test_first_version_is_always_one_point_zero( BumpType $bump ): void {
		$this->assertSame( '1.0', ( new VersionPolicy() )->next_version( null, $bump, true )->to_string() );
	}

	/**
	 * @return array<string, array{0: BumpType}>
	 */
	public static function bump_types(): array {
		return array(
			'minor requested' => array( BumpType::Minor ),
			'major requested' => array( BumpType::Major ),
		);
	}

	public function test_minor_change_bumps_the_minor_part(): void {
		$next = ( new VersionPolicy() )->next_version( VersionNumber::of( 1, 2 ), BumpType::Minor, true );

		$this->assertSame( '1.3', $next->to_string() );
	}

	public function test_major_change_bumps_the_major_part(): void {
		$next = ( new VersionPolicy() )->next_version( VersionNumber::of( 1, 2 ), BumpType::Major, true );

		$this->assertSame( '2.0', $next->to_string() );
	}

	public function test_minor_version_without_changes_is_refused(): void {
		$this->assert_refused(
			VersionPolicyException::UNCHANGED,
			static fn() => ( new VersionPolicy() )->next_version( VersionNumber::of( 1, 2 ), BumpType::Minor, false )
		);
	}

	public function test_major_version_may_republish_unchanged_text(): void {
		$next = ( new VersionPolicy() )->next_version( VersionNumber::of( 1, 2 ), BumpType::Major, false );

		$this->assertSame( '2.0', $next->to_string() );
	}

	public function test_change_note_is_trimmed(): void {
		$this->assertSame( 'Added the extraction fan check.', ( new VersionPolicy() )->change_note( "  Added the extraction fan check.\n" ) );
	}

	public function test_empty_change_note_is_refused(): void {
		$this->assert_refused( VersionPolicyException::MISSING_NOTE, static fn() => ( new VersionPolicy() )->change_note( " \n\t" ) );
	}

	public function test_change_note_length_is_counted_in_characters(): void {
		$policy = new VersionPolicy();
		$note   = str_repeat( 'ü', VersionPolicy::MAX_NOTE_LENGTH );

		$this->assertSame( $note, $policy->change_note( $note ) );
		$this->assert_refused( VersionPolicyException::NOTE_TOO_LONG, static fn() => $policy->change_note( $note . 'ü' ) );
	}

	private function assert_refused( string $reason, callable $action ): void {
		try {
			$action();
		} catch ( VersionPolicyException $error ) {
			$this->assertSame( $reason, $error->reason );
			return;
		}

		$this->fail( sprintf( 'Expected the "%s" rule to refuse this request.', $reason ) );
	}
}
