<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit;

use ForgeBench\InductionRecords\Plugin;

final class PluginHeaderTest extends TestCase {

	/**
	 * @return array<string, string>
	 */
	private static function headers(): array {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/induction-records.php' );
		preg_match_all( '/^ \* ([A-Za-z ]+):\s+(.+)$/m', $source, $matches, PREG_SET_ORDER );

		$headers = array();
		foreach ( $matches as $match ) {
			$headers[ $match[1] ] = trim( $match[2] );
		}

		return $headers;
	}

	public function test_header_version_matches_the_plugin_version(): void {
		$this->assertSame( Plugin::VERSION, self::headers()['Version'] );
	}

	public function test_header_declares_requirements_and_text_domain(): void {
		$headers = self::headers();

		$this->assertSame( '8.1', $headers['Requires PHP'] );
		$this->assertSame( '6.4', $headers['Requires at least'] );
		$this->assertSame( 'induction-records', $headers['Text Domain'] );
	}
}
