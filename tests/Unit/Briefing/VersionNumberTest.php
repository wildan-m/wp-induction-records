<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Briefing;

use ForgeBench\InductionRecords\Briefing\BumpType;
use ForgeBench\InductionRecords\Briefing\VersionNumber;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class VersionNumberTest extends TestCase {

	public function test_first_version_is_one_point_zero(): void {
		$this->assertSame( '1.0', VersionNumber::initial()->to_string() );
	}

	public function test_minor_bump_increments_the_minor_part(): void {
		$this->assertSame( '2.4', VersionNumber::of( 2, 3 )->bump( BumpType::Minor )->to_string() );
	}

	public function test_major_bump_increments_the_major_part_and_resets_minor(): void {
		$this->assertSame( '3.0', VersionNumber::of( 2, 3 )->bump( BumpType::Major )->to_string() );
	}

	public function test_bumping_leaves_the_original_unchanged(): void {
		$version = VersionNumber::of( 1, 2 );
		$version->bump( BumpType::Major );

		$this->assertSame( '1.2', $version->to_string() );
	}

	#[DataProvider( 'valid_versions' )]
	public function test_parses_major_minor_text( string $text, int $major, int $minor ): void {
		$version = VersionNumber::parse( $text );

		$this->assertSame( $major, $version->major() );
		$this->assertSame( $minor, $version->minor() );
	}

	/**
	 * @return array<string, array{0: string, 1: int, 2: int}>
	 */
	public static function valid_versions(): array {
		return array(
			'plain'              => array( '2.13', 2, 13 ),
			'surrounding spaces' => array( ' 3.0 ', 3, 0 ),
			'leading zero'       => array( '1.05', 1, 5 ),
		);
	}

	#[DataProvider( 'invalid_versions' )]
	public function test_rejects_text_that_is_not_major_minor( string $text ): void {
		$this->expectException( InvalidArgumentException::class );

		VersionNumber::parse( $text );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function invalid_versions(): array {
		return array(
			'major only'     => array( '1' ),
			'three parts'    => array( '1.2.3' ),
			'prefixed'       => array( 'v1.0' ),
			'empty'          => array( '' ),
			'major zero'     => array( '0.4' ),
			'negative minor' => array( '1.-1' ),
			'letters'        => array( 'a.b' ),
			'out of range'   => array( '70000.0' ),
		);
	}

	public function test_compares_numerically_not_as_text(): void {
		$this->assertSame( -1, VersionNumber::of( 1, 9 )->compare( VersionNumber::of( 1, 10 ) ) );
		$this->assertSame( 1, VersionNumber::of( 2, 0 )->compare( VersionNumber::of( 1, 10 ) ) );
		$this->assertSame( 0, VersionNumber::of( 1, 4 )->compare( VersionNumber::parse( '1.4' ) ) );
		$this->assertTrue( VersionNumber::of( 1, 4 )->equals( VersionNumber::parse( '1.4' ) ) );
	}

	public function test_major_part_starts_at_one(): void {
		$this->expectException( InvalidArgumentException::class );

		VersionNumber::of( 0, 1 );
	}

	public function test_converts_to_string(): void {
		$this->assertSame( 'v4.2', 'v' . VersionNumber::of( 4, 2 ) );
	}
}
