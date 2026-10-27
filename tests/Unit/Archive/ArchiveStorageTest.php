<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Archive;

use ForgeBench\InductionRecords\Archive\ArchiveException;
use ForgeBench\InductionRecords\Archive\ArchivePaths;
use ForgeBench\InductionRecords\Archive\ArchiveStorage;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class ArchiveStorageTest extends TestCase {

	private const SECRET = 'b7e4c0f5a1d94e2c8f3a6b9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f';

	private function storage(): ArchiveStorage {
		return new ArchiveStorage( $this->temp_dir() . '/' . ArchiveStorage::FOLDER, new ArchivePaths( self::SECRET ) );
	}

	public function test_stores_a_file_in_the_members_folder_under_a_random_name(): void {
		$storage  = $this->storage();
		$relative = $storage->store( 42, '%PDF-1.7 certificate' );

		$this->assertSame( ( new ArchivePaths( self::SECRET ) )->member_folder( 42 ), dirname( $relative ) );
		$this->assertMatchesRegularExpression( '#^[a-f0-9]{32}/[a-f0-9]{32}\.pdf$#', $relative );
		$this->assertStringEqualsFile( $storage->path( $relative ), '%PDF-1.7 certificate' );
		$this->assertTrue( $storage->exists( $relative ) );
	}

	public function test_every_archive_folder_denies_web_access(): void {
		$storage  = $this->storage();
		$relative = $storage->store( 42, '%PDF-1.7' );

		foreach ( array( $storage->root(), $storage->root() . '/' . dirname( $relative ) ) as $folder ) {
			$this->assertFileExists( $folder . '/index.php' );
			$this->assertFileExists( $folder . '/.htaccess' );

			$rules = (string) file_get_contents( $folder . '/.htaccess' );
			$this->assertStringContainsString( 'Require all denied', $rules );
			$this->assertStringContainsString( 'Deny from all', $rules );
		}
	}

	public function test_copies_an_existing_scan_into_the_archive(): void {
		$source = $this->temp_file( '%PDF-1.4 scanned form', 'FB-2019-001.pdf' );

		$storage  = $this->storage();
		$relative = $storage->import_file( 7, $source );

		$this->assertStringEqualsFile( $storage->path( $relative ), '%PDF-1.4 scanned form' );
		$this->assertStringNotContainsString( 'FB-2019-001', $relative );
		$this->assertFileExists( $source );
	}

	public function test_refuses_paths_it_did_not_create(): void {
		$this->expectException( ArchiveException::class );

		$this->storage()->path( '../../wp-config.php' );
	}

	public function test_foreign_or_missing_files_do_not_exist(): void {
		$storage = $this->storage();

		$this->assertFalse( $storage->exists( '../../wp-config.php' ) );
		$this->assertFalse( $storage->exists( str_repeat( 'a', 32 ) . '/' . str_repeat( 'b', 32 ) . '.pdf' ) );
	}

	public function test_deletes_a_file(): void {
		$storage  = $this->storage();
		$relative = $storage->store( 42, '%PDF-1.7' );

		$storage->delete( $relative );

		$this->assertFalse( $storage->exists( $relative ) );
	}

	public function test_deletes_the_whole_archive(): void {
		$storage = $this->storage();
		$storage->store( 42, '%PDF-1.7' );
		$storage->store( 7, '%PDF-1.7' );

		$storage->delete_all();

		$this->assertDirectoryDoesNotExist( $storage->root() );
	}
}
