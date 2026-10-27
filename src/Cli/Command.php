<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Cli;

use ForgeBench\InductionRecords\Import\ImportFileException;
use ForgeBench\InductionRecords\Import\LegacyImporter;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Manages machine induction records.
 */
final class Command {

	public function __construct(
		private readonly LegacyImporter $importer
	) {
	}

	/**
	 * Imports paper induction records from a CSV file.
	 *
	 * Each row becomes a sign-off on the briefing version that was in force on
	 * its induction date, and its scanned form is copied into the member's
	 * archive folder. Rows whose legacy_ref was already imported are skipped,
	 * so the command can be run again after fixing errors. A bad row is
	 * reported and the import carries on with the next one.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : CSV file with the columns legacy_ref, member_email, machine_slug,
	 * inducted_on, supervisor_email and scan_filename. Dates may be written as
	 * 2019-03-02, 02/03/2019 (day first) or 2 March 2019.
	 *
	 * --files=<dir>
	 * : Folder holding the scanned PDFs named in scan_filename.
	 *
	 * [--dry-run]
	 * : Check every row and report what would be created, skipped or rejected,
	 * without writing anything.
	 *
	 * [--format=<format>]
	 * : Report format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param list<string>               $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function import( array $args, array $assoc_args ): void {
		$csv     = $args[0] ?? '';
		$files   = (string) Utils\get_flag_value( $assoc_args, 'files', '' );
		$dry_run = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$format  = (string) Utils\get_flag_value( $assoc_args, 'format', 'table' );

		// WP_CLI::error() exits; the returns make the flow explicit.
		if ( ! is_file( $csv ) || ! is_readable( $csv ) ) {
			WP_CLI::error( sprintf( 'Cannot read the CSV file "%s".', $csv ) );
			return;
		}

		if ( '' === $files || ! is_dir( $files ) ) {
			WP_CLI::error( 'Pass the folder of scanned PDFs with --files=<dir>.' );
			return;
		}

		try {
			$report = $this->importer->run( $csv, $files, $dry_run );
		} catch ( ImportFileException $error ) {
			WP_CLI::error( $error->getMessage() );
			return;
		}

		Utils\format_items( $format, $report->rows(), array( 'row', 'legacy_ref', 'result', 'detail' ) );

		if ( $report->has_errors() ) {
			WP_CLI::warning( $report->summary() );
			WP_CLI::halt( 1 );
		}

		WP_CLI::success( $report->summary() );
	}
}
