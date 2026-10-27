<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Database;

use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use RuntimeException;
use stdClass;
use wpdb;

/**
 * Reads and appends briefing versions.
 *
 * There is deliberately no update or delete method: a published version is
 * never changed in place, because sign-offs and certificates point at it.
 */
final class VersionRepository {

	/**
	 * @var array<int, BriefingVersion|null> Latest version per briefing, for this request.
	 */
	private array $latest = array();

	public function __construct(
		private readonly wpdb $db,
		private readonly string $table
	) {
	}

	public static function create(): self {
		global $wpdb;

		return new self( $wpdb, Schema::versions_table() );
	}

	/**
	 * @throws RuntimeException When the row cannot be written, for instance because
	 *                          the same version number was published concurrently.
	 */
	public function insert( BriefingVersion $version ): BriefingVersion {
		$inserted = $this->db->insert(
			$this->table,
			array(
				'briefing_id'   => $version->briefing_id,
				'version_major' => $version->version->major(),
				'version_minor' => $version->version->minor(),
				'title'         => $version->title,
				'body_html'     => $version->body_html,
				'body_checksum' => $version->body_checksum,
				'change_note'   => $version->change_note,
				'published_at'  => $version->published_at->format( 'Y-m-d H:i:s' ),
				'published_by'  => $version->published_by,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not save the briefing version: ' . $this->db->last_error );
		}

		unset( $this->latest[ $version->briefing_id ] );

		return $version->with_id( (int) $this->db->insert_id );
	}

	public function find( int $id ): ?BriefingVersion {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ) );

		return $row instanceof stdClass ? BriefingVersion::from_row( $row ) : null;
	}

	/**
	 * @param list<int> $ids
	 * @return array<int, BriefingVersion> Keyed by version id.
	 */
	public function find_many( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( array() === $ids ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				array_merge( array( $this->table ), $ids )
			)
		);

		$versions = array();
		foreach ( $this->to_versions( $rows ) as $version ) {
			$versions[ $version->id ] = $version;
		}

		return $versions;
	}

	public function latest_for_briefing( int $briefing_id ): ?BriefingVersion {
		if ( ! array_key_exists( $briefing_id, $this->latest ) ) {
			$row = $this->db->get_row(
				$this->db->prepare(
					'SELECT * FROM %i WHERE briefing_id = %d ORDER BY version_major DESC, version_minor DESC LIMIT 1',
					$this->table,
					$briefing_id
				)
			);

			$this->latest[ $briefing_id ] = $row instanceof stdClass ? BriefingVersion::from_row( $row ) : null;
		}

		return $this->latest[ $briefing_id ];
	}

	/**
	 * @param list<int> $briefing_ids
	 * @return array<int, BriefingVersion> Current version of each briefing that has one, keyed by briefing id.
	 */
	public function latest_for_briefings( array $briefing_ids ): array {
		$briefing_ids = array_values( array_unique( array_filter( array_map( 'intval', $briefing_ids ) ) ) );
		if ( array() === $briefing_ids ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT v.* FROM %i v WHERE v.briefing_id IN (' . implode( ',', array_fill( 0, count( $briefing_ids ), '%d' ) ) . ')
				AND NOT EXISTS (
					SELECT 1 FROM %i newer
					WHERE newer.briefing_id = v.briefing_id
					AND ( newer.version_major > v.version_major OR ( newer.version_major = v.version_major AND newer.version_minor > v.version_minor ) )
				)',
				array_merge( array( $this->table ), $briefing_ids, array( $this->table ) )
			)
		);

		$latest = array();
		foreach ( $this->to_versions( $rows ) as $version ) {
			$latest[ $version->briefing_id ]       = $version;
			$this->latest[ $version->briefing_id ] = $version;
		}

		return $latest;
	}

	/**
	 * @return list<BriefingVersion> Oldest first.
	 */
	public function all_for_briefing( int $briefing_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i WHERE briefing_id = %d ORDER BY version_major ASC, version_minor ASC',
				$this->table,
				$briefing_id
			)
		);

		return $this->to_versions( $rows );
	}

	/**
	 * @param mixed $rows Result of wpdb::get_results().
	 * @return list<BriefingVersion>
	 */
	private function to_versions( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$versions = array();
		foreach ( $rows as $row ) {
			if ( $row instanceof stdClass ) {
				$versions[] = BriefingVersion::from_row( $row );
			}
		}

		return $versions;
	}
}
