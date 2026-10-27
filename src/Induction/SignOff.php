<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Induction;

use DateTimeImmutable;
use DateTimeZone;
use stdClass;

/**
 * One member's induction on one briefing version.
 */
final class SignOff {

	public function __construct(
		public readonly int $id,
		public readonly int $member_id,
		public readonly int $briefing_version_id,
		public readonly int $supervisor_id,
		public readonly DateTimeImmutable $inducted_at,
		public readonly ?DateTimeImmutable $member_confirmed_at,
		public readonly ?string $ip_address,
		public readonly ?string $user_agent,
		public readonly ?string $rendered_hash,
		public readonly ?string $pdf_path,
		public readonly ?string $legacy_ref,
		public readonly SignOffStatus $status,
		public readonly DateTimeImmutable $created_at
	) {
	}

	/**
	 * @param stdClass $row Row of the sign-offs table; datetimes are stored in UTC.
	 */
	public static function from_row( stdClass $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->member_id,
			(int) $row->briefing_version_id,
			(int) $row->supervisor_id,
			self::date( $row->inducted_at ) ?? new DateTimeImmutable( '@0' ),
			self::date( $row->member_confirmed_at ),
			self::text( $row->ip_address ),
			self::text( $row->user_agent ),
			self::text( $row->rendered_hash ),
			self::text( $row->pdf_path ),
			self::text( $row->legacy_ref ),
			SignOffStatus::from( (string) $row->status ),
			self::date( $row->created_at ) ?? new DateTimeImmutable( '@0' )
		);
	}

	public static function format_certificate_id( int $id ): string {
		return sprintf( 'IR-%06d', $id );
	}

	/**
	 * Reference printed on the certificate and used in its file name.
	 */
	public function certificate_id(): string {
		return self::format_certificate_id( $this->id );
	}

	public function has_document(): bool {
		return null !== $this->pdf_path;
	}

	public function is_legacy(): bool {
		return null !== $this->legacy_ref;
	}

	private static function date( mixed $value ): ?DateTimeImmutable {
		if ( ! is_string( $value ) || '' === $value || str_starts_with( $value, '0000-00-00' ) ) {
			return null;
		}

		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	private static function text( mixed $value ): ?string {
		return is_string( $value ) && '' !== $value ? $value : null;
	}
}
