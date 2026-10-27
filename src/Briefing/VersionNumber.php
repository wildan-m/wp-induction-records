<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

use InvalidArgumentException;

/**
 * Immutable briefing version number in major.minor form.
 */
final class VersionNumber {

	private const MAX_PART = 65535;

	private function __construct(
		private readonly int $major,
		private readonly int $minor
	) {
	}

	/**
	 * @throws InvalidArgumentException When a part is out of range.
	 */
	public static function of( int $major, int $minor ): self {
		if ( $major < 1 || $minor < 0 || $major > self::MAX_PART || $minor > self::MAX_PART ) {
			throw new InvalidArgumentException( sprintf( 'Invalid briefing version %d.%d.', $major, $minor ) );
		}

		return new self( $major, $minor );
	}

	public static function initial(): self {
		return new self( 1, 0 );
	}

	/**
	 * @throws InvalidArgumentException When the text is not in major.minor form.
	 */
	public static function parse( string $text ): self {
		if ( 1 !== preg_match( '/^(\d{1,5})\.(\d{1,5})$/D', trim( $text ), $parts ) ) {
			throw new InvalidArgumentException( sprintf( '"%s" is not a briefing version in major.minor form.', $text ) );
		}

		return self::of( (int) $parts[1], (int) $parts[2] );
	}

	public function major(): int {
		return $this->major;
	}

	public function minor(): int {
		return $this->minor;
	}

	/**
	 * Next version number. A major bump resets the minor part to zero.
	 */
	public function bump( BumpType $type ): self {
		if ( BumpType::Major === $type ) {
			return new self( $this->major + 1, 0 );
		}

		return new self( $this->major, $this->minor + 1 );
	}

	/**
	 * Orders numerically by major, then minor: 1.9 sorts before 1.10.
	 *
	 * @return int -1, 0 or 1.
	 */
	public function compare( self $other ): int {
		return array( $this->major, $this->minor ) <=> array( $other->major, $other->minor );
	}

	public function equals( self $other ): bool {
		return 0 === $this->compare( $other );
	}

	public function to_string(): string {
		return $this->major . '.' . $this->minor;
	}

	public function __toString(): string {
		return $this->to_string();
	}
}
