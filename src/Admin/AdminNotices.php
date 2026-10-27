<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

/**
 * One-time admin notices that survive the redirect after a form post.
 * Stored per user in a short-lived transient.
 */
final class AdminNotices {

	private const TRANSIENT_PREFIX = 'induction_records_notices_';

	private const TYPES = array( 'success', 'warning', 'error', 'info' );

	/**
	 * @param string $type    success, warning, error or info.
	 * @param string $message Plain text; escaped when shown.
	 */
	public static function add( string $type, string $message ): void {
		$key     = self::TRANSIENT_PREFIX . get_current_user_id();
		$notices = get_transient( $key );
		$notices = is_array( $notices ) ? $notices : array();

		$notices[] = array(
			'type'    => in_array( $type, self::TYPES, true ) ? $type : 'info',
			'message' => $message,
		);

		set_transient( $key, $notices, 5 * MINUTE_IN_SECONDS );
	}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		$key     = self::TRANSIENT_PREFIX . get_current_user_id();
		$notices = get_transient( $key );

		if ( ! is_array( $notices ) || array() === $notices ) {
			return;
		}

		delete_transient( $key );

		foreach ( $notices as $notice ) {
			if ( ! is_array( $notice ) ) {
				continue;
			}

			wp_admin_notice(
				esc_html( (string) ( $notice['message'] ?? '' ) ),
				array(
					'type'        => (string) ( $notice['type'] ?? 'info' ),
					'dismissible' => true,
				)
			);
		}
	}
}
