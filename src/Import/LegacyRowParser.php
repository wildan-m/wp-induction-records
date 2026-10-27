<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;

/**
 * Validates rows of the paper-records CSV and turns them into LegacyRecords.
 *
 * Every problem in a row is collected, so one pass over the file reports all
 * of them. A legacy_ref may appear only once per file (compared without
 * regard to case, like the database index); every later row with the same
 * reference is rejected, even when the first one was invalid for another reason.
 */
final class LegacyRowParser {

	public const COLUMNS = array( 'legacy_ref', 'member_email', 'machine_slug', 'inducted_on', 'supervisor_email', 'scan_filename' );

	/**
	 * Accepted date formats: ISO (2019-03-02), day first with slashes
	 * (02/03/2019 or 2/3/2019) and written out (2 Mar 2019, 2 March 2019).
	 */
	private const DATE_FORMATS = array( 'Y-m-d', 'd/m/Y', 'j M Y', 'j F Y' );

	private const MAX_REF_LENGTH = 64;

	/**
	 * @var array<string, int> Required column name => cell index.
	 */
	private array $columns = array();

	private int $header_width = 0;

	/**
	 * @var array<string, int> Lower-cased legacy_ref => row it first appeared on.
	 */
	private array $seen_refs = array();

	private readonly DateTimeImmutable $today;

	/**
	 * @param DateTimeZone      $timezone Site timezone; dates are read as local dates.
	 * @param DateTimeImmutable $today    Dates after this day are rejected.
	 */
	public function __construct(
		private readonly DateTimeZone $timezone,
		DateTimeImmutable $today
	) {
		$this->today = $today->setTimezone( $timezone )->setTime( 0, 0 );
	}

	/**
	 * Maps the header row. Column names are matched without regard to case or
	 * order; extra columns are ignored.
	 *
	 * @param array<int, string> $cells
	 * @throws ImportFileException When a required column is missing or a column repeats.
	 */
	public function read_header( array $cells ): void {
		$columns = array();
		foreach ( array_values( $cells ) as $index => $cell ) {
			if ( 0 === $index ) {
				$cell = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $cell );
			}

			$name = strtolower( trim( $cell ) );
			if ( '' === $name ) {
				continue;
			}

			if ( isset( $columns[ $name ] ) ) {
				throw new ImportFileException( sprintf( 'The header repeats the column "%s".', $name ) );
			}

			$columns[ $name ] = $index;
		}

		$missing = array_diff( self::COLUMNS, array_keys( $columns ) );
		if ( array() !== $missing ) {
			throw new ImportFileException( sprintf( 'The header is missing required column(s): %s.', implode( ', ', $missing ) ) );
		}

