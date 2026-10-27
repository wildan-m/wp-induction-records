<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Certificate;

/**
 * A rendered certificate: the exact text, its hash and the PDF built from it.
 */
final class IssuedCertificate {

	/**
	 * @param string       $rendered_text  Certificate text after merge fields were filled in.
	 * @param string       $rendered_hash  SHA-256 of $rendered_text.
	 * @param string       $pdf            PDF file contents.
	 * @param list<string> $unknown_fields Template fields that had no value.
	 */
	public function __construct(
		public readonly string $rendered_text,
		public readonly string $rendered_hash,
		public readonly string $pdf,
		public readonly array $unknown_fields
	) {
	}
}
