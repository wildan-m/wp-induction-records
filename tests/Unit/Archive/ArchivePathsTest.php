<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Archive;

use ForgeBench\InductionRecords\Archive\ArchivePaths;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class ArchivePathsTest extends TestCase {

	private const SECRET = 'b7e4c0f5a1d94e2c8f3a6b9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f';

	public function test_member_folder_is_32_hex_characters(): void {
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', ( new ArchivePaths( self::SECRET ) )->member_folder( 42 ) );
	}

	public function test_member_folder_is_stable(): void {
		$this->assertSame(
			( new ArchivePaths( self::SECRET ) )->member_folder( 42 ),
			( new ArchivePaths( self::SECRET ) )->member_folder( 42 )
		);
	}

	public function test_members_get_different_folders(): void {
		$paths = new ArchivePaths( self::SECRET );

		$this->assertNotSame( $paths->member_folder( 42 ), $paths->member_folder( 43 ) );
	}

	public function test_folder_names_depend_on_the_site_secret(): void {
		$this->assertNotSame(
			( new ArchivePaths( self::SECRET ) )->member_folder( 42 ),
			( new ArchivePaths( strrev( self::SECRET ) ) )->member_folder( 42 )
		);
	}

	#[DataProvider( 'members' )]
	public function test_paths_reveal_no_email_name_or_user_id( int $member_id, string $email, string $name ): void {
		$relative = ( new ArchivePaths( self::SECRET ) )->new_relative_path( $member_id );
		$personal = array(
			$email,
			(string) strstr( $email, '@', true ),
			(string) strstr( $email, '@' ),
			$name,
			str_replace( ' ', '-', $name ),
			str_replace( ' ', '_', $name ),
		);

		foreach ( $personal as $detail ) {
			$this->assertStringNotContainsStringIgnoringCase( $detail, $relative );
		}

		foreach ( explode( ' ', $name ) as $part ) {
			$this->assertStringNotContainsStringIgnoringCase( $part, $relative );
		}

		$this->assertNotSame( (string) $member_id, dirname( $relative ) );
	}

	/**
	 * @return array<string, array{0: int, 1: string, 2: string}>
	 */
	public static function members(): array {
		return array(
			'Robin' => array( 42, 'robin.ashby@forgebench.test', 'Robin Ashby' ),
			'Priya' => array( 7, 'priya.nair@forgebench.test', 'Priya Nair' ),
			'Tomas' => array( 1, 'tomas.weller@forgebench.test', 'Tomas Weller' ),
			'Maria' => array( 1009, 'maria.okafor@forgebench.test', 'Maria Okafor' ),
		);
	}

	public function test_file_names_are_random_and_unique(): void {
		$paths = new ArchivePaths( self::SECRET );
		$names = array();
		for ( $i = 0; $i < 50; ++$i ) {
			$names[] = basename( $paths->new_relative_path( 42 ) );
		}

		$this->assertCount( 50, array_unique( $names ) );
		foreach ( $names as $name ) {
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}\.pdf$/', $name );
		}
	}

	public function test_a_members_files_share_one_folder(): void {
		$paths = new ArchivePaths( self::SECRET );

		$this->assertSame( dirname( $paths->new_relative_path( 42 ) ), dirname( $paths->new_relative_path( 42 ) ) );
	}

	public function test_accepts_the_paths_it_creates(): void {
		$paths = new ArchivePaths( self::SECRET );

		$this->assertTrue( $paths->is_valid_relative_path( $paths->new_relative_path( 42 ) ) );
	}

	#[DataProvider( 'foreign_paths' )]
	public function test_refuses_any_other_path( string $relative ): void {
		$this->assertFalse( ( new ArchivePaths( self::SECRET ) )->is_valid_relative_path( $relative ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function foreign_paths(): array {
		$folder = str_repeat( 'a', 32 );
		$file   = str_repeat( 'b', 32 );

		return array(
			'parent traversal'  => array( '../' . $folder . '/' . $file . '.pdf' ),
			'absolute'          => array( '/' . $folder . '/' . $file . '.pdf' ),
			'nested traversal'  => array( $folder . '/../../wp-config.php' ),
			'backslash'         => array( $folder . '\\' . $file . '.pdf' ),
			'upper case'        => array( strtoupper( $folder ) . '/' . $file . '.pdf' ),
			'other extension'   => array( $folder . '/' . $file . '.php' ),
			'trailing new line' => array( $folder . '/' . $file . ".pdf\n" ),
			'readable names'    => array( 'robin-ashby/certificate.pdf' ),
			'empty'             => array( '' ),
		);
	}

	public function test_short_secret_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		new ArchivePaths( 'too-short' );
	}

	public function test_member_id_must_be_positive(): void {
		$this->expectException( InvalidArgumentException::class );

		( new ArchivePaths( self::SECRET ) )->member_folder( 0 );
	}
}
