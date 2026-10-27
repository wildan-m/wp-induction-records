<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Certificate;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Certificate\CertificateContext;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use ForgeBench\InductionRecords\Settings;
use ForgeBench\InductionRecords\Template\MergeFieldRenderer;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class CertificateContextTest extends TestCase {

	private static function all_fields(): string {
		return implode( ' ', array_map( static fn( string $field ): string => '{{' . $field . '}}', CertificateContext::fields() ) );
	}

	public function test_every_listed_field_is_filled_for_a_sign_off(): void {
		$context = CertificateContext::build(
			'Forge & Bench Makerspace',
			self::signoff( 42, 5, SignOffStatus::Pending ),
			self::version( 5, 3, '2.1', 'Laser cutter' ),
			'laser-cutter',
			'Robin Ashby',
			'Maria Okafor',
			new DateTimeImmutable( '2024-02-02 09:00:00', new DateTimeZone( 'UTC' ) )
		);

		$result = ( new MergeFieldRenderer() )->render( self::all_fields(), $context );

		$this->assertTrue( $result->is_complete() );
		$this->assertStringContainsString( 'IR-000042', $result->output );
		$this->assertStringContainsString( 'Forge &amp; Bench Makerspace', $result->output );
		$this->assertStringContainsString( 'Maria Okafor', $result->output );
	}

	public function test_every_listed_field_has_a_preview_value(): void {
		$result = ( new MergeFieldRenderer() )->render( self::all_fields(), CertificateContext::preview( 'Forge & Bench Makerspace' ) );

		$this->assertSame( array(), $result->unknown_fields );
	}

	public function test_default_certificate_text_uses_only_known_fields(): void {
		Functions\stubTranslationFunctions();

		$template = Settings::default_certificate_template();
		$result   = ( new MergeFieldRenderer() )->render( $template, CertificateContext::preview( 'Forge & Bench Makerspace' ) );

		$this->assertStringContainsString( '{{member.display_name}}', $template );
		$this->assertTrue( $result->is_complete() );
	}
}
