<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Induction;

use RuntimeException;

/**
 * An induction action that was refused. The message is translated and safe to
 * show (escaped) to the user; the reason code is used in redirects.
 */
final class InductionException extends RuntimeException {

	public function __construct(
		public readonly string $reason,
		string $message
	) {
		parent::__construct( $message );
	}
}
