<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Database;

/**
 * Custom tables, created and upgraded with dbDelta().
 *
 * Bump VERSION whenever a CREATE TABLE statement changes. Installer runs
 * upgrade() on the next request after the plugin files are updated, because
 * activation hooks do not run on updates.
 */
final class Schema {

	public const VERSION = 1;
	public const OPTION  = 'induction_records_schema_version';

	public static function versions_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'induction_records_versions';
	}

	public static function signoffs_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'induction_records_signoffs';
	}

	public static function installed_version(): int {
		return (int) get_option( self::OPTION, 0 );
	}

	/**
	 * Creates both tables or brings them up to date, then records the schema version.
	 */
	public static function upgrade(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$from     = self::installed_version();
		$collate  = $wpdb->get_charset_collate();
		$versions = self::versions_table();
		$signoffs = self::signoffs_table();

		// dbDelta is strict about formatting: one column per line, two spaces after PRIMARY KEY.
		dbDelta(
			"CREATE TABLE {$versions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  briefing_id bigint(20) unsigned NOT NULL,
  version_major smallint(5) unsigned NOT NULL,
  version_minor smallint(5) unsigned NOT NULL,
  title varchar(255) NOT NULL,
  body_html longtext NOT NULL,
  body_checksum char(64) NOT NULL,
  change_note text NOT NULL,
  published_at datetime NOT NULL,
  published_by bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY briefing_version (briefing_id,version_major,version_minor),
  KEY briefing_published (briefing_id,published_at)
) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$signoffs} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  member_id bigint(20) unsigned NOT NULL,
  briefing_version_id bigint(20) unsigned NOT NULL,
  supervisor_id bigint(20) unsigned NOT NULL,
  inducted_at datetime NOT NULL,
  member_confirmed_at datetime DEFAULT NULL,
  ip_address varchar(45) DEFAULT NULL,
  user_agent varchar(255) DEFAULT NULL,
  rendered_hash char(64) DEFAULT NULL,
  pdf_path varchar(191) DEFAULT NULL,
  legacy_ref varchar(64) DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY legacy_ref (legacy_ref),
  KEY member_status (member_id,status),
  KEY briefing_version_id (briefing_version_id),
  KEY status_inducted (status,inducted_at)
) {$collate};"
		);

		update_option( self::OPTION, self::VERSION, true );

		/**
		 * Fires after the plugin tables were created or upgraded.
		 *
		 * @param int $from Schema version before the upgrade, 0 on a fresh install.
		 * @param int $to   Schema version now installed.
		 */
		do_action( 'induction_records_schema_upgraded', $from, self::VERSION );
	}

	/**
	 * Drops both tables. Only called by the uninstaller when data deletion is switched on.
	 */
	public static function drop(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own tables on uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i, %i', self::signoffs_table(), self::versions_table() ) );

		delete_option( self::OPTION );
	}
}
