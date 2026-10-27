<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

use DateTimeImmutable;
use DateTimeZone;
use stdClass;

/**
 * Immutable snapshot of a machine briefing at the moment it was published.
 */
final class BriefingVersion {

	public function __construct(
		public readonly int $id,
		public readonly int $briefing_id,
		public readonly VersionNumber $version,
		public readonly string $title,
		public readonly string $body_html,
		public readonly string $body_checksum,
		public readonly string $change_note,
		public readonly DateTimeImmutable $published_at,
		public readonly int $published_by
	) {
	}

	/**
	 * @param stdClass $row Row of the versions table; datetimes are stored in UTC.
	 */
	public static function from_row( stdClass $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->briefing_id,
			VersionNumber::of( (int) $row->version_major, (int) $row->version_minor ),
			(string) $row->title,
			(string) $row->body_html,
			(string) $row->body_checksum,
			(string) $row->change_note,
			new DateTimeImmutable( (string) $row->published_at, new DateTimeZone( 'UTC' ) ),
			(int) $row->published_by
		);
	}

	public function with_id( int $id ): self {
		return new self(
			$id,
			$this->briefing_id,
			$this->version,
			$this->title,
			$this->body_html,
			$this->body_checksum,
			$this->change_note,
			$this->published_at,
			$this->published_by
		);
	}
}
