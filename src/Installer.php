<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords;

use ForgeBench\InductionRecords\Database\Schema;

/**
 * Activation, deactivation and per-site installation.
 */
final class Installer {

	public const ARCHIVE_KEY_OPTION = 'induction_records_archive_key';
	public const REWRITE_OPTION     = 'induction_records_rewrite_version';

	/**
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function activate( $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install_site();
				restore_current_blog();
			}
			return;
		}

		self::install_site();
	}

	public static function deactivate(): void {
		// Rewrite rules are rebuilt on the next request, without the My Account endpoint.
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Runs on every load. Installs a site that was never set up (such as a new
	 * site on a network) and upgrades the tables after the plugin was updated,
	 * since activation hooks do not run on updates.
	 */
	public static function maybe_upgrade(): void {
		$installed = Schema::installed_version();

		if ( 0 === $installed ) {
			self::install_site();
		} elseif ( $installed < Schema::VERSION ) {
			Schema::upgrade();
		}
	}

	public static function install_site(): void {
		Schema::upgrade();
		Capabilities::grant_defaults();
		self::archive_key();

		// Forces a rewrite flush once the My Account endpoint is registered.
		delete_option( self::REWRITE_OPTION );
	}

	/**
	 * Flushes rewrite rules once per plugin version, late on init so the
	 * WooCommerce endpoint is already registered.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( Plugin::VERSION !== get_option( self::REWRITE_OPTION ) ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_OPTION, Plugin::VERSION, true );
		}
	}

	/**
	 * Site secret that the opaque per-member archive folder names are derived from.
	 * Created on first use; never shown anywhere.
	 */
	public static function archive_key(): string {
		$key = get_option( self::ARCHIVE_KEY_OPTION );

		if ( ! is_string( $key ) || 64 !== strlen( $key ) ) {
			// add_option() does nothing if a parallel request stored a key first.
			add_option( self::ARCHIVE_KEY_OPTION, bin2hex( random_bytes( 32 ) ), '', true );
			$key = (string) get_option( self::ARCHIVE_KEY_OPTION );
		}

		return $key;
	}
}
