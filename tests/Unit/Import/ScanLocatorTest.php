<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Import;

use ForgeBench\InductionRecords\Import\InvalidRowException;
use ForgeBench\InductionRecords\Import\ScanLocator;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class ScanLocatorTest extends TestCase {

	public function test_finds_a_pdf_in_the_folder(): void {
		$folder = $this->temp_dir();
		file_put_contents( $folder . '/FB-2019-001.pdf', "%PDF-1.4\n% scanned form" );

		$this->assertSame( realpath( $folder . '/FB-2019-001.pdf' ), ( new ScanLocator() )->locate( $folder, 'FB-2019-001.pdf' ) );
	}

	public function test_missing_scan_is_a_row_error(): void {
		$this->expectException( InvalidRowException::class );
		$this->expectExceptionMessage( 'scan "FB-2019-404.pdf" was not found in the files folder' );

		( new ScanLocator() )->locate( $this->temp_dir(), 'FB-2019-404.pdf' );
	}

	public function test_file_that_is_not_a_pdf_is_a_row_error(): void {
		$folder = $this->temp_dir();
		file_put_contents( $folder . '/FB-2019-002.pdf', "\xFF\xD8\xFF\xE0 photo of the form" );

		$this->expectException( InvalidRowException::class );
		$this->expectExceptionMessage( 'scan "FB-2019-002.pdf" is not a PDF file' );

		( new ScanLocator() )->locate( $folder, 'FB-2019-002.pdf' );
	}

	public function test_files_outside_the_folder_are_refused(): void {
		$parent = $this->temp_dir();
		mkdir( $parent . '/scans' );
		file_put_contents( $parent . '/private.pdf', '%PDF-1.4' );
		symlink( $parent . '/private.pdf', $parent . '/scans/linked.pdf' );

		$locator = new ScanLocator();
		foreach ( array( '../private.pdf', 'linked.pdf' ) as $name ) {
			try {
				$locator->locate( $parent . '/scans', $name );
				$this->fail( sprintf( 'Expected "%s" to be refused.', $name ) );
			} catch ( InvalidRowException $error ) {
				$this->assertStringContainsString( 'was not found in the files folder', $error->getMessage() );
			}
		}
	}
}
