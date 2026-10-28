<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Pdf;

use ForgeBench\InductionRecords\Pdf\CertificateData;
use ForgeBench\InductionRecords\Pdf\CertificateHtml;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class CertificateHtmlTest extends TestCase {

	private static function data(): CertificateData {
		return new CertificateData(
			'Forge & Bench Makerspace',
			'IR-000042',
			'Induction certificate',
			'<h1>Certificate of machine induction</h1><p>Test Member 1</p>',
			str_repeat( 'c', 64 ),
			'Safety briefing: Laser cutter, version 2.1',
			'<p>Stay with the laser while it cuts.</p>'
		);
	}

	public function test_header_and_footer_carry_the_organisation_certificate_id_and_hash(): void {
		$html = ( new CertificateHtml() )->build( self::data() );

		$this->assertStringContainsString( '<div class="organisation">Forge &amp; Bench Makerspace</div>', $html );
		$this->assertStringContainsString( '<div>Certificate IR-000042</div>', $html );
		$this->assertStringContainsString( 'SHA-256 of certificate text: ' . str_repeat( 'c', 64 ), $html );
	}

	public function test_certificate_text_and_briefing_are_inserted_as_html(): void {
		$html = ( new CertificateHtml() )->build( self::data() );

		$this->assertStringContainsString( '<div class="certificate"><h1>Certificate of machine induction</h1><p>Test Member 1</p></div>', $html );
		$this->assertStringContainsString( '<div class="appendix"><h2>Safety briefing: Laser cutter, version 2.1</h2><p>Stay with the laser while it cuts.</p></div>', $html );
	}

	public function test_appendix_is_left_out_without_a_briefing(): void {
		$data = new CertificateData( 'Forge & Bench Makerspace', 'IR-000042', 'Induction certificate', '<p>Text</p>', str_repeat( 'c', 64 ) );

		$this->assertStringNotContainsString( 'class="appendix"', ( new CertificateHtml() )->build( $data ) );
	}

	public function test_labels_and_metadata_are_escaped(): void {
		$data = new CertificateData( 'Forge <&> Bench', 'IR-1"', 'Title', '<p>Text</p>', 'hash', '', '', 'Cert<b>', 'Hash' );

		$html = ( new CertificateHtml() )->build( $data );

		$this->assertStringContainsString( '<meta name="author" content="Forge &lt;&amp;&gt; Bench">', $html );
		$this->assertStringContainsString( 'Cert&lt;b&gt; IR-1&quot;', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}
}
