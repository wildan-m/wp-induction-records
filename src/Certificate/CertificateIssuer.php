<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Certificate;

use DateTimeImmutable;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Induction\SignOff;
use ForgeBench\InductionRecords\Pdf\CertificateData;
use ForgeBench\InductionRecords\Pdf\CertificatePdfRenderer;
use ForgeBench\InductionRecords\Settings;
use ForgeBench\InductionRecords\Support\Formatting;
use ForgeBench\InductionRecords\Template\MergeFieldRenderer;

/**
 * Fills in the certificate template for a sign-off and renders the PDF.
 */
final class CertificateIssuer {

	public function __construct(
		private readonly MergeFieldRenderer $renderer,
		private readonly CertificatePdfRenderer $pdf,
		private readonly Settings $settings
	) {
	}

	public function issue( SignOff $signoff, BriefingVersion $version, DateTimeImmutable $confirmed_at ): IssuedCertificate {
		$organisation = $this->settings->organisation_name();

		$context = CertificateContext::build(
			$organisation,
			$signoff,
			$version,
			(string) get_post_field( 'post_name', $version->briefing_id ),
			Formatting::person( $signoff->member_id ),
			Formatting::person( $signoff->supervisor_id ),
			$confirmed_at
		);

		$result = $this->renderer->render( $this->settings->certificate_template(), $context );

		if ( ! $result->is_complete() ) {
			/**
			 * Fires when a certificate was issued with merge fields that could not be filled in.
			 * The placeholders are printed as written; fix the template under Inductions > Settings.
			 *
			 * @param list<string> $unknown_fields  Field names without a value.
			 * @param list<string> $unknown_filters Unsupported filter names.
			 * @param SignOff      $signoff         The sign-off the certificate belongs to.
			 */
			do_action( 'induction_records_certificate_incomplete', $result->unknown_fields, $result->unknown_filters, $signoff );
		}

		$data = new CertificateData(
			$organisation,
			$signoff->certificate_id(),
			__( 'Induction certificate', 'induction-records' ),
			$result->output,
			$result->sha256(),
			sprintf(
				/* translators: 1: machine name, 2: briefing version number */
				__( 'Safety briefing: %1$s, version %2$s', 'induction-records' ),
				$version->title,
				$version->version->to_string()
			),
			$version->body_html,
			__( 'Certificate', 'induction-records' ),
			__( 'SHA-256 of certificate text', 'induction-records' ),
			/* translators: Keep {PAGE_NUM} and {PAGE_COUNT} as they are; they are replaced by numbers. */
			__( 'Page {PAGE_NUM} of {PAGE_COUNT}', 'induction-records' ),
			substr( get_locale(), 0, 2 )
		);

		return new IssuedCertificate( $result->output, $result->sha256(), $this->pdf->render( $data ), $result->unknown_fields );
	}
}
