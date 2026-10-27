<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Database;

use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Briefing\VersionNumber;
use ForgeBench\InductionRecords\Induction\SignOff;
use ForgeBench\InductionRecords\Induction\SignOffStatus;
use RuntimeException;
use stdClass;
use wpdb;

/**
 * Reads and writes induction sign-offs.
 */
final class SignOffRepository {

	public function __construct(
		private readonly wpdb $db,
		private readonly string $table,
		private readonly string $versions_table
	) {
	}

	public static function create(): self {
		global $wpdb;

		return new self( $wpdb, Schema::signoffs_table(), Schema::versions_table() );
	}

	/**
	 * @return int Id of the new sign-off.
	 * @throws RuntimeException When the row cannot be written, for instance on a duplicate legacy_ref.
	 */
	public function create_signoff(
		int $member_id,
		int $briefing_version_id,
		int $supervisor_id,
		DateTimeImmutable $inducted_at,
		SignOffStatus $status,
		?DateTimeImmutable $member_confirmed_at = null,
		?string $pdf_path = null,
		?string $legacy_ref = null
	): int {
		$inserted = $this->db->insert(
			$this->table,
			array(
				'member_id'           => $member_id,
				'briefing_version_id' => $briefing_version_id,
				'supervisor_id'       => $supervisor_id,
				'inducted_at'         => self::to_db( $inducted_at ),
				'member_confirmed_at' => null === $member_confirmed_at ? null : self::to_db( $member_confirmed_at ),
				'pdf_path'            => $pdf_path,
				'legacy_ref'          => $legacy_ref,
				'status'              => $status->value,
				'created_at'          => self::to_db( new DateTimeImmutable( 'now' ) ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not save the sign-off: ' . $this->db->last_error );
		}

		return (int) $this->db->insert_id;
	}

	public function find( int $id ): ?SignOff {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ) );

		return $row instanceof stdClass ? SignOff::from_row( $row ) : null;
	}

	public function find_by_legacy_ref( string $legacy_ref ): ?SignOff {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM %i WHERE legacy_ref = %s', $this->table, $legacy_ref ) );

		return $row instanceof stdClass ? SignOff::from_row( $row ) : null;
	}

	/**
	 * @return list<SignOff> Newest first.
	 */
	public function for_member( int $member_id ): array {
		return $this->to_signoffs(
			$this->db->get_results(
				$this->db->prepare(
					'SELECT * FROM %i WHERE member_id = %d ORDER BY inducted_at DESC, id DESC',
					$this->table,
					$member_id
				)
			)
		);
	}

