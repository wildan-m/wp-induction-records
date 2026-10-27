<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Support;

use DateTimeInterface;
use WP_User;

/**
 * Display helpers that follow the site's date settings.
 */
final class Formatting {

	public static function date( ?DateTimeInterface $date ): string {
		if ( null === $date ) {
			return '';
		}

		return (string) wp_date( (string) get_option( 'date_format' ), $date->getTimestamp() );
	}

	public static function datetime( ?DateTimeInterface $date ): string {
		if ( null === $date ) {
			return '';
		}

		$format = trim( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		return (string) wp_date( $format, $date->getTimestamp() );
	}

	public static function person( int $user_id ): string {
		$user = get_userdata( $user_id );

		return $user instanceof WP_User ? $user->display_name : __( 'Unknown user', 'induction-records' );
	}
}
