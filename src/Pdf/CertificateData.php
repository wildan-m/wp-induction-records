<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Pdf;

/**
 * Everything the PDF renderer needs, as plain strings.
 *
 * Labels arrive already translated so the renderer itself has no WordPress
 * dependency. $body_html and $appendix_html are trusted HTML: the certificate
 * text comes from the merge-field renderer (values escaped) and the appendix
 * is the briefing snapshot, sanitised when the version was published.
 */
final class CertificateData {

	public function __construct(
		public readonly string $organisation_name,
		public readonly string $certificate_id,
		public readonly string $document_title,
		public readonly string $body_html,
		public readonly string $rendered_hash,
		public readonly string $appendix_title = '',
		public readonly string $appendix_html = '',
		public readonly string $certificate_label = 'Certificate',
		public readonly string $hash_label = 'SHA-256 of certificate text',
		public readonly string $page_label = 'Page {PAGE_NUM} of {PAGE_COUNT}',
		public readonly string $language = 'en'
	) {
	}
}
