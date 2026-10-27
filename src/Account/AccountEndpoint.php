<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Account;

/**
 * "Inductions" tab in the WooCommerce My Account area.
 */
final class AccountEndpoint {

	public const ENDPOINT = 'inductions';

	public function __construct(
		private readonly MemberPage $page
	) {
	}

	public function register(): void {
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( $this, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
	}

	public static function url(): string {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return (string) wc_get_account_endpoint_url( self::ENDPOINT );
		}

		return home_url( '/' );
	}

	/**
	 * WooCommerce registers the rewrite endpoint for every query var it knows about.
	 *
	 * @param array<string, string> $query_vars
	 * @return array<string, string>
	 */
	public function add_query_var( $query_vars ): array {
		$query_vars                   = (array) $query_vars;
		$query_vars[ self::ENDPOINT ] = self::ENDPOINT;

		return $query_vars;
	}

	/**
	 * Adds the tab just before "Log out".
	 *
	 * @param array<string, string> $items
	 * @return array<string, string>
	 */
	public function add_menu_item( $items ): array {
		$items  = (array) $items;
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );

		$items[ self::ENDPOINT ] = __( 'Inductions', 'induction-records' );

		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	public function title(): string {
		return __( 'Inductions', 'induction-records' );
	}

	public function render(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes everything it prints.
		echo $this->page->render( get_current_user_id(), self::url() );
	}
}
