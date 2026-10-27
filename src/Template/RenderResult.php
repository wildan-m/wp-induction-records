<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Template;

/**
 * Output of a merge-field render plus anything that could not be resolved.
 */
final class RenderResult {

	/**
	 * @param string       $output          Rendered text.
	 * @param list<string> $unknown_fields  Fields with no printable value, in order of first use.
	 * @param list<string> $unknown_filters Filters the renderer does not support.
	 */
	public function __construct(
		public readonly string $output,
		public readonly array $unknown_fields = array(),
		public readonly array $unknown_filters = array()
	) {
	}

	public function is_complete(): bool {
		return array() === $this->unknown_fields && array() === $this->unknown_filters;
	}

	/**
	 * SHA-256 of the exact rendered output, as stored with a sign-off.
	 */
	public function sha256(): string {
		return hash( 'sha256', $this->output );
	}
}
