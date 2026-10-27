<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

use ForgeBench\InductionRecords\Certificate\CertificateContext;
use ForgeBench\InductionRecords\Settings;
use ForgeBench\InductionRecords\Template\MergeFieldRenderer;
use ForgeBench\InductionRecords\Template\RenderResult;

/**
 * Inductions > Settings.
 */
final class SettingsPage {

	public const SLUG = 'induction-records-settings';

	private const GROUP = 'induction_records';

	/**
	 * Guards against a duplicate warning: WordPress sanitises a new option twice.
	 */
	private bool $warned = false;

	public function __construct(
		private readonly Settings $settings,
		private readonly MergeFieldRenderer $renderer
	) {
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section( 'induction_records_certificate', __( 'Certificates', 'induction-records' ), '__return_false', self::SLUG );
		add_settings_field(
			'organisation_name',
			__( 'Organisation name', 'induction-records' ),
			array( $this, 'render_organisation_field' ),
			self::SLUG,
			'induction_records_certificate',
			array( 'label_for' => 'induction-records-organisation' )
		);
		add_settings_field(
			'certificate_template',
			__( 'Certificate text', 'induction-records' ),
			array( $this, 'render_template_field' ),
			self::SLUG,
			'induction_records_certificate',
			array( 'label_for' => 'induction-records-template' )
		);

		add_settings_section( 'induction_records_members', __( 'Members', 'induction-records' ), '__return_false', self::SLUG );
		add_settings_field(
			'member_page_id',
			__( 'Inductions page', 'induction-records' ),
			array( $this, 'render_member_page_field' ),
			self::SLUG,
			'induction_records_members',
			array( 'label_for' => 'induction-records-member-page' )
		);

		add_settings_section( 'induction_records_data', __( 'Data', 'induction-records' ), '__return_false', self::SLUG );
		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Uninstall', 'induction-records' ),
			array( $this, 'render_uninstall_field' ),
			self::SLUG,
			'induction_records_data'
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'induction-records' ), '', array( 'response' => 403 ) );
		}

		$check = $this->check_template( $this->settings->certificate_template() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Induction Records settings', 'induction-records' ); ?></h1>
			<?php if ( ! $check->is_complete() ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $this->problems_text( $check ) ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_organisation_field(): void {
		printf(
			'<input type="text" id="induction-records-organisation" name="%1$s[organisation_name]" value="%2$s" class="regular-text"><p class="description">%3$s</p>',
			esc_attr( Settings::OPTION ),
			esc_attr( $this->settings->organisation_name() ),
			esc_html__( 'Printed in the certificate header. Defaults to the site title.', 'induction-records' )
		);
	}

	public function render_template_field(): void {
		$fields = array_map(
			static fn( string $field ): string => '<code>{{' . esc_html( $field ) . '}}</code>',
			CertificateContext::fields()
		);

		printf(
			'<textarea id="induction-records-template" name="%1$s[certificate_template]" rows="14" class="large-text code">%2$s</textarea>',
			esc_attr( Settings::OPTION ),
			esc_textarea( $this->settings->certificate_template() )
		);
		echo '<p class="description">' . esc_html__( 'HTML with merge fields. Leave empty to restore the default text. The safety briefing is appended on the following pages automatically.', 'induction-records' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Fields:', 'induction-records' ) . ' ' . wp_kses( implode( ' ', $fields ), array( 'code' => array() ) ) . '</p>';
		echo '<p class="description">' . wp_kses(
			sprintf(
				/* translators: 1: list of filter names, 2: date format, for instance {{inducted_at|date:Y-m-d}} */
				__( 'Filters: %1$s. A date format can follow a colon: %2$s.', 'induction-records' ),
				'<code>|date</code> <code>|datetime</code> <code>|upper</code> <code>|lower</code>',
				'<code>{{inducted_at|date:Y-m-d}}</code>'
			),
			array( 'code' => array() )
		) . '</p>';
	}

	public function render_member_page_field(): void {
		wp_dropdown_pages(
			array(
				'name'              => esc_attr( Settings::OPTION . '[member_page_id]' ),
				'id'                => 'induction-records-member-page',
				'selected'          => (int) $this->settings->all()['member_page_id'],
				'show_option_none'  => esc_html__( 'None', 'induction-records' ),
				'option_none_value' => '0',
			)
		);
		echo '<p class="description">' . esc_html__( 'Without WooCommerce, put the [induction_records] shortcode on a page and choose it here so emails can link to it. With WooCommerce, members use the Inductions tab in My Account.', 'induction-records' ) . '</p>';
	}

	public function render_uninstall_field(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[delete_data_on_uninstall]" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( Settings::OPTION ),
			checked( $this->settings->delete_data_on_uninstall(), true, false ),
			esc_html__( 'Delete all induction data when the plugin is deleted', 'induction-records' ),
			esc_html__( 'Leave this unticked to keep briefings, versions, sign-offs and certificate files when the plugin is removed. Induction records are a safety audit trail.', 'induction-records' )
		);
	}

	/**
	 * @param mixed $input Submitted values, already unslashed by options.php.
	 * @return array{organisation_name: string, certificate_template: string, member_page_id: int, delete_data_on_uninstall: bool}
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$template = isset( $input['certificate_template'] ) ? trim( wp_kses_post( (string) $input['certificate_template'] ) ) : '';

		$clean = array(
			'organisation_name'        => isset( $input['organisation_name'] ) ? sanitize_text_field( (string) $input['organisation_name'] ) : '',
			'certificate_template'     => $template,
			'member_page_id'           => isset( $input['member_page_id'] ) ? absint( $input['member_page_id'] ) : 0,
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ),
		);

		if ( '' !== $template && ! $this->warned ) {
			$check = $this->check_template( $template );
			if ( ! $check->is_complete() ) {
				add_settings_error( Settings::OPTION, 'induction-records-template', $this->problems_text( $check ), 'warning' );
				$this->warned = true;
			}
		}

		return $clean;
	}

	private function check_template( string $template ): RenderResult {
		return $this->renderer->render( $template, CertificateContext::preview( $this->settings->organisation_name() ) );
	}

	private function problems_text( RenderResult $result ): string {
		$problems = array();

		if ( array() !== $result->unknown_fields ) {
			$problems[] = sprintf(
				/* translators: %s: comma-separated merge field names */
				__( 'unknown merge fields: %s', 'induction-records' ),
				implode( ', ', $result->unknown_fields )
			);
		}

		if ( array() !== $result->unknown_filters ) {
			$problems[] = sprintf(
				/* translators: %s: comma-separated filter names */
				__( 'unknown filters: %s', 'induction-records' ),
				implode( ', ', $result->unknown_filters )
			);
		}

		return sprintf(
			/* translators: %s: list of problems */
			__( 'The certificate text has %s. These placeholders are printed exactly as typed on certificates.', 'induction-records' ),
			implode( '; ', $problems )
		);
	}
}
