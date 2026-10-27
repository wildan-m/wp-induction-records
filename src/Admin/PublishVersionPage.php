<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

use ForgeBench\InductionRecords\Briefing\BriefingPostType;
use ForgeBench\InductionRecords\Briefing\BumpType;
use ForgeBench\InductionRecords\Briefing\PublishOutcome;
use ForgeBench\InductionRecords\Briefing\VersionPolicy;
use ForgeBench\InductionRecords\Briefing\VersionPolicyException;
use ForgeBench\InductionRecords\Briefing\VersionPublisher;
use ForgeBench\InductionRecords\Capabilities;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Support\Formatting;
use ForgeBench\InductionRecords\Support\Log;
use Throwable;
use WP_Post;

/**
 * Inductions > Publish version: snapshots a briefing as a new minor or major
 * version, with a change note.
 */
final class PublishVersionPage {

	public const SLUG   = 'induction-records-publish';
	public const ACTION = 'induction_records_publish_version';

	public function __construct(
		private readonly VersionPublisher $publisher,
		private readonly VersionRepository $versions,
		private readonly SignOffRepository $signoffs
	) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public static function url( int $briefing_id = 0 ): string {
		$args = array( 'page' => self::SLUG );
		if ( $briefing_id > 0 ) {
			$args['briefing'] = $briefing_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to publish briefing versions.', 'induction-records' ), '', array( 'response' => 403 ) );
		}

		$briefings = get_posts(
			array(
				'post_type'   => BriefingPostType::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which briefing to show.
		$selected_id = isset( $_GET['briefing'] ) ? absint( $_GET['briefing'] ) : 0;
		$selected    = null;
		foreach ( $briefings as $briefing ) {
			if ( $briefing->ID === $selected_id ) {
				$selected = $briefing;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Publish briefing version', 'induction-records' ); ?></h1>
			<p><?php esc_html_e( 'Publishing takes a fixed copy of the saved briefing. A minor version fixes wording and keeps every induction valid. A major version changes the safety content: everyone inducted on an earlier major version is flagged for re-induction.', 'induction-records' ); ?></p>

			<?php if ( array() === $briefings ) : ?>
				<p><?php esc_html_e( 'Add a machine briefing first.', 'induction-records' ); ?></p>
			<?php else : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
					<label for="induction-records-briefing"><?php esc_html_e( 'Machine', 'induction-records' ); ?></label>
					<select name="briefing" id="induction-records-briefing">
						<option value="0"><?php esc_html_e( 'Choose a machine', 'induction-records' ); ?></option>
						<?php foreach ( $briefings as $briefing ) : ?>
							<option value="<?php echo esc_attr( (string) $briefing->ID ); ?>" <?php selected( $selected_id, $briefing->ID ); ?>><?php echo esc_html( get_the_title( $briefing ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Select', 'induction-records' ), 'secondary', '', false ); ?>
				</form>
			<?php endif; ?>

			<?php
			if ( null !== $selected ) {
				$this->render_form( $selected );
			}
			?>
		</div>
		<?php
	}

	private function render_form( WP_Post $briefing ): void {
		$current = $this->versions->latest_for_briefing( $briefing->ID );
		$changed = $this->publisher->has_unpublished_changes( $briefing );
		$active  = $this->signoffs->count_active_on_briefing( $briefing->ID );
		?>
		<h2><?php echo esc_html( get_the_title( $briefing ) ); ?></h2>
		<p>
			<?php
			if ( null === $current ) {
				esc_html_e( 'Nothing published yet. This will publish version 1.0.', 'induction-records' );
			} else {
				printf(
					/* translators: 1: version number, 2: date */
					esc_html__( 'Current version: %1$s, published %2$s.', 'induction-records' ),
					esc_html( $current->version->to_string() ),
					esc_html( Formatting::date( $current->published_at ) )
				);
				echo ' ';
				if ( $changed ) {
					esc_html_e( 'The saved briefing differs from it.', 'induction-records' );
				} else {
					esc_html_e( 'The saved briefing is identical to it.', 'induction-records' );
				}
			}
			?>
			<a href="<?php echo esc_url( (string) get_edit_post_link( $briefing->ID ) ); ?>"><?php esc_html_e( 'Edit briefing', 'induction-records' ); ?></a>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="briefing" value="<?php echo esc_attr( (string) $briefing->ID ); ?>">
			<?php wp_nonce_field( self::ACTION ); ?>
			<table class="form-table" role="presentation">
				<?php if ( null !== $current ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Type of change', 'induction-records' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="bump" value="<?php echo esc_attr( BumpType::Minor->value ); ?>" <?php checked( $changed ); ?> <?php disabled( ! $changed ); ?>>
									<?php
									printf(
										/* translators: %s: next minor version number */
										esc_html__( 'Minor (%s): wording fix, existing inductions stay valid', 'induction-records' ),
										esc_html( $current->version->bump( BumpType::Minor )->to_string() )
									);
									?>
								</label>
								<br>
								<label>
									<input type="radio" name="bump" value="<?php echo esc_attr( BumpType::Major->value ); ?>" <?php checked( ! $changed ); ?>>
									<?php
									printf(
										/* translators: 1: next major version number, 2: number of inductions */
										esc_html( _n( 'Major (%1$s): safety content changed, %2$s active induction will need re-induction', 'Major (%1$s): safety content changed, %2$s active inductions will need re-induction', $active, 'induction-records' ) ),
										esc_html( $current->version->bump( BumpType::Major )->to_string() ),
										esc_html( number_format_i18n( $active ) )
									);
									?>
								</label>
							</fieldset>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><label for="induction-records-change-note"><?php esc_html_e( 'Change note', 'induction-records' ); ?></label></th>
					<td>
						<textarea id="induction-records-change-note" name="change_note" rows="4" class="large-text" maxlength="<?php echo esc_attr( (string) VersionPolicy::MAX_NOTE_LENGTH ); ?>" required></textarea>
						<p class="description"><?php esc_html_e( 'Kept in the version history and shown to members who need re-induction.', 'induction-records' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Publish version', 'induction-records' ) ); ?>
		</form>
		<?php
	}

	public function handle(): void {
		check_admin_referer( self::ACTION );

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to publish briefing versions.', 'induction-records' ), '', array( 'response' => 403 ) );
		}

		$briefing_id = isset( $_POST['briefing'] ) ? absint( $_POST['briefing'] ) : 0;
		$bump        = BumpType::tryFrom( isset( $_POST['bump'] ) ? sanitize_key( wp_unslash( $_POST['bump'] ) ) : '' ) ?? BumpType::Minor;
		$note        = isset( $_POST['change_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['change_note'] ) ) : '';

		try {
			AdminNotices::add( 'success', $this->published_message( $this->publisher->publish( $briefing_id, $bump, $note, get_current_user_id() ) ) );
		} catch ( VersionPolicyException $error ) {
			AdminNotices::add( 'error', $this->policy_message( $error ) );
		} catch ( Throwable $error ) {
			Log::error( 'publish briefing version', $error );
			AdminNotices::add( 'error', __( 'The version could not be published. Please try again.', 'induction-records' ) );
		}

		wp_safe_redirect( self::url( $briefing_id ) );
		exit;
	}

	private function published_message( PublishOutcome $outcome ): string {
		$message = sprintf(
			/* translators: 1: machine name, 2: version number */
			__( '%1$s version %2$s published.', 'induction-records' ),
			$outcome->version->title,
			$outcome->version->version->to_string()
		);

		if ( $outcome->flagged_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %s: number of inductions */
				_n( '%s induction now needs re-induction.', '%s inductions now need re-induction.', $outcome->flagged_count, 'induction-records' ),
				number_format_i18n( $outcome->flagged_count )
			);
		}

		return $message;
	}

	private function policy_message( VersionPolicyException $error ): string {
		return match ( $error->reason ) {
			VersionPolicyException::UNCHANGED     => __( 'The briefing has not changed since the current version. Save your edits first, or publish a major version to require re-induction on unchanged text.', 'induction-records' ),
			VersionPolicyException::MISSING_NOTE  => __( 'Please describe the change in the change note.', 'induction-records' ),
			VersionPolicyException::NOTE_TOO_LONG => __( 'The change note is too long.', 'induction-records' ),
			VersionPolicyException::EMPTY_CONTENT => __( 'The briefing needs a title and some text before it can be published.', 'induction-records' ),
			default                               => $error->getMessage(),
		};
	}
}
