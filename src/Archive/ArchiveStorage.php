<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Archive;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Stores certificate PDFs in per-member folders under a protected root.
 *
 * Every folder the archive creates gets an .htaccess that denies all web
 * access and an empty index.php. Files are only ever served through the
 * authenticated download endpoint; nothing here produces a URL.
 */
final class ArchiveStorage {

	public const FOLDER = 'induction-records';

	private const DENY_RULES = "# Induction Records: certificates are served through an authenticated endpoint only.\n"
		. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
		. "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";

	private const INDEX_FILE = "<?php\n// Silence is golden.\n";

	public function __construct(
		private readonly string $root,
		private readonly ArchivePaths $paths
	) {
	}

	public function root(): string {
		return $this->root;
	}

	/**
	 * Writes a new file for the member.
	 *
	 * @return string Path relative to the archive root, as stored on the sign-off.
	 * @throws ArchiveException When the file cannot be written.
	 */
	public function store( int $member_id, string $contents ): string {
		list( $relative_path, $target ) = $this->reserve( $member_id );

		if ( false === file_put_contents( $target, $contents, LOCK_EX ) ) {
			throw new ArchiveException( 'Could not write a file to the certificate archive.' );
		}

		return $relative_path;
	}

	/**
	 * Copies an existing file, such as a scanned paper record, into the member's folder.
	 *
	 * @return string Path relative to the archive root.
	 * @throws ArchiveException When the source cannot be read or the copy fails.
	 */
	public function import_file( int $member_id, string $source ): string {
		if ( ! is_file( $source ) || ! is_readable( $source ) ) {
			throw new ArchiveException( sprintf( 'Cannot read "%s".', $source ) );
		}

		list( $relative_path, $target ) = $this->reserve( $member_id );

		if ( ! copy( $source, $target ) ) {
			throw new ArchiveException( 'Could not copy the file into the certificate archive.' );
		}

		return $relative_path;
	}

	/**
	 * Absolute path of a stored file.
	 *
	 * @throws ArchiveException When the relative path is not one this archive creates.
	 */
	public function path( string $relative_path ): string {
		if ( ! $this->paths->is_valid_relative_path( $relative_path ) ) {
			throw new ArchiveException( 'Invalid archive path.' );
		}

		return $this->root . '/' . $relative_path;
	}

	public function exists( string $relative_path ): bool {
		return $this->paths->is_valid_relative_path( $relative_path ) && is_file( $this->path( $relative_path ) );
	}

	public function delete( string $relative_path ): void {
		if ( $this->exists( $relative_path ) ) {
			unlink( $this->path( $relative_path ) );
		}
	}

	/**
	 * Removes the whole archive. Only used when the plugin is uninstalled with
	 * data deletion switched on.
	 */
	public function delete_all(): void {
		if ( ! is_dir( $this->root ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( ! $item instanceof SplFileInfo ) {
				continue;
			}

			if ( $item->isDir() && ! $item->isLink() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $this->root );
	}

	/**
	 * Picks an unused random path in the member's folder and makes sure the
	 * folders exist and are protected.
	 *
	 * @return array{0: string, 1: string} Relative and absolute path.
	 */
	private function reserve( int $member_id ): array {
		do {
			$relative_path = $this->paths->new_relative_path( $member_id );
			$target        = $this->path( $relative_path );
		} while ( file_exists( $target ) );

		$this->protect( $this->root );
		$this->protect( dirname( $target ) );

		return array( $relative_path, $target );
	}

	private function protect( string $directory ): void {
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
			throw new ArchiveException( 'Could not create a folder in the certificate archive.' );
		}

		$files = array(
			'.htaccess' => self::DENY_RULES,
			'index.php' => self::INDEX_FILE,
		);

		foreach ( $files as $name => $contents ) {
			$file = $directory . '/' . $name;
			if ( ! is_file( $file ) && false === file_put_contents( $file, $contents ) ) {
				throw new ArchiveException( sprintf( 'Could not write %s in the certificate archive.', $name ) );
			}
		}
	}
}
