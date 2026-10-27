<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Archive;

use ForgeBench\InductionRecords\Capabilities;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Induction\SignOff;

/**
 * Streams archived certificates through admin-post.php.
 *
 * Only the member the certificate belongs to, or a user with
 * manage_inductions, may download it. Links carry a per-user nonce, and the
 * file's location on disk is never exposed.
 */
final class DownloadController {

	public const ACTION = 'induction_records_download';

	public function __construct(
		private readonly SignOffRepository $signoffs,
		private readonly ArchiveStorage $archive
	) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, 'auth_redirect' );
	}

	public static function url( int $signoff_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION,
					'signoff' => $signoff_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $signoff_id
		);
	}

	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is per sign-off and checked right below.
		$signoff_id = isset( $_GET['signoff'] ) ? absint( $_GET['signoff'] ) : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $signoff_id ) ) {
			wp_die(
				esc_html__( 'This download link has expired. Go back, reload the page and try again.', 'induction-records' ),
				esc_html__( 'Link expired', 'induction-records' ),
				array( 'response' => 403 )
			);
		}

		$signoff = $this->signoffs->find( $signoff_id );
		if ( null === $signoff || ! $this->may_download( $signoff ) ) {
			wp_die(
				esc_html__( 'You are not allowed to download this certificate.', 'induction-records' ),
				esc_html__( 'Not allowed', 'induction-records' ),
				array( 'response' => 403 )
			);
		}

		if ( null === $signoff->pdf_path || ! $this->archive->exists( $signoff->pdf_path ) ) {
			wp_die(
				esc_html__( 'The certificate file is missing. Please contact the makerspace team.', 'induction-records' ),
				esc_html__( 'Certificate missing', 'induction-records' ),
				array( 'response' => 404 )
			);
		}

		$this->send( $this->archive->path( $signoff->pdf_path ), sprintf( 'induction-certificate-%s.pdf', $signoff->certificate_id() ) );
	}

	private function may_download( SignOff $signoff ): bool {
		$user_id = get_current_user_id();

		return 0 !== $user_id && ( $user_id === $signoff->member_id || current_user_can( Capabilities::MANAGE ) );
	}

	private function send( string $path, string $filename ): never {
		$size = filesize( $path );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( false !== $size ) {
			header( 'Content-Length: ' . $size );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a private archive file to an authorised user.
		readfile( $path );
		exit;
	}
}
