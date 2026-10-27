<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Pdf;

use ForgeBench\InductionRecords\Pdf\CertificateData;
use ForgeBench\InductionRecords\Pdf\CertificateHtml;
use ForgeBench\InductionRecords\Pdf\CertificatePdfRenderer;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class CertificatePdfRendererTest extends TestCase {

	private static string $pdf = '';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$data = new CertificateData(
			'Forge & Bench Makerspace',
			'IR-000042',
			'Induction certificate',
			'<h1>Certificate of machine induction</h1><p>Zoë Ångström completed the safety induction for the Laser cutter.</p>',
			str_repeat( 'c', 64 ),
			'Safety briefing: Laser cutter, version 2.1',
			str_repeat( '<p>Keep the lid closed while the laser fires, and never leave a job running unattended.</p>', 60 )
		);

		self::$pdf = ( new CertificatePdfRenderer( new CertificateHtml(), sys_get_temp_dir() ) )->render( $data );
	}

	public function test_produces_a_complete_pdf_file(): void {
		$this->assertStringStartsWith( '%PDF-', self::$pdf );
		$this->assertMatchesRegularExpression( '/%%EOF\s*$/', self::$pdf );
	}

	public function test_pages_are_a4(): void {
		$this->assertMatchesRegularExpression( '#/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+595\.28\d*\s+841\.89\d*\s*\]#', self::$pdf );
	}

	public function test_font_is_embedded(): void {
		$this->assertStringContainsString( '/FontFile2', self::$pdf );
		$this->assertMatchesRegularExpression( '#/BaseFont\s*/[A-Z]{6}\+DejaVuSans#', self::$pdf );
	}

	public function test_long_briefings_continue_on_further_pages(): void {
		$this->assertGreaterThan( 2, preg_match_all( '#/Type\s*/Page\b#', self::$pdf ) );
	}

	public function test_document_title_names_the_certificate(): void {
		// dompdf writes document metadata as UTF-16BE text.
		$this->assertStringContainsString( mb_convert_encoding( 'Induction certificate IR-000042', 'UTF-16BE', 'UTF-8' ), self::$pdf );
	}
}
