<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

use ForgeBench\InductionRecords\Capabilities;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Induction\InductionException;
use ForgeBench\InductionRecords\Induction\InductionService;
use ForgeBench\InductionRecords\Support\Log;
use Throwable;

/**
 * Inductions > Sign-offs, plus the "Resend to member" action.
 */
final class SignOffsPage {

	public const RESEND_ACTION = 'induction_records_resend';

	public function __construct(
		private readonly SignOffRepository $signoffs,
		private readonly VersionRepository $versions,
		private readonly InductionService $inductions
	) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::RESEND_ACTION, array( $this, 'handle_resend' ) );
	}

	/**
	 * @param array<string, int|string> $args Extra query arguments.
	 */
	public static function url( array $args = array() ): string {
		$args = array_filter( $args );

		return add_query_arg( array_merge( array( 'page' => AdminMenu::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function resend_url( int $signoff_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::RESEND_ACTION,
					'signoff' => $signoff_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::RESEND_ACTION . '_' . $signoff_id
		);
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::RECORD ) ) {
			wp_die( esc_html__( 'You are not allowed to view inductions.', 'induction-records' ), '', array( 'response' => 403 ) );
		}

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new SignOffListTable( $this->signoffs, $this->versions );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Induction sign-offs', 'induction-records' ); ?></h1>
			<a href="<?php echo esc_url( RecordInductionPage::url() ); ?>" class="page-title-action"><?php esc_html_e( 'Record induction', 'induction-records' ); ?></a>
			<hr class="wp-header-end">
			<?php $table->views(); ?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( AdminMenu::SLUG ); ?>">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public function handle_resend(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is per sign-off and checked right below.
		$signoff_id = isset( $_GET['signoff'] ) ? absint( $_GET['signoff'] ) : 0;
		check_admin_referer( self::RESEND_ACTION . '_' . $signoff_id );

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to send certificates.', 'induction-records' ), '', array( 'response' => 403 ) );
		}

		try {
			if ( $this->inductions->resend_certificate( $signoff_id ) ) {
				AdminNotices::add( 'success', __( 'The certificate was emailed to the member again.', 'induction-records' ) );
			} else {
				AdminNotices::add( 'error', __( 'The email could not be sent. Check the site\'s mail settings.', 'induction-records' ) );
			}
		} catch ( InductionException $error ) {
			AdminNotices::add( 'error', $error->getMessage() );
		} catch ( Throwable $error ) {
			Log::error( 'resend certificate', $error );
			AdminNotices::add( 'error', __( 'The certificate could not be sent.', 'induction-records' ) );
		}

		$referer = wp_get_referer();
		wp_safe_redirect( false !== $referer ? $referer : self::url() );
		exit;
	}
}
