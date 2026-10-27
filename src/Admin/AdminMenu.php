<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

use ForgeBench\InductionRecords\Capabilities;

/**
 * The "Inductions" admin menu.
 *
 * Sign-offs and Record induction are added before WordPress adds the
 * "Machine briefings" post type entry (priority 10), Publish version and
 * Settings after it, so the menu reads in workflow order.
 */
final class AdminMenu {

	public const SLUG = 'induction-records';

	public function __construct(
		private readonly SignOffsPage $signoffs,
		private readonly RecordInductionPage $record,
		private readonly PublishVersionPage $publish,
		private readonly SettingsPage $settings
	) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_primary_pages' ), 9 );
		add_action( 'admin_menu', array( $this, 'add_secondary_pages' ), 11 );
	}

	public function add_primary_pages(): void {
		add_menu_page(
			__( 'Induction sign-offs', 'induction-records' ),
			__( 'Inductions', 'induction-records' ),
			Capabilities::RECORD,
			self::SLUG,
			array( $this->signoffs, 'render' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page(
			self::SLUG,
			__( 'Induction sign-offs', 'induction-records' ),
			__( 'Sign-offs', 'induction-records' ),
			Capabilities::RECORD,
			self::SLUG,
			array( $this->signoffs, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Record induction', 'induction-records' ),
			__( 'Record induction', 'induction-records' ),
			Capabilities::RECORD,
			RecordInductionPage::SLUG,
			array( $this->record, 'render' )
		);
	}

	public function add_secondary_pages(): void {
		add_submenu_page(
			self::SLUG,
			__( 'Publish briefing version', 'induction-records' ),
			__( 'Publish version', 'induction-records' ),
			Capabilities::MANAGE,
			PublishVersionPage::SLUG,
			array( $this->publish, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Induction Records settings', 'induction-records' ),
			__( 'Settings', 'induction-records' ),
			'manage_options',
			SettingsPage::SLUG,
			array( $this->settings, 'render' )
		);
	}
}
