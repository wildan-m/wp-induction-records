<?php
/**
 * Uninstall routine. Data is kept unless "Delete all induction data when the
 * plugin is deleted" is ticked under Inductions > Settings.
 *
 * @package ForgeBench\InductionRecords
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// The uninstaller only needs the plugin's own classes.
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'ForgeBench\\InductionRecords\\';
			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}

			$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

ForgeBench\InductionRecords\Uninstaller::run();
