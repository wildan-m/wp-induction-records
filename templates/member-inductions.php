<?php
/**
 * Member inductions: My Account "Inductions" tab and [induction_records] shortcode.
 *
 * Override by copying this file to yourtheme/induction-records/member-inductions.php.
 *
 * @package ForgeBench\InductionRecords
 *
 * @var \ForgeBench\InductionRecords\Account\MemberPage         $view
 * @var \ForgeBench\InductionRecords\Induction\InductionSummary $summary
 * @var array{type: string, text: string}                      $notice
 * @var string                                                  $return_url
 */

use ForgeBench\InductionRecords\Account\ConfirmationController;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to MemberPage::render().
?>
<div class="induction-records">
	<?php if ( '' !== $notice['text'] ) : ?>
		<div class="<?php echo esc_attr( 'success' === $notice['type'] ? 'woocommerce-message' : 'woocommerce-error' ); ?> induction-records__notice" role="status">
			<?php echo esc_html( $notice['text'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $summary->is_empty() ) : ?>
		<p><?php esc_html_e( 'You have no machine inductions yet. Ask a supervisor to induct you before using a machine.', 'induction-records' ); ?></p>
	<?php endif; ?>

	<?php if ( array() !== $summary->awaiting_confirmation ) : ?>
		<h3><?php esc_html_e( 'Waiting for your confirmation', 'induction-records' ); ?></h3>
		<?php foreach ( $summary->awaiting_confirmation as $entry ) : ?>
			<section class="induction-records__pending">
				<h4><?php echo esc_html( $entry->machine_name() ); ?></h4>
				<p>
					<?php
					printf(
						/* translators: 1: supervisor name, 2: induction date, 3: briefing version */
						esc_html__( 'Inducted by %1$s on %2$s, briefing version %3$s.', 'induction-records' ),
						esc_html( $view->person( $entry->signoff->supervisor_id ) ),
						esc_html( $view->date( $entry->signoff->inducted_at ) ),
						esc_html( $entry->version->version->to_string() )
					);
					?>
				</p>
				<details>
					<summary><?php esc_html_e( 'Read the safety briefing', 'induction-records' ); ?></summary>
					<div class="induction-records__briefing"><?php echo wp_kses_post( $entry->version->body_html ); ?></div>
				</details>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( ConfirmationController::ACTION ); ?>">
					<input type="hidden" name="signoff" value="<?php echo esc_attr( (string) $entry->signoff->id ); ?>">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $return_url ); ?>">
					<?php wp_nonce_field( ConfirmationController::ACTION . '_' . $entry->signoff->id ); ?>
					<p>
						<label>
							<input type="checkbox" name="acknowledge" value="1" required>
							<?php esc_html_e( 'I received this safety briefing from the supervisor named above and I understood it.', 'induction-records' ); ?>
						</label>
					</p>
					<p><button type="submit" class="button"><?php esc_html_e( 'Confirm induction', 'induction-records' ); ?></button></p>
				</form>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( array() !== $summary->valid ) : ?>
		<h3><?php esc_html_e( 'Current inductions', 'induction-records' ); ?></h3>
		<table class="shop_table shop_table_responsive induction-records__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Machine', 'induction-records' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Briefing version', 'induction-records' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Inducted', 'induction-records' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Certificate', 'induction-records' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $summary->valid as $entry ) : ?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Machine', 'induction-records' ); ?>"><?php echo esc_html( $entry->machine_name() ); ?></td>
						<td data-title="<?php esc_attr_e( 'Briefing version', 'induction-records' ); ?>"><?php echo esc_html( $entry->version->version->to_string() ); ?></td>
						<td data-title="<?php esc_attr_e( 'Inducted', 'induction-records' ); ?>"><?php echo esc_html( $view->date( $entry->signoff->inducted_at ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Certificate', 'induction-records' ); ?>">
							<?php if ( $entry->signoff->has_document() ) : ?>
								<a href="<?php echo esc_url( $view->download_url( $entry->signoff ) ); ?>"><?php esc_html_e( 'Download', 'induction-records' ); ?></a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( array() !== $summary->reinduction_required ) : ?>
		<h3><?php esc_html_e( 'Re-induction required', 'induction-records' ); ?></h3>
		<p><?php esc_html_e( 'The safety briefing for these machines has changed. Do not use them until a supervisor has inducted you again.', 'induction-records' ); ?></p>
		<table class="shop_table shop_table_responsive induction-records__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Machine', 'induction-records' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Your version', 'induction-records' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Current version', 'induction-records' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What changed', 'induction-records' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Previous certificate', 'induction-records' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $summary->reinduction_required as $entry ) : ?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Machine', 'induction-records' ); ?>"><?php echo esc_html( $entry->machine_name() ); ?></td>
						<td data-title="<?php esc_attr_e( 'Your version', 'induction-records' ); ?>"><?php echo esc_html( $entry->version->version->to_string() ); ?></td>
						<td data-title="<?php esc_attr_e( 'Current version', 'induction-records' ); ?>"><?php echo esc_html( null !== $entry->current_version ? $entry->current_version->version->to_string() : '' ); ?></td>
						<td data-title="<?php esc_attr_e( 'What changed', 'induction-records' ); ?>"><?php echo esc_html( null !== $entry->current_version ? $entry->current_version->change_note : '' ); ?></td>
						<td data-title="<?php esc_attr_e( 'Previous certificate', 'induction-records' ); ?>">
							<?php if ( $entry->signoff->has_document() ) : ?>
								<a href="<?php echo esc_url( $view->download_url( $entry->signoff ) ); ?>"><?php esc_html_e( 'Download', 'induction-records' ); ?></a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( array() !== $summary->history ) : ?>
		<details class="induction-records__history">
			<summary><?php esc_html_e( 'Earlier inductions', 'induction-records' ); ?></summary>
			<table class="shop_table shop_table_responsive induction-records__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Machine', 'induction-records' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Briefing version', 'induction-records' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Inducted', 'induction-records' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Certificate', 'induction-records' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $summary->history as $entry ) : ?>
						<tr>
							<td data-title="<?php esc_attr_e( 'Machine', 'induction-records' ); ?>"><?php echo esc_html( $entry->machine_name() ); ?></td>
							<td data-title="<?php esc_attr_e( 'Briefing version', 'induction-records' ); ?>"><?php echo esc_html( $entry->version->version->to_string() ); ?></td>
							<td data-title="<?php esc_attr_e( 'Inducted', 'induction-records' ); ?>"><?php echo esc_html( $view->date( $entry->signoff->inducted_at ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Certificate', 'induction-records' ); ?>">
								<?php if ( $entry->signoff->has_document() ) : ?>
									<a href="<?php echo esc_url( $view->download_url( $entry->signoff ) ); ?>"><?php esc_html_e( 'Download', 'induction-records' ); ?></a>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
	<?php endif; ?>
</div>
