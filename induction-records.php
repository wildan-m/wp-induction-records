<?php
/**
 * Plugin Name:       Induction Records
 * Description:       Versioned machine safety briefings, supervisor-recorded inductions with member sign-off, certificate PDFs and a protected per-member archive.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Forge & Bench Makerspace
 * License:           Proprietary
 * Text Domain:       induction-records
 * Domain Path:       /languages
 *
 * @package ForgeBench\InductionRecords
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			wp_admin_notice(
				esc_html__( 'Induction Records cannot start because its Composer dependencies are missing. Run "composer install --no-dev" in the plugin folder.', 'induction-records' ),
				array( 'type' => 'error' )
			);
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( ForgeBench\InductionRecords\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( ForgeBench\InductionRecords\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( ForgeBench\InductionRecords\Plugin::class, 'boot' ) );
