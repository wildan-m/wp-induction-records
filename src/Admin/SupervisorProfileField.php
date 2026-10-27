<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

use ForgeBench\InductionRecords\Capabilities;
use WP_Role;
use WP_User;

/**
 * "Can record inductions" checkbox on the user profile.
 *
 * Supervisors are usually members too (WooCommerce customers), so the
 * capability is granted per user instead of through a separate role.
 */
final class SupervisorProfileField {

	private const FIELD = 'induction_records_supervisor';

	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
	}

	public function render( WP_User $user ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$from_role = $this->granted_by_role( $user );
		?>
		<h2><?php esc_html_e( 'Machine inductions', 'induction-records' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Supervisor', 'induction-records' ); ?></th>
				<td>
					<label for="induction-records-supervisor">
						<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" id="induction-records-supervisor" value="1" <?php checked( $user->has_cap( Capabilities::RECORD ) ); ?> <?php disabled( $from_role ); ?>>
						<?php esc_html_e( 'Can record machine inductions', 'induction-records' ); ?>
					</label>
					<?php if ( $from_role ) : ?>
						<p class="description"><?php esc_html_e( 'Granted by this user\'s role.', 'induction-records' ); ?></p>
					<?php endif; ?>
					<?php wp_nonce_field( self::FIELD . '_' . $user->ID, self::FIELD . '_nonce' ); ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param int $user_id Profile being saved.
	 */
	public function save( $user_id ): void {
		$user_id = (int) $user_id;
		$nonce   = isset( $_POST[ self::FIELD . '_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD . '_nonce' ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::FIELD . '_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || $this->granted_by_role( $user ) ) {
			return;
		}

		if ( ! empty( $_POST[ self::FIELD ] ) ) {
			$user->add_cap( Capabilities::RECORD );
		} else {
			$user->remove_cap( Capabilities::RECORD );
		}
	}

	private function granted_by_role( WP_User $user ): bool {
		foreach ( $user->roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role instanceof WP_Role && $role->has_cap( Capabilities::RECORD ) ) {
				return true;
			}
		}

		return false;
	}
}
