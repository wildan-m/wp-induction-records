<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Import;

use ForgeBench\InductionRecords\Import\ImportFileException;
use ForgeBench\InductionRecords\Import\LegacyCsvReader;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class LegacyCsvReaderTest extends TestCase {

	public function test_keys_records_by_row_number_and_skips_blank_lines(): void {
		$rows = iterator_to_array( ( new LegacyCsvReader() )->rows( dirname( __DIR__, 2 ) . '/fixtures/legacy-inductions.csv' ) );

		$this->assertSame( array( 1, 2, 3, 5, 6, 7, 8 ), array_keys( $rows ) );
		$this->assertSame( 'legacy_ref', $rows[1][0] );
		$this->assertSame( 'Ashby, Robin', $rows[8][1] );
	}

	public function test_reads_quoted_values_with_quotes_and_line_breaks(): void {
		$path = $this->temp_file( "legacy_ref,note\nA-1,\"Said \"\"stop\"\"\nthen left\"\nA-2,plain\n" );

		$rows = iterator_to_array( ( new LegacyCsvReader() )->rows( $path ) );

		$this->assertSame( array( 'A-1', "Said \"stop\"\nthen left" ), $rows[2] );
		$this->assertSame( array( 'A-2', 'plain' ), $rows[3] );
	}

	public function test_backslashes_are_kept_as_typed(): void {
		$path = $this->temp_file( "legacy_ref,folder\nA-1,\"C:\\scans\\\"\n" );

		$rows = iterator_to_array( ( new LegacyCsvReader() )->rows( $path ) );

		$this->assertSame( array( 'A-1', 'C:\\scans\\' ), $rows[2] );
	}

	public function test_missing_file_is_reported(): void {
		$this->expectException( ImportFileException::class );

		iterator_to_array( ( new LegacyCsvReader() )->rows( $this->temp_dir() . '/missing.csv' ) );
	}
}
