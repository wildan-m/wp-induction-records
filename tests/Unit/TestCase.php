<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit;

use Brain\Monkey;
use DateTimeImmutable;
use DateTimeZone;
use FilesystemIterator;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Briefing\VersionNumber;
use ForgeBench\InductionRecords\Induction\SignOff;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Base test case: Brain Monkey for WordPress functions and hooks, temporary
 * folders, and builders for domain objects.
 */
abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * @var list<string>
	 */
	private array $temp_dirs = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		foreach ( $this->temp_dirs as $dir ) {
			self::remove_dir( $dir );
		}

		parent::tearDown();
	}

	/**
	 * Empty folder that is removed after the test.
	 */
	protected function temp_dir(): string {
		$dir = sys_get_temp_dir() . '/induction-records-tests-' . bin2hex( random_bytes( 6 ) );
		mkdir( $dir, 0700, true );
		$this->temp_dirs[] = $dir;

		return $dir;
	}

	protected function temp_file( string $contents, string $name = 'records.csv' ): string {
		$path = $this->temp_dir() . '/' . $name;
		file_put_contents( $path, $contents );

		return $path;
	}

	protected static function version(
		int $id,
		int $briefing_id,
		string $number,
		string $title = 'Laser cutter',
		string $published_at = '2024-01-01 09:00:00'
	): BriefingVersion {
		return new BriefingVersion(
			$id,
			$briefing_id,
			VersionNumber::parse( $number ),
			$title,
			'<p>Wear the supplied goggles.</p>',
			hash( 'sha256', '<p>Wear the supplied goggles.</p>' ),
			'Version ' . $number,
			new DateTimeImmutable( $published_at, new DateTimeZone( 'UTC' ) ),
			1
		);
	}

	protected static function signoff( int $id, int $version_id, SignOffStatus $status, string $inducted_at = '2024-02-01 10:00:00' ): SignOff {
		$inducted = new DateTimeImmutable( $inducted_at, new DateTimeZone( 'UTC' ) );

		return new SignOff( $id, 42, $version_id, 7, $inducted, null, null, null, null, null, null, $status, $inducted );
	}

	private static function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item instanceof SplFileInfo && $item->isDir() && ! $item->isLink() ) {
				rmdir( $item->getPathname() );
			} elseif ( $item instanceof SplFileInfo ) {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}
}
