<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use RuntimeException;

/**
 * One import row cannot be imported. Carries every problem found, so the
 * report lists them all at once.
 */
final class InvalidRowException extends RuntimeException {

	/**
	 * @param list<string> $problems
	 */
	public function __construct(
		public readonly array $problems
	) {
		parent::__construct( implode( '; ', $problems ) );
	}

	public static function because( string $problem ): self {
		return new self( array( $problem ) );
	}
}
