<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Import;

use ForgeBench\InductionRecords\Import\ImportReport;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class ImportReportTest extends TestCase {

	public function test_dry_run_reports_what_would_happen(): void {
		$report = new ImportReport( true );
		$report->created( 2, 'FB-2019-001', 'laser-cutter 1.0, confirmed' );
		$report->skipped( 3, 'FB-2019-002', 'already imported as IR-000007' );
		$report->error( 4, 'FB-2020-003', 'no user account for member tomas.weller@forgebench.test' );

		$this->assertSame( array( 'would-create', 'would-skip', 'error' ), array_column( $report->rows(), 'result' ) );
		$this->assertSame( 'Dry run: 1 would be created, 1 would be skipped, 1 with errors. Nothing was written.', $report->summary() );
		$this->assertTrue( $report->has_errors() );
	}

	public function test_import_reports_what_happened(): void {
		$report = new ImportReport( false );
		$report->created( 2, 'FB-2019-001', 'IR-000010: laser-cutter 1.0, confirmed' );
		$report->created( 3, 'FB-2019-002', 'IR-000011: wood-lathe 2.1, reinduction_required' );
		$report->skipped( 5, 'FB-2020-003', 'already imported as IR-000007' );

		$this->assertSame( array( 'created', 'created', 'skipped' ), array_column( $report->rows(), 'result' ) );
		$this->assertSame( '2 created, 1 skipped, 0 with errors.', $report->summary() );
		$this->assertFalse( $report->has_errors() );
		$this->assertSame(
			array(
				'row'        => 2,
				'legacy_ref' => 'FB-2019-001',
				'result'     => 'created',
				'detail'     => 'IR-000010: laser-cutter 1.0, confirmed',
			),
			$report->rows()[0]
		);
	}
}
