<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Mail;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use ForgeBench\InductionRecords\Mail\CertificateMailer;
use ForgeBench\InductionRecords\Mail\MemberMessage;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;
use Mockery;

final class CertificateMailerTest extends TestCase {

	private const PAGE_URL = 'https://forgebench.test/my-account/inductions/';

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	private static function message( string $pdf_path = '/srv/uploads/induction-records/3f2a/9c1d.pdf' ): MemberMessage {
		return new MemberMessage(
			42,
			'robin.ashby@forgebench.test',
			'Robin Ashby',
			'Laser cutter',
			'2.1',
			self::PAGE_URL,
			'Forge & Bench Makerspace',
			'IR-000042',
			$pdf_path
		);
	}

	public function test_emails_the_certificate_with_a_readable_attachment_name(): void {
		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'robin.ashby@forgebench.test',
				'Your Laser cutter induction certificate (IR-000042)',
				Mockery::on( static fn( string $body ): bool => str_starts_with( $body, 'Hi Robin Ashby,' ) && str_contains( $body, self::PAGE_URL ) ),
				array(),
				array( 'induction-certificate-IR-000042.pdf' => '/srv/uploads/induction-records/3f2a/9c1d.pdf' )
			)
			->andReturn( true );

		$this->assertTrue( ( new CertificateMailer() )->send_certificate( self::message() ) );
	}

	public function test_subject_body_and_headers_are_filterable(): void {
		$message = self::message();

		Filters\expectApplied( 'induction_records_certificate_email_subject' )
			->once()
			->with( 'Your Laser cutter induction certificate (IR-000042)', $message )
			->andReturn( 'Laser cutter certificate' );
		Filters\expectApplied( 'induction_records_certificate_email_body' )
			->once()
			->with( Mockery::type( 'string' ), $message )
			->andReturn( 'Body from the theme' );
		Filters\expectApplied( 'induction_records_email_headers' )
			->once()
			->with( array(), 'certificate', $message )
			->andReturn( array( 'Reply-To: inductions@forgebench.test' ) );

		Functions\expect( 'wp_mail' )
			->once()
			->with( 'robin.ashby@forgebench.test', 'Laser cutter certificate', 'Body from the theme', array( 'Reply-To: inductions@forgebench.test' ), Mockery::type( 'array' ) )
			->andReturn( true );

		$this->assertTrue( ( new CertificateMailer() )->send_certificate( $message ) );
	}

	public function test_reports_a_failed_send(): void {
		Functions\when( 'wp_mail' )->justReturn( false );

		$this->assertFalse( ( new CertificateMailer() )->send_certificate( self::message() ) );
	}

	public function test_sends_nothing_without_a_certificate_file(): void {
		Functions\expect( 'wp_mail' )->never();

		$this->assertFalse( ( new CertificateMailer() )->send_certificate( self::message( '' ) ) );
	}

	public function test_confirmation_request_links_to_the_inductions_page(): void {
		Filters\expectApplied( 'induction_records_confirmation_email_subject' )->once();
		Filters\expectApplied( 'induction_records_confirmation_email_body' )->once();

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'robin.ashby@forgebench.test',
				'Please confirm your Laser cutter induction',
				Mockery::on( static fn( string $body ): bool => str_contains( $body, 'briefing version 2.1' ) && str_contains( $body, self::PAGE_URL ) ),
				array()
			)
			->andReturn( true );

		$this->assertTrue( ( new CertificateMailer() )->request_confirmation( self::message( '' ) ) );
	}
}
