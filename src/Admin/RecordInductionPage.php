<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

use DateTimeImmutable;
use ForgeBench\InductionRecords\Briefing\BriefingPostType;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Capabilities;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Induction\InductionException;
use ForgeBench\InductionRecords\Induction\InductionService;
use ForgeBench\InductionRecords\Support\Log;
use Throwable;
use WP_Post;
use WP_User;

/**
 * Inductions > Record induction: the form a supervisor fills in after giving
 * an induction at the machine.
 */
final class RecordInductionPage {

	public const SLUG   = 'induction-records-record';
	public const ACTION = 'induction_records_record';

	public function __construct(
		private readonly InductionService $inductions,
		private readonly VersionRepository $versions
	) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public static function url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::RECORD ) ) {
			wp_die( esc_html__( 'You are not allowed to record inductions.', 'induction-records' ), '', array( 'response' => 403 ) );
		}

		$machines = $this->machines();
		$now      = new DateTimeImmutable( 'now', wp_timezone() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Record induction', 'induction-records' ); ?></h1>
			<p><?php esc_html_e( 'Record an induction you have just given. The member then confirms it from their inductions page, which issues their certificate.', 'induction-records' ); ?></p>

			<?php if ( array() === $machines ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'No machine has a published briefing version yet. Publish one before recording inductions.', 'induction-records' ); ?></p>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="induction-records-member"><?php esc_html_e( 'Member', 'induction-records' ); ?></label></th>
							<td>
								<input type="text" id="induction-records-member" name="member" class="regular-text" autocomplete="off" required>
								<p class="description"><?php esc_html_e( 'Email address or username of the member you inducted.', 'induction-records' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="induction-records-machine"><?php esc_html_e( 'Machine', 'induction-records' ); ?></label></th>
							<td>
								<select id="induction-records-machine" name="briefing" required>
									<option value=""><?php esc_html_e( 'Choose a machine', 'induction-records' ); ?></option>
									<?php foreach ( $machines as $machine ) : ?>
										<option value="<?php echo esc_attr( (string) $machine['id'] ); ?>">
											<?php
											printf(
												/* translators: 1: machine name, 2: briefing version */
												esc_html__( '%1$s (briefing %2$s)', 'induction-records' ),
												esc_html( $machine['title'] ),
												esc_html( $machine['version']->version->to_string() )
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="induction-records-inducted-at"><?php esc_html_e( 'Inducted at', 'induction-records' ); ?></label></th>
							<td>
								<input type="datetime-local" id="induction-records-inducted-at" name="inducted_at" value="<?php echo esc_attr( $now->format( 'Y-m-d\TH:i' ) ); ?>" max="<?php echo esc_attr( $now->format( 'Y-m-d\TH:i' ) ); ?>" required>
								<p class="description"><?php esc_html_e( 'Site time zone.', 'induction-records' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Supervisor', 'induction-records' ); ?></th>
							<td><?php echo esc_html( wp_get_current_user()->display_name ); ?></td>
						</tr>
					</table>
					<?php submit_button( __( 'Record induction', 'induction-records' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle(): void {
		check_admin_referer( self::ACTION );

		if ( ! current_user_can( Capabilities::RECORD ) ) {
			wp_die( esc_html__( 'You are not allowed to record inductions.', 'induction-records' ), '', array( 'response' => 403 ) );
		}

		$member_input   = isset( $_POST['member'] ) ? sanitize_text_field( wp_unslash( $_POST['member'] ) ) : '';
		$briefing_id    = isset( $_POST['briefing'] ) ? absint( $_POST['briefing'] ) : 0;
		$inducted_input = isset( $_POST['inducted_at'] ) ? sanitize_text_field( wp_unslash( $_POST['inducted_at'] ) ) : '';

		$member      = $this->find_member( $member_input );
		$inducted_at = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $inducted_input, wp_timezone() );

		if ( null === $member ) {
			AdminNotices::add( 'error', __( 'No member account matches that email address or username.', 'induction-records' ) );
		} elseif ( false === $inducted_at ) {
			AdminNotices::add( 'error', __( 'Enter the date and time of the induction.', 'induction-records' ) );
		} else {
			try {
				$signoff = $this->inductions->record( $member->ID, $briefing_id, get_current_user_id(), $inducted_at );
				AdminNotices::add(
					'success',
					sprintf(
						/* translators: 1: certificate reference, 2: member name */
						__( 'Induction %1$s recorded for %2$s. They have been emailed and need to confirm it from their inductions page.', 'induction-records' ),
						$signoff->certificate_id(),
						$member->display_name
					)
				);
			} catch ( InductionException $error ) {
				AdminNotices::add( 'error', $error->getMessage() );
			} catch ( Throwable $error ) {
				Log::error( 'record induction', $error );
				AdminNotices::add( 'error', __( 'The induction could not be recorded. Please try again.', 'induction-records' ) );
			}
		}

		wp_safe_redirect( self::url() );
		exit;
	}

	private function find_member( string $input ): ?WP_User {
		if ( '' === $input ) {
			return null;
		}

		$user = is_email( $input ) ? get_user_by( 'email', $input ) : get_user_by( 'login', $input );

		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Active machines that have a published version.
	 *
	 * @return list<array{id: int, title: string, version: BriefingVersion}>
	 */
	private function machines(): array {
		$posts = get_posts(
			array(
				'post_type'   => BriefingPostType::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$current = $this->versions->latest_for_briefings( array_map( static fn( WP_Post $post ): int => $post->ID, $posts ) );

		$machines = array();
		foreach ( $posts as $post ) {
			if ( isset( $current[ $post->ID ] ) ) {
				$machines[] = array(
					'id'      => $post->ID,
					'title'   => get_the_title( $post ),
					'version' => $current[ $post->ID ],
				);
			}
		}

		return $machines;
	}
}
