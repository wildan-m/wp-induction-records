<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Import;

use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Import\ImportFileException;
use ForgeBench\InductionRecords\Import\InvalidRowException;
use ForgeBench\InductionRecords\Import\LegacyCsvReader;
use ForgeBench\InductionRecords\Import\LegacyRowParser;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

final class LegacyRowParserTest extends TestCase {

	private function parser(): LegacyRowParser {
		$london = new DateTimeZone( 'Europe/London' );
		$parser = new LegacyRowParser( $london, new DateTimeImmutable( '2026-10-27 15:00:00', $london ) );
		$parser->read_header( LegacyRowParser::COLUMNS );

		return $parser;
	}

	/**
	 * A valid row in header order, with some values replaced.
	 *
	 * @param array<string, string> $changes
	 * @return list<string>
	 */
	private static function row( array $changes = array() ): array {
		$values = array_merge(
			array(
				'legacy_ref'       => 'FB-2019-001',
				'member_email'     => 'member1@forgebench.test',
				'machine_slug'     => 'laser-cutter',
				'inducted_on'      => '2019-03-02',
				'supervisor_email' => 'supervisor1@forgebench.test',
				'scan_filename'    => 'FB-2019-001.pdf',
			),
			$changes
		);

		return array_values( $values );
	}

	public function test_maps_a_valid_row(): void {
		$record = $this->parser()->parse(
			self::row(
				array(
					'member_email' => ' Member1@ForgeBench.test ',
					'machine_slug' => 'Laser-Cutter',
				)
			),
			2
		);

		$this->assertSame( 2, $record->row );
		$this->assertSame( 'FB-2019-001', $record->legacy_ref );
		$this->assertSame( 'member1@forgebench.test', $record->member_email );
		$this->assertSame( 'laser-cutter', $record->machine_slug );
		$this->assertSame( '2019-03-02 00:00:00 Europe/London', $record->inducted_on->format( 'Y-m-d H:i:s e' ) );
		$this->assertSame( 'supervisor1@forgebench.test', $record->supervisor_email );
		$this->assertSame( 'FB-2019-001.pdf', $record->scan_filename );
	}

