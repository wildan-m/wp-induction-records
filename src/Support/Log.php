<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Support;

use Throwable;

/**
 * Reports unexpected failures. Users see a generic message; the details go to
 * the PHP error log and to the induction_records_error action for sites that
 * forward errors elsewhere.
 */
final class Log {

	public static function error( string $context, Throwable $error ): void {
		/**
		 * Fires when an unexpected error interrupted an induction action.
		 *
		 * @param string    $context What was being done.
		 * @param Throwable $error   The error.
		 */
		do_action( 'induction_records_error', $context, $error );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Unexpected failures must reach the server log.
		error_log( sprintf( 'Induction Records (%1$s): %2$s in %3$s:%4$d', $context, $error->getMessage(), $error->getFile(), $error->getLine() ) );
	}
}