	/**
	 * Pending or confirmed sign-off of a member on a given major version of a briefing.
	 */
	public function find_active( int $member_id, int $briefing_id, int $major ): ?SignOff {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT s.* FROM %i s INNER JOIN %i v ON v.id = s.briefing_version_id
				WHERE s.member_id = %d AND v.briefing_id = %d AND v.version_major = %d AND s.status IN ( %s, %s )
				ORDER BY s.id DESC LIMIT 1',
				$this->table,
				$this->versions_table,
				$member_id,
				$briefing_id,
				$major,
				SignOffStatus::Pending->value,
				SignOffStatus::Confirmed->value
			)
		);

		return $row instanceof stdClass ? SignOff::from_row( $row ) : null;
	}

	/**
	 * Most recent confirmed or flagged sign-off of a member on a briefing.
	 */
	public function newest_settled( int $member_id, int $briefing_id ): ?SignOff {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT s.* FROM %i s INNER JOIN %i v ON v.id = s.briefing_version_id
				WHERE s.member_id = %d AND v.briefing_id = %d AND s.status IN ( %s, %s )
				ORDER BY s.inducted_at DESC, s.id DESC LIMIT 1',
				$this->table,
				$this->versions_table,
				$member_id,
				$briefing_id,
				SignOffStatus::Confirmed->value,
				SignOffStatus::ReinductionRequired->value
			)
		);

		return $row instanceof stdClass ? SignOff::from_row( $row ) : null;
	}

	/**
	 * Pending and confirmed sign-offs on any version of a briefing, with the
	 * version each one was given on.
	 *
	 * @return list<array{id: int, status: SignOffStatus, version: VersionNumber}>
	 */
	public function active_on_briefing( int $briefing_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT s.id, s.status, v.version_major, v.version_minor FROM %i s INNER JOIN %i v ON v.id = s.briefing_version_id
				WHERE v.briefing_id = %d AND s.status IN ( %s, %s )',
				$this->table,
				$this->versions_table,
				$briefing_id,
				SignOffStatus::Pending->value,
				SignOffStatus::Confirmed->value
			)
		);

		$active = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$active[] = array(
				'id'      => (int) $row->id,
				'status'  => SignOffStatus::from( (string) $row->status ),
				'version' => VersionNumber::of( (int) $row->version_major, (int) $row->version_minor ),
			);
		}

		return $active;
	}

	public function count_active_on_briefing( int $briefing_id ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				'SELECT COUNT(*) FROM %i s INNER JOIN %i v ON v.id = s.briefing_version_id
				WHERE v.briefing_id = %d AND s.status IN ( %s, %s )',
				$this->table,
				$this->versions_table,
				$briefing_id,
				SignOffStatus::Pending->value,
				SignOffStatus::Confirmed->value
			)
		);
	}

	/**
	 * Confirms a pending sign-off. Conditional on the status, so of two
	 * concurrent confirmations only one succeeds.
	 *
	 * @return bool False when the sign-off was no longer pending.
	 */
	public function mark_confirmed(
		int $id,
		DateTimeImmutable $confirmed_at,
		string $ip_address,
		string $user_agent,
		string $rendered_hash,
		string $pdf_path
	): bool {
		$updated = $this->db->update(
			$this->table,
			array(
				'status'              => SignOffStatus::Confirmed->value,
				'member_confirmed_at' => self::to_db( $confirmed_at ),
				'ip_address'          => '' === $ip_address ? null : $ip_address,
				'user_agent'          => '' === $user_agent ? null : $user_agent,
				'rendered_hash'       => $rendered_hash,
				'pdf_path'            => $pdf_path,
			),
			array(
				'id'     => $id,
				'status' => SignOffStatus::Pending->value,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);

		return 1 === $updated;
	}

	/**
	 * @param list<int> $ids
	 * @return int Number of rows changed.
	 * @throws RuntimeException When the update fails.
	 */
	public function set_status( array $ids, SignOffStatus $status ): int {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( array() === $ids ) {
			return 0;
		}

		$updated = $this->db->query(
			$this->db->prepare(
				'UPDATE %i SET status = %s WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				array_merge( array( $this->table, $status->value ), $ids )
			)
		);

		if ( false === $updated ) {
			throw new RuntimeException( 'Could not update sign-off statuses: ' . $this->db->last_error );
		}

		return (int) $updated;
	}

	/**
	 * Marks a member's other confirmed or flagged sign-offs on the same briefing
	 * as superseded, once a newer induction has taken their place.
	 *
	 * @return int Number of rows changed.
	 */
	public function supersede_others( int $member_id, int $briefing_id, int $keep_id ): int {
		return (int) $this->db->query(
			$this->db->prepare(
				'UPDATE %i s INNER JOIN %i v ON v.id = s.briefing_version_id SET s.status = %s
				WHERE s.member_id = %d AND v.briefing_id = %d AND s.id <> %d AND s.status IN ( %s, %s )',
				$this->table,
				$this->versions_table,
				SignOffStatus::Superseded->value,
				$member_id,
				$briefing_id,
				$keep_id,
				SignOffStatus::Confirmed->value,
				SignOffStatus::ReinductionRequired->value
			)
		);
	}

	/**
	 * Page of sign-offs for the admin list, newest induction first by default.
	 *
	 * @param int    $briefing_id Machine filter; 0 for all machines.
	 * @param string $status      Status filter; '' for all.
	 * @param string $order       'ASC' or 'DESC' by induction date.
	 * @return array{items: list<SignOff>, total: int}
	 */
	public function search( int $briefing_id, string $status, int $per_page, int $page, string $order ): array {
		$direction = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$per_page  = max( 1, $per_page );
		$offset    = max( 0, $page - 1 ) * $per_page;

		// Unset filters compare against 0 / '' so one prepared statement covers every combination.
		$where = 'FROM %i s INNER JOIN %i v ON v.id = s.briefing_version_id WHERE ( %d = 0 OR v.briefing_id = %d ) AND ( %s = \'\' OR s.status = %s )';
		$args  = array( $this->table, $this->versions_table, $briefing_id, $briefing_id, $status, $status );

		$total = (int) $this->db->get_var( $this->db->prepare( 'SELECT COUNT(*) ' . $where, $args ) );

		// $direction is one of two literals.
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT s.* ' . $where . " ORDER BY s.inducted_at {$direction}, s.id {$direction} LIMIT %d OFFSET %d",
				array_merge( $args, array( $per_page, $offset ) )
			)
		);

		return array(
			'items' => $this->to_signoffs( $rows ),
			'total' => $total,
		);
	}

	/**
	 * @return array<string, int> Number of sign-offs per status value, optionally for one machine.
	 */
	public function count_by_status( int $briefing_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT s.status, COUNT(*) AS total FROM %i s INNER JOIN %i v ON v.id = s.briefing_version_id
				WHERE ( %d = 0 OR v.briefing_id = %d ) GROUP BY s.status',
				$this->table,
				$this->versions_table,
				$briefing_id,
				$briefing_id
			)
		);

		$counts = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$counts[ (string) $row->status ] = (int) $row->total;
		}

		return $counts;
	}

	private static function to_db( DateTimeImmutable $date ): string {
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * @param mixed $rows Result of wpdb::get_results().
	 * @return list<SignOff>
	 */
	private function to_signoffs( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$signoffs = array();
		foreach ( $rows as $row ) {
			if ( $row instanceof stdClass ) {
				$signoffs[] = SignOff::from_row( $row );
			}
		}

		return $signoffs;
	}
}
