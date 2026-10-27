<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Admin;

use ForgeBench\InductionRecords\Archive\DownloadController;
use ForgeBench\InductionRecords\Briefing\BriefingPostType;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Capabilities;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Induction\SignOff;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use ForgeBench\InductionRecords\Support\Formatting;
use WP_List_Table;
use WP_User;

/**
 * Admin list of sign-offs, filterable by machine and status.
 *
 * The filters only select what is displayed, so they are read from the query
 * string without a nonce, like the core list tables.
 */
final class SignOffListTable extends WP_List_Table {

	private const PER_PAGE = 25;

	/**
	 * @var array<int, BriefingVersion> Versions of the listed sign-offs, keyed by id.
	 */
	private array $listed_versions = array();

	public function __construct(
		private readonly SignOffRepository $signoffs,
		private readonly VersionRepository $versions
	) {
		parent::__construct(
			array(
				'singular' => 'signoff',
				'plural'   => 'signoffs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'certificate' => __( 'Certificate', 'induction-records' ),
			'member'      => __( 'Member', 'induction-records' ),
			'machine'     => __( 'Machine', 'induction-records' ),
			'version'     => __( 'Version', 'induction-records' ),
			'supervisor'  => __( 'Supervisor', 'induction-records' ),
			'inducted_at' => __( 'Inducted', 'induction-records' ),
			'confirmed'   => __( 'Confirmed by member', 'induction-records' ),
			'status'      => __( 'Status', 'induction-records' ),
		);
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'inducted_at' => array( 'inducted_at', true ),
		);
	}

	protected function get_default_primary_column_name() {
		return 'certificate';
	}

	public function prepare_items(): void {
		$result = $this->signoffs->search(
			$this->machine_filter(),
			$this->status_filter(),
			self::PER_PAGE,
			$this->get_pagenum(),
			$this->order()
		);

		$this->items           = $result['items'];
		$this->listed_versions = $this->versions->find_many(
			array_map( static fn( SignOff $signoff ): int => $signoff->briefing_version_id, $result['items'] )
		);

		$user_ids = array();
		foreach ( $result['items'] as $signoff ) {
			$user_ids[] = $signoff->member_id;
			$user_ids[] = $signoff->supervisor_id;
		}
		cache_users( array_values( array_unique( $user_ids ) ) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'certificate' );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => self::PER_PAGE,
			)
		);
	}

	/**
	 * Status links above the table.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$counts  = $this->signoffs->count_by_status( $this->machine_filter() );
		$current = $this->status_filter();
		$base    = SignOffsPage::url( array( 'machine' => $this->machine_filter() ) );

		$views = array(
			'all' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $base ),
				'' === $current ? ' class="current" aria-current="page"' : '',
				esc_html__( 'All', 'induction-records' ),
				esc_html( number_format_i18n( array_sum( $counts ) ) )
			),
		);

		foreach ( SignOffStatus::cases() as $status ) {
			$views[ $status->value ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( add_query_arg( 'status', $status->value, $base ) ),
				$status->value === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $status->label() ),
				esc_html( number_format_i18n( $counts[ $status->value ] ?? 0 ) )
			);
		}

		return $views;
	}

	/**
	 * Machine filter above the table.
	 *
	 * @param string $which Top or bottom navigation.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$machines = get_posts(
			array(
				'post_type'   => BriefingPostType::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="induction-records-machine-filter"><?php esc_html_e( 'Filter by machine', 'induction-records' ); ?></label>
			<select name="machine" id="induction-records-machine-filter">
				<option value="0"><?php esc_html_e( 'All machines', 'induction-records' ); ?></option>
				<?php foreach ( $machines as $machine ) : ?>
					<option value="<?php echo esc_attr( (string) $machine->ID ); ?>" <?php selected( $this->machine_filter(), $machine->ID ); ?>><?php echo esc_html( get_the_title( $machine ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( '' !== $this->status_filter() ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $this->status_filter() ); ?>">
			<?php endif; ?>
			<?php submit_button( __( 'Filter', 'induction-records' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function no_items(): void {
		esc_html_e( 'No inductions match these filters.', 'induction-records' );
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_certificate( $item ): string {
		$reference = '<strong>' . esc_html( $item->certificate_id() ) . '</strong>';

		if ( $item->is_legacy() ) {
			$reference .= '<br><span class="description">' . sprintf(
				/* translators: %s: reference of the paper record */
				esc_html__( 'Paper record %s', 'induction-records' ),
				esc_html( (string) $item->legacy_ref )
			) . '</span>';
		}

		return $reference;
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_member( $item ): string {
		$member = get_userdata( $item->member_id );
		if ( ! $member instanceof WP_User ) {
			return esc_html__( 'Deleted user', 'induction-records' );
		}

		$name = esc_html( $member->display_name );
		if ( current_user_can( 'edit_user', $member->ID ) ) {
			$name = sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_edit_user_link( $member->ID ) ), $name );
		}

		return $name . '<br><span class="description">' . esc_html( $member->user_email ) . '</span>';
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_machine( $item ): string {
		$version = $this->listed_versions[ $item->briefing_version_id ] ?? null;

		return null === $version ? '&mdash;' : esc_html( $version->title );
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_version( $item ): string {
		$version = $this->listed_versions[ $item->briefing_version_id ] ?? null;

		return null === $version ? '&mdash;' : esc_html( $version->version->to_string() );
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_supervisor( $item ): string {
		return esc_html( Formatting::person( $item->supervisor_id ) );
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_inducted_at( $item ): string {
		return esc_html( Formatting::date( $item->inducted_at ) );
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_confirmed( $item ): string {
		if ( null === $item->member_confirmed_at ) {
			return '&mdash;';
		}

		return esc_html( Formatting::datetime( $item->member_confirmed_at ) );
	}

	/**
	 * @param SignOff $item Row.
	 */
	public function column_status( $item ): string {
		return esc_html( $item->status->label() );
	}

	/**
	 * @param SignOff $item        Row.
	 * @param string  $column_name Column being rendered.
	 * @param string  $primary     Primary column name.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ): string {
		if ( $primary !== $column_name || ! $item->has_document() || ! current_user_can( Capabilities::MANAGE ) ) {
			return '';
		}

		$actions = array(
			'download' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( DownloadController::url( $item->id ) ),
				esc_html__( 'Download certificate', 'induction-records' )
			),
			'resend'   => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( SignOffsPage::resend_url( $item->id ) ),
				esc_html__( 'Resend to member', 'induction-records' )
			),
		);

		return $this->row_actions( $actions );
	}

	private function machine_filter(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
		return isset( $_GET['machine'] ) ? absint( $_GET['machine'] ) : 0;
	}

	private function status_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		return null !== SignOffStatus::tryFrom( $status ) ? $status : '';
	}

	private function order(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
		$order = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '';

		return 'asc' === $order ? 'ASC' : 'DESC';
	}
}
