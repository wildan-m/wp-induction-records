<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

/**
 * Finds a scanned paper record inside the import folder.
 */
final class ScanLocator {

	/**
	 * @return string Absolute path of the scan.
	 * @throws InvalidRowException When the file is missing, resolves outside the folder or is not a PDF.
	 */
	public function locate( string $directory, string $filename ): string {
		$base = realpath( $directory );
		$file = realpath( $directory . DIRECTORY_SEPARATOR . $filename );

		if ( false === $base || false === $file || ! str_starts_with( $file, rtrim( $base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) || ! is_file( $file ) ) {
			throw InvalidRowException::because( sprintf( 'scan "%s" was not found in the files folder', $filename ) );
		}

		if ( '%PDF-' !== file_get_contents( $file, false, null, 0, 5 ) ) {
			throw InvalidRowException::because( sprintf( 'scan "%s" is not a PDF file', $filename ) );
		}

		return $file;
	}
}
