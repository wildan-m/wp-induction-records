<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords;

use WP_Role;
use WP_User;

/**
 * Plugin capabilities.
 *
 * manage_inductions: edit briefings, publish versions, see and resend every
 * certificate. record_inductions: record inductions as a supervisor.
 * Administrators get both on activation; supervisors are granted
 * record_inductions individually on their user profile.
 */
final class Capabilities {

	public const MANAGE = 'manage_inductions';
	public const RECORD = 'record_inductions';

	public static function grant_defaults(): void {
		$administrator = get_role( 'administrator' );

		if ( $administrator instanceof WP_Role ) {
			$administrator->add_cap( self::MANAGE );
			$administrator->add_cap( self::RECORD );
		}
	}

	public static function revoke_all(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			$role->remove_cap( self::MANAGE );
			$role->remove_cap( self::RECORD );
		}

		$users = get_users(
			array(
				'capability__in' => array( self::MANAGE, self::RECORD ),
				'fields'         => 'all',
			)
		);

		foreach ( $users as $user ) {
			if ( $user instanceof WP_User ) {
				$user->remove_cap( self::MANAGE );
				$user->remove_cap( self::RECORD );
			}
		}
	}
}
