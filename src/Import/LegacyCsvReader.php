<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use Generator;

/**
 * Reads the paper-records CSV one record at a time.
 */
final class LegacyCsvReader {

	/**
	 * Yields each record keyed by its 1-based row number, header included.
	 * Blank lines are skipped but still counted, so row numbers match what a
	 * spreadsheet shows. Values follow RFC 4180 quoting.
	 *
	 * @return Generator<int, list<string>>
	 * @throws ImportFileException When the file cannot be opened.
	 */
	public function rows( string $path ): Generator {
		$handle = is_file( $path ) && is_readable( $path ) ? fopen( $path, 'rb' ) : false;
		if ( false === $handle ) {
			throw new ImportFileException( sprintf( 'Cannot open "%s".', $path ) );
		}

		try {
			$row = 0;
			while ( true ) {
				$cells = fgetcsv( $handle, 0, ',', '"', '' );
				if ( false === $cells ) {
					break;
				}

				++$row;
				if ( array( null ) === $cells ) {
					continue;
				}

				yield $row => array_map( 'strval', $cells );
			}
		} finally {
			fclose( $handle );
		}
	}
}