		$this->columns      = array_intersect_key( $columns, array_flip( self::COLUMNS ) );
		$this->header_width = count( $cells );
	}

	/**
	 * Raw legacy_ref of a row, for reports about rows that failed validation.
	 *
	 * @param array<int, string> $cells
	 */
	public function reference_in( array $cells ): string {
		$index = $this->columns['legacy_ref'] ?? null;
		$cells = array_values( $cells );

		return null !== $index && isset( $cells[ $index ] ) ? trim( $cells[ $index ] ) : '';
	}

	/**
	 * @param array<int, string> $cells
	 * @param int                $row   Row number in the file, used in messages.
	 * @throws InvalidRowException Listing every problem in the row.
	 * @throws LogicException      When called before read_header().
	 */
	public function parse( array $cells, int $row ): LegacyRecord {
		if ( array() === $this->columns ) {
			throw new LogicException( 'Read the header row before parsing records.' );
		}

		$cells    = array_values( $cells );
		$problems = array();

		$legacy_ref = $this->legacy_ref( $this->reference_in( $cells ), $row, $problems );

		$missing = array();
		foreach ( $this->columns as $name => $index ) {
			if ( ! array_key_exists( $index, $cells ) ) {
				$missing[] = $name;
			}
		}

		if ( array() !== $missing ) {
			$problems[] = sprintf( 'missing column(s): %s', implode( ', ', $missing ) );
			throw new InvalidRowException( $problems );
		}

		$overflow = array_filter(
			array_slice( $cells, $this->header_width ),
			static fn( string $cell ): bool => '' !== trim( $cell )
		);
		if ( array() !== $overflow ) {
			$problems[] = 'more values than header columns (check for unquoted commas)';
		}

		$member_email     = $this->email( 'member_email', $this->value( $cells, 'member_email' ), $problems );
		$machine_slug     = $this->machine_slug( $this->value( $cells, 'machine_slug' ), $problems );
		$inducted_on      = $this->date( $this->value( $cells, 'inducted_on' ), $problems );
		$supervisor_email = $this->email( 'supervisor_email', $this->value( $cells, 'supervisor_email' ), $problems );
		$scan_filename    = $this->scan_filename( $this->value( $cells, 'scan_filename' ), $problems );

		if ( array() !== $problems || null === $inducted_on ) {
			throw new InvalidRowException( $problems );
		}

		return new LegacyRecord( $row, $legacy_ref, $member_email, $machine_slug, $inducted_on, $supervisor_email, $scan_filename );
	}

	/**
	 * @param list<string> $cells
	 */
	private function value( array $cells, string $column ): string {
		return trim( $cells[ $this->columns[ $column ] ] );
	}

	/**
	 * @param list<string> $problems
	 */
	private function legacy_ref( string $legacy_ref, int $row, array &$problems ): string {
		if ( '' === $legacy_ref ) {
			$problems[] = 'legacy_ref is empty';
			return '';
		}

		if ( strlen( $legacy_ref ) > self::MAX_REF_LENGTH || 1 !== preg_match( '#^[A-Za-z0-9][A-Za-z0-9._/-]*$#D', $legacy_ref ) ) {
			$problems[] = sprintf(
				'legacy_ref "%1$s" must start with a letter or digit and use only letters, digits and . _ / - (at most %2$d characters)',
				$legacy_ref,
				self::MAX_REF_LENGTH
			);
			return $legacy_ref;
		}

		$key = strtolower( $legacy_ref );
		if ( isset( $this->seen_refs[ $key ] ) ) {
			$problems[] = sprintf( 'legacy_ref "%1$s" already appears on row %2$d', $legacy_ref, $this->seen_refs[ $key ] );
		} else {
			$this->seen_refs[ $key ] = $row;
		}

		return $legacy_ref;
	}

	/**
	 * @param list<string> $problems
	 */
	private function email( string $column, string $email, array &$problems ): string {
		if ( '' === $email ) {
			$problems[] = sprintf( '%s is empty', $column );
		} elseif ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$problems[] = sprintf( '%1$s "%2$s" is not a valid email address', $column, $email );
		}

		return strtolower( $email );
	}

	/**
	 * @param list<string> $problems
	 */
	private function machine_slug( string $slug, array &$problems ): string {
		$slug = strtolower( $slug );

		if ( '' === $slug ) {
			$problems[] = 'machine_slug is empty';
		} elseif ( 1 !== preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/D', $slug ) ) {
			$problems[] = sprintf( 'machine_slug "%s" is not a valid slug', $slug );
		}

		return $slug;
	}

	/**
	 * @param list<string> $problems
	 */
	private function date( string $text, array &$problems ): ?DateTimeImmutable {
		if ( '' === $text ) {
			$problems[] = 'inducted_on is empty';
			return null;
		}

		foreach ( self::DATE_FORMATS as $format ) {
			// The leading "!" resets the time to midnight.
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $text, $this->timezone );
			if ( false === $date || $this->had_parse_warnings() || (int) $date->format( 'Y' ) < 1900 ) {
				continue;
			}

			if ( $date > $this->today ) {
				$problems[] = sprintf( 'inducted_on "%s" is in the future', $text );
				return null;
			}

			return $date;
		}

		$problems[] = sprintf( 'inducted_on "%s" is not a valid date (use 2019-03-02, 02/03/2019 or 2 March 2019)', $text );
		return null;
	}

	/**
	 * True when the last parse rolled an impossible date over, such as 31 April.
	 */
	private function had_parse_warnings(): bool {
		$errors = DateTimeImmutable::getLastErrors();

		return is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 );
	}

	/**
	 * @param list<string> $problems
	 */
	private function scan_filename( string $name, array &$problems ): string {
		if ( '' === $name ) {
			$problems[] = 'scan_filename is empty';
		} elseif ( strlen( $name ) > 255 || basename( $name ) !== $name || str_contains( $name, '\\' ) || str_contains( $name, "\0" ) || str_starts_with( $name, '.' ) ) {
			$problems[] = sprintf( 'scan_filename "%s" must be a plain file name inside the --files folder', $name );
		} elseif ( 1 !== preg_match( '/\.pdf$/iD', $name ) ) {
			$problems[] = sprintf( 'scan_filename "%s" is not a .pdf file', $name );
		}

		return $name;
	}
}
