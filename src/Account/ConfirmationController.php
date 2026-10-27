<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Account;

use ForgeBench\InductionRecords\Induction\InductionException;
use ForgeBench\InductionRecords\Induction\InductionService;
use ForgeBench\InductionRecords\Support\Log;
use Throwable;

/**
 * Handles the member's "Confirm induction" form (admin-post.php, which
 * WooCommerce leaves open to customers).
 */
final class ConfirmationController {

	public const ACTION = 'induction_records_confirm';

	public function __construct(
		private readonly InductionService $inductions
	) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, 'auth_redirect' );
	}

	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is per sign-off and checked right below.
		$signoff_id = isset( $_POST['signoff'] ) ? absint( $_POST['signoff'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $signoff_id );

		$return_url = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		$return_url = wp_validate_redirect( $return_url, home_url( '/' ) );

		if ( empty( $_POST['acknowledge'] ) ) {
			$this->redirect( $return_url, 'acknowledge' );
		}

		try {
			$this->inductions->confirm( $signoff_id, get_current_user_id(), $this->client_ip(), $this->user_agent() );
			$notice = 'confirmed';
		} catch ( InductionException $error ) {
			$notice = $error->reason;
		} catch ( Throwable $error ) {
			Log::error( 'confirm induction', $error );
			$notice = 'failed';
		}

		$this->redirect( $return_url, $notice );
	}

	private function redirect( string $url, string $notice ): never {
		wp_safe_redirect( add_query_arg( 'induction_notice', $notice, $url ) );
		exit;
	}

	/**
	 * The connecting address. Sites behind a proxy can supply the forwarded
	 * address with the induction_records_client_ip filter.
	 */
	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters the IP address stored with a member's confirmation.
		 *
		 * @param string $ip Address from REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'induction_records_client_ip', $ip );

		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	private function user_agent(): string {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return mb_substr( $agent, 0, 255, 'UTF-8' );
	}
}
