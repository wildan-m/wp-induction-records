<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords;

use ForgeBench\InductionRecords\Briefing\BriefingPostType;
use ForgeBench\InductionRecords\Database\Schema;

/**
 * Runs when the plugin is deleted.
 *
 * Induction records are a safety audit trail, so nothing is removed unless
 * "Delete all induction data when the plugin is deleted" is ticked under
 * Inductions > Settings. On a network the setting is honoured per site.
 */
final class Uninstaller {

	public static function run(): void {
		if ( ! is_multisite() ) {
			self::uninstall_site();
			return;
		}

		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::uninstall_site();
			restore_current_blog();
		}
	}

	private static function uninstall_site(): void {
		if ( ! ( new Settings() )->delete_data_on_uninstall() ) {
			return;
		}

		$key = get_option( Installer::ARCHIVE_KEY_OPTION );
		if ( is_string( $key ) && strlen( $key ) >= 32 ) {
			Plugin::make_archive_storage()->delete_all();
		}

		self::delete_briefings();
		Schema::drop();
		Capabilities::revoke_all();

		delete_option( Settings::OPTION );
		delete_option( Installer::ARCHIVE_KEY_OPTION );
		delete_option( Installer::REWRITE_OPTION );
	}

	private static function delete_briefings(): void {
		do {
			$ids = get_posts(
				array(
					'post_type'   => BriefingPostType::POST_TYPE,
					'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft' ),
					'numberposts' => 100,
					'fields'      => 'ids',
				)
			);

			$deleted = 0;
			foreach ( $ids as $id ) {
				if ( wp_delete_post( (int) $id, true ) ) {
					++$deleted;
				}
			}
		} while ( $deleted > 0 );
	}
}
