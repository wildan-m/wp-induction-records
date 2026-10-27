<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

use ForgeBench\InductionRecords\Admin\AdminMenu;
use ForgeBench\InductionRecords\Admin\PublishVersionPage;
use ForgeBench\InductionRecords\Capabilities;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Support\Formatting;
use WP_Post;

/**
 * The "Machine briefing" post type.
 *
 * The post title is the machine name, the slug identifies the machine in the
 * paper-records import, and the content is the working copy of the safety
 * briefing. Members are only ever inducted on versions published from it.
 */
final class BriefingPostType {

	public const POST_TYPE = 'induction_briefing';

	public function __construct(
		private readonly VersionRepository $versions
	) {
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_filter( 'pre_delete_post', array( $this, 'protect_versioned_briefing' ), 10, 2 );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Machine briefings', 'induction-records' ),
					'singular_name'      => __( 'Machine briefing', 'induction-records' ),
					'add_new'            => __( 'Add briefing', 'induction-records' ),
					'add_new_item'       => __( 'Add machine briefing', 'induction-records' ),
					'edit_item'          => __( 'Edit machine briefing', 'induction-records' ),
					'new_item'           => __( 'New machine briefing', 'induction-records' ),
					'search_items'       => __( 'Search machine briefings', 'induction-records' ),
					'not_found'          => __( 'No machine briefings found.', 'induction-records' ),
					'not_found_in_trash' => __( 'No machine briefings found in Trash.', 'induction-records' ),
					'all_items'          => __( 'Machine briefings', 'induction-records' ),
					'menu_name'          => __( 'Machine briefings', 'induction-records' ),
				),
				'description'         => __( 'Safety briefing for one machine.', 'induction-records' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => AdminMenu::SLUG,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'capabilities'        => self::capabilities(),
				'map_meta_cap'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'has_archive'         => false,
			)
		);
	}

	public function add_meta_box(): void {
		add_meta_box(
			'induction-records-versions',
			__( 'Published versions', 'induction-records' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render_meta_box( WP_Post $post ): void {
		$versions = array_reverse( $this->versions->all_for_briefing( $post->ID ) );

		echo '<p>' . esc_html__( 'Members are inducted on published versions. Saving here only changes the working copy.', 'induction-records' ) . '</p>';

		if ( array() === $versions ) {
			echo '<p><strong>' . esc_html__( 'No version published yet.', 'induction-records' ) . '</strong></p>';
		} else {
			echo '<ul>';
			foreach ( array_slice( $versions, 0, 10 ) as $version ) {
				printf(
					'<li><strong>%1$s</strong> &middot; %2$s<br><span class="description">%3$s</span></li>',
					esc_html( $version->version->to_string() ),
					esc_html( Formatting::date( $version->published_at ) ),
					esc_html( $version->change_note )
				);
			}
			echo '</ul>';
		}

		if ( 'auto-draft' !== $post->post_status ) {
			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url( PublishVersionPage::url( $post->ID ) ),
				esc_html__( 'Publish new version', 'induction-records' )
			);
		}
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function add_columns( array $columns ): array {
		$with_ours = array();

		foreach ( $columns as $key => $label ) {
			$with_ours[ $key ] = $label;

			if ( 'title' === $key ) {
				$with_ours['induction_records_slug']    = __( 'Machine slug', 'induction-records' );
				$with_ours['induction_records_version'] = __( 'Current version', 'induction-records' );
			}
		}

		return $with_ours;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public function render_column( $column, $post_id ): void {
		if ( 'induction_records_slug' === $column ) {
			echo '<code>' . esc_html( (string) get_post_field( 'post_name', (int) $post_id ) ) . '</code>';
		} elseif ( 'induction_records_version' === $column ) {
			$current = $this->versions->latest_for_briefing( (int) $post_id );
			echo null === $current ? '&mdash;' : esc_html( $current->version->to_string() );
		}
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function add_row_action( $actions, WP_Post $post ): array {
		$actions = (array) $actions;

		if ( self::POST_TYPE === $post->post_type && current_user_can( Capabilities::MANAGE ) ) {
			$actions['induction_records_publish'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( PublishVersionPage::url( $post->ID ) ),
				esc_html__( 'Publish new version', 'induction-records' )
			);
		}

		return $actions;
	}

	/**
	 * Sign-offs point at published versions, so a briefing that has any can be
	 * trashed but not deleted permanently.
	 *
	 * @param WP_Post|false|null $delete Short-circuit value from earlier filters.
	 * @param WP_Post            $post   Post about to be deleted.
	 * @return WP_Post|false|null
	 */
	public function protect_versioned_briefing( $delete, WP_Post $post ) {
		if ( self::POST_TYPE === $post->post_type && null !== $this->versions->latest_for_briefing( $post->ID ) ) {
			return false;
		}

		return $delete;
	}

	/**
	 * Every briefing capability maps to manage_inductions.
	 *
	 * @return array<string, string>
	 */
	private static function capabilities(): array {
		$capabilities = array(
			'edit_post',
			'read_post',
			'delete_post',
			'edit_posts',
			'edit_others_posts',
			'edit_private_posts',
			'edit_published_posts',
			'delete_posts',
			'delete_others_posts',
			'delete_private_posts',
			'delete_published_posts',
			'publish_posts',
			'read_private_posts',
			'create_posts',
		);

		return array_fill_keys( $capabilities, Capabilities::MANAGE );
	}
}
