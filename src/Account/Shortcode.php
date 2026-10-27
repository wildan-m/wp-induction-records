<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Account;

use ForgeBench\InductionRecords\Plugin;

/**
 * [induction_records] shortcode for sites without WooCommerce.
 */
final class Shortcode {

	public const TAG = 'induction_records';

	public const STYLE = 'induction-records-member';

	public function __construct(
		private readonly MemberPage $page
	) {
	}

	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	public function render(): string {
		$permalink = (string) get_permalink();

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<p class="induction-records-login">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Log in to see your machine inductions.', 'induction-records' ),
				esc_url( wp_login_url( $permalink ) ),
				esc_html__( 'Log in', 'induction-records' )
			);
		}

		// WooCommerce styles the shared markup itself; without it the page brings its own small stylesheet.
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_enqueue_style( self::STYLE, plugins_url( 'assets/member-page.css', dirname( __DIR__, 2 ) . '/induction-records.php' ), array(), Plugin::VERSION );
		}

		return $this->page->render( get_current_user_id(), $permalink );
	}
}
