<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Pdf;

/**
 * Builds the HTML document that dompdf turns into the certificate.
 *
 * The header and footer are fixed-position elements, which dompdf repeats on
 * every page; they come first so they also appear on the first page.
 */
final class CertificateHtml {

	private const STYLES = '
		@page { margin: 34mm 18mm 30mm 18mm; }
		body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.45; color: #1d2327; }
		#page-header { position: fixed; top: -24mm; left: 0; right: 0; height: 16mm; border-bottom: 1.2pt solid #9a3b16; }
		#page-header .organisation { font-size: 15pt; font-weight: bold; color: #9a3b16; }
		#page-header .document { font-size: 8pt; color: #50575e; text-transform: uppercase; letter-spacing: 0.6pt; }
		#page-footer { position: fixed; bottom: -20mm; left: 0; right: 0; height: 13mm; border-top: 0.6pt solid #c3c4c7; padding-top: 2mm; font-size: 7.5pt; color: #50575e; }
		#page-footer .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 7pt; }
		h1 { font-size: 19pt; margin: 2mm 0 6mm; }
		h2 { font-size: 13pt; margin: 0 0 4mm; }
		.certificate table { width: 100%; border-collapse: collapse; margin: 6mm 0; }
		.certificate th, .certificate td { text-align: left; vertical-align: top; padding: 2mm 3mm; border-bottom: 0.5pt solid #dcdcde; }
		.certificate th { width: 36%; background: #f6f7f7; }
		.appendix { page-break-before: always; font-size: 9.5pt; }
	';

	public function build( CertificateData $data ): string {
		$header = sprintf(
			'<div id="page-header"><div class="organisation">%1$s</div><div class="document">%2$s</div></div>',
			$this->escape( $data->organisation_name ),
			$this->escape( $data->document_title )
		);

		$footer = sprintf(
			'<div id="page-footer"><div>%1$s %2$s</div><div class="hash">%3$s: %4$s</div></div>',
			$this->escape( $data->certificate_label ),
			$this->escape( $data->certificate_id ),
			$this->escape( $data->hash_label ),
			$this->escape( $data->rendered_hash )
		);

		$appendix = '';
		if ( '' !== trim( $data->appendix_html ) ) {
			$appendix = sprintf(
				'<div class="appendix"><h2>%1$s</h2>%2$s</div>',
				$this->escape( $data->appendix_title ),
				$data->appendix_html
			);
		}

		return '<!DOCTYPE html><html lang="' . $this->escape( $data->language ) . '"><head><meta charset="utf-8">'
			. '<title>' . $this->escape( $data->document_title . ' ' . $data->certificate_id ) . '</title>'
			. '<meta name="author" content="' . $this->escape( $data->organisation_name ) . '">'
			. '<meta name="description" content="' . $this->escape( $data->certificate_id ) . '">'
			. '<style>' . self::STYLES . '</style></head><body>'
			. $header
			. $footer
			. '<div class="certificate">' . $data->body_html . '</div>'
			. $appendix
			. '</body></html>';
	}

	private function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