	#[DataProvider( 'accepted_dates' )]
	public function test_reads_the_accepted_date_formats( string $text ): void {
		$record = $this->parser()->parse( self::row( array( 'inducted_on' => $text ) ), 2 );

		$this->assertSame( '2019-03-02', $record->inducted_on->format( 'Y-m-d' ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function accepted_dates(): array {
		return array(
			'ISO'                    => array( '2019-03-02' ),
			'day first with slashes' => array( '02/03/2019' ),
			'without leading zeros'  => array( '2/3/2019' ),
			'short month name'       => array( '2 Mar 2019' ),
			'full month name'        => array( '2 March 2019' ),
		);
	}

	#[DataProvider( 'rejected_dates' )]
	public function test_rejects_dates_that_are_invalid_ambiguous_or_in_the_future( string $text, string $problem ): void {
		$this->assert_row_problem( self::row( array( 'inducted_on' => $text ) ), $problem );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function rejected_dates(): array {
		return array(
			'30 February'         => array( '2019-02-30', 'is not a valid date' ),
			'31 April, day first' => array( '31/04/2020', 'is not a valid date' ),
			'month first'         => array( '03/31/2020', 'is not a valid date' ),
			'two-digit year'      => array( '2/3/19', 'is not a valid date' ),
			'year first, slashes' => array( '2019/03/02', 'is not a valid date' ),
			'relative words'      => array( 'last Tuesday', 'is not a valid date' ),
			'tomorrow'            => array( '2026-10-28', 'is in the future' ),
			'empty'               => array( '', 'inducted_on is empty' ),
		);
	}

	public function test_accepts_an_induction_dated_today(): void {
		$record = $this->parser()->parse( self::row( array( 'inducted_on' => '27/10/2026' ) ), 2 );

		$this->assertSame( '2026-10-27', $record->inducted_on->format( 'Y-m-d' ) );
	}

	public function test_header_columns_may_come_in_any_order_and_case_after_a_byte_order_mark(): void {
		$parser = new LegacyRowParser( new DateTimeZone( 'UTC' ), new DateTimeImmutable( '2026-10-27' ) );
		$parser->read_header( array( "\xEF\xBB\xBFScan_Filename", 'notes', 'LEGACY_REF', 'member_email', 'machine_slug', 'inducted_on', 'supervisor_email' ) );

		$record = $parser->parse(
			array( 'P-7.pdf', 'signed in red ink', 'P-7', 'member3@forgebench.test', 'wood-lathe', '2020-01-15', 'supervisor2@forgebench.test' ),
			2
		);

		$this->assertSame( 'P-7', $record->legacy_ref );
		$this->assertSame( 'P-7.pdf', $record->scan_filename );
		$this->assertSame( 'wood-lathe', $record->machine_slug );
	}

	public function test_header_without_required_columns_is_rejected(): void {
		$parser = new LegacyRowParser( new DateTimeZone( 'UTC' ), new DateTimeImmutable( '2026-10-27' ) );

		$this->expectException( ImportFileException::class );
		$this->expectExceptionMessage( 'missing required column(s): inducted_on, scan_filename' );

		$parser->read_header( array( 'legacy_ref', 'member_email', 'machine_slug', 'supervisor_email' ) );
	}

	public function test_header_with_a_repeated_column_is_rejected(): void {
		$parser = new LegacyRowParser( new DateTimeZone( 'UTC' ), new DateTimeImmutable( '2026-10-27' ) );

		$this->expectException( ImportFileException::class );
		$this->expectExceptionMessage( 'repeats the column "member_email"' );

		$parser->read_header( array_merge( LegacyRowParser::COLUMNS, array( 'Member_Email' ) ) );
	}

	public function test_rows_cannot_be_parsed_before_the_header(): void {
		$parser = new LegacyRowParser( new DateTimeZone( 'UTC' ), new DateTimeImmutable( '2026-10-27' ) );

		$this->expectException( LogicException::class );

		$parser->parse( self::row(), 2 );
	}

	public function test_short_row_names_the_missing_columns(): void {
		$this->assert_row_problem( array_slice( self::row(), 0, 4 ), 'missing column(s): supervisor_email, scan_filename' );
	}

	public function test_values_beyond_the_header_are_rejected_unless_empty(): void {
		$parser = $this->parser();

		$this->assertSame( 'FB-2019-001', $parser->parse( array_merge( self::row(), array( '', ' ' ) ), 2 )->legacy_ref );
		$this->assert_row_problem(
			array_merge( self::row( array( 'legacy_ref' => 'FB-2019-002' ) ), array( 'Smith' ) ),
			'more values than header columns',
			$parser
		);
	}

	public function test_reports_every_problem_in_a_row_at_once(): void {
		try {
			$this->parser()->parse( array( '', 'not-an-email', 'Laser Cutter!', '2019-13-01', '', 'scan.jpg' ), 5 );
			$this->fail( 'Expected the row to be rejected.' );
		} catch ( InvalidRowException $error ) {
			$this->assertSame(
				array(
					'legacy_ref is empty',
					'member_email "not-an-email" is not a valid email address',
					'machine_slug "laser cutter!" is not a valid slug',
					'inducted_on "2019-13-01" is not a valid date (use 2019-03-02, 02/03/2019 or 2 March 2019)',
					'supervisor_email is empty',
					'scan_filename "scan.jpg" is not a .pdf file',
				),
				$error->problems
			);
		}
	}

	#[DataProvider( 'unsafe_file_names' )]
	public function test_scan_filename_must_be_a_plain_file_name( string $name ): void {
		$this->assert_row_problem( self::row( array( 'scan_filename' => $name ) ), 'must be a plain file name' );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function unsafe_file_names(): array {
		return array(
			'parent folder' => array( '../FB-2019-001.pdf' ),
			'sub folder'    => array( 'scans/FB-2019-001.pdf' ),
			'absolute path' => array( '/etc/FB-2019-001.pdf' ),
			'windows path'  => array( 'C:\\scans\\FB-2019-001.pdf' ),
			'hidden file'   => array( '.FB-2019-001.pdf' ),
		);
	}

	public function test_duplicate_legacy_ref_points_to_the_first_row(): void {
		$parser = $this->parser();
		$parser->parse( self::row(), 2 );

		$this->assert_row_problem(
			self::row( array( 'legacy_ref' => 'fb-2019-001' ) ),
			'legacy_ref "fb-2019-001" already appears on row 2',
			$parser
		);
	}

	public function test_duplicate_legacy_ref_is_caught_even_when_the_first_row_was_invalid(): void {
		$parser = $this->parser();

		$this->assert_row_problem( self::row( array( 'member_email' => 'nobody' ) ), 'is not a valid email address', $parser, 3 );
		$this->assert_row_problem( self::row(), 'legacy_ref "FB-2019-001" already appears on row 3', $parser, 9 );
	}

	public function test_legacy_ref_format_is_checked(): void {
		$this->assert_row_problem( self::row( array( 'legacy_ref' => 'FB 2019 001' ) ), 'legacy_ref "FB 2019 001" must start with a letter or digit' );
	}

	public function test_reference_of_a_failed_row_is_available_for_the_report(): void {
		$parser = $this->parser();

		$this->assertSame( 'FB-2019-001', $parser->reference_in( self::row( array( 'member_email' => 'nobody' ) ) ) );
		$this->assertSame( '', $parser->reference_in( array() ) );
	}

	public function test_parses_the_paper_records_fixture(): void {
		$parser  = new LegacyRowParser( new DateTimeZone( 'Europe/London' ), new DateTimeImmutable( '2026-10-27' ) );
		$valid   = array();
		$invalid = array();

		foreach ( ( new LegacyCsvReader() )->rows( dirname( __DIR__, 2 ) . '/fixtures/legacy-inductions.csv' ) as $row => $cells ) {
			if ( 1 === $row ) {
				$parser->read_header( $cells );
				continue;
			}

			try {
				$valid[ $row ] = $parser->parse( $cells, $row )->legacy_ref;
			} catch ( InvalidRowException $error ) {
				$invalid[ $row ] = $error->getMessage();
			}
		}

		$this->assertSame(
			array(
				2 => 'FB-2019-001',
				3 => 'FB-2019-002',
				5 => 'FB-2020-003',
			),
			$valid
		);
		$this->assertSame( array( 6, 7, 8 ), array_keys( $invalid ) );
		$this->assertStringContainsString( 'member_email "not-an-email" is not a valid email address', $invalid[6] );
		$this->assertStringContainsString( 'inducted_on "31/02/2020" is not a valid date', $invalid[6] );
		$this->assertStringContainsString( 'legacy_ref "FB-2019-001" already appears on row 2', $invalid[7] );
		$this->assertStringContainsString( 'member_email "Member 1, Test" is not a valid email address', $invalid[8] );
		$this->assertStringContainsString( 'scan_filename "../FB-2021-005.pdf" must be a plain file name', $invalid[8] );
	}

	/**
	 * @param list<string> $cells
	 */
	private function assert_row_problem( array $cells, string $expected, ?LegacyRowParser $parser = null, int $row = 3 ): void {
		try {
			( $parser ?? $this->parser() )->parse( $cells, $row );
		} catch ( InvalidRowException $error ) {
			$this->assertStringContainsString( $expected, $error->getMessage() );
			return;
		}

		$this->fail( sprintf( 'Expected the row to be rejected with "%s".', $expected ) );
	}
}
