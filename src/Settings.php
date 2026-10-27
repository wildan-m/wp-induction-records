<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords;

use ForgeBench\InductionRecords\Account\AccountEndpoint;

/**
 * Plugin settings, stored as one array option.
 */
final class Settings {

	public const OPTION = 'induction_records_settings';

	/**
	 * Stored settings merged with defaults.
	 *
	 * @return array{organisation_name: string, certificate_template: string, member_page_id: int, delete_data_on_uninstall: bool}
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$organisation = isset( $stored['organisation_name'] ) ? trim( (string) $stored['organisation_name'] ) : '';
		$template     = isset( $stored['certificate_template'] ) ? trim( (string) $stored['certificate_template'] ) : '';

		return array(
			'organisation_name'        => '' !== $organisation ? $organisation : (string) get_bloginfo( 'name' ),
			'certificate_template'     => '' !== $template ? $template : self::default_certificate_template(),
			'member_page_id'           => isset( $stored['member_page_id'] ) ? (int) $stored['member_page_id'] : 0,
			'delete_data_on_uninstall' => ! empty( $stored['delete_data_on_uninstall'] ),
		);
	}

	/**
	 * Name printed in the certificate header, such as "Forge & Bench Makerspace".
	 */
	public function organisation_name(): string {
		return $this->all()['organisation_name'];
	}

	public function certificate_template(): string {
		return $this->all()['certificate_template'];
	}

	public function delete_data_on_uninstall(): bool {
		return $this->all()['delete_data_on_uninstall'];
	}

	/**
	 * Where members find their inductions: the My Account tab with WooCommerce,
	 * otherwise the page chosen in the settings (it should hold the shortcode).
	 */
	public function member_page_url(): string {
		$url = home_url( '/' );

		if ( class_exists( 'WooCommerce' ) ) {
			$url = AccountEndpoint::url();
		} elseif ( $this->all()['member_page_id'] > 0 ) {
			$permalink = get_permalink( $this->all()['member_page_id'] );
			if ( is_string( $permalink ) ) {
				$url = $permalink;
			}
		}

		/**
		 * Filters the URL of the member inductions page used in emails.
		 *
		 * @param string $url Page URL.
		 */
		return (string) apply_filters( 'induction_records_member_page_url', $url );
	}

	/**
	 * Default certificate text, assembled from translatable strings.
	 */
	public static function default_certificate_template(): string {
		$rows = array(
			array( esc_html__( 'Machine', 'induction-records' ), '{{machine.name}}' ),
			array( esc_html__( 'Inducted on', 'induction-records' ), '{{inducted_at|date}}' ),
			array( esc_html__( 'Supervisor', 'induction-records' ), '{{supervisor.display_name}}' ),
			array( esc_html__( 'Briefing version', 'induction-records' ), '{{briefing.version}}' ),
			array( esc_html__( 'Briefing checksum (SHA-256)', 'induction-records' ), '{{briefing.checksum}}' ),
			array( esc_html__( 'Confirmed by the member', 'induction-records' ), '{{confirmed_at|datetime}}' ),
		);

		$table = '';
		foreach ( $rows as $row ) {
			$table .= '<tr><th>' . $row[0] . '</th><td>' . $row[1] . "</td></tr>\n";
		}

		return '<h1>' . esc_html__( 'Certificate of machine induction', 'induction-records' ) . "</h1>\n"
			. '<p>' . sprintf(
				/* translators: 1: member name, 2: machine name. Both are merge fields; keep them as they are. */
				esc_html__( 'This certifies that %1$s has completed the safety induction for the %2$s.', 'induction-records' ),
				'<strong>{{member.display_name}}</strong>',
				'<strong>{{machine.name}}</strong>'
			) . "</p>\n"
			. "<table>\n" . $table . "</table>\n"
			. '<p>' . esc_html__( 'The member confirmed that they received and understood the safety briefing reproduced on the following pages.', 'induction-records' ) . '</p>';
	}
}
