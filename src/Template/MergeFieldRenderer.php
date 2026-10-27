<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Template;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Stringable;

/**
 * Replaces {{merge.fields}} in a trusted HTML template with escaped values.
 *
 * Fields are dot paths into the context array, such as {{member.display_name}},
 * and may carry one filter: {{inducted_at|date}}, {{inducted_at|date:Y-m-d}} or
 * {{machine.name|upper}}. A placeholder that cannot be resolved stays in the
 * output exactly as written and is listed in the result, so a typo shows up on
 * the document instead of silently disappearing.
 */
final class MergeFieldRenderer {

	private const PLACEHOLDER = '/\{\{\s*([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*)\s*(?:\|\s*([a-z][a-z0-9_]*)\s*(?::([^{}]*?))?\s*)?\}\}/i';

	public const FILTERS = array( 'date', 'datetime', 'upper', 'lower' );

	public function __construct(
		private readonly string $date_format = 'j F Y',
		private readonly string $datetime_format = 'j F Y, H:i',
		private readonly ?DateTimeZone $timezone = null
	) {
	}

	/**
	 * @param string               $template Trusted HTML; only the merged values are escaped.
	 * @param array<string, mixed> $context  Values keyed by field name. Nested arrays are reached with dot paths.
	 */
	public function render( string $template, array $context ): RenderResult {
		$unknown_fields  = array();
		$unknown_filters = array();

		$output = preg_replace_callback(
			self::PLACEHOLDER,
			function ( array $placeholder ) use ( $context, &$unknown_fields, &$unknown_filters ): string {
				$field    = strtolower( $placeholder[1] );
				$filter   = isset( $placeholder[2] ) ? strtolower( $placeholder[2] ) : '';
				$argument = isset( $placeholder[3] ) ? trim( $placeholder[3] ) : '';

				list( $found, $value ) = $this->lookup( $context, $field );

				if ( ! $found ) {
					$unknown_fields[ $field ] = true;
					return $placeholder[0];
				}

				if ( '' !== $filter && ! in_array( $filter, self::FILTERS, true ) ) {
					$unknown_filters[ $filter ] = true;
					return $placeholder[0];
				}

				return $this->escape( $this->format( $value, $filter, $argument ) );
			},
			$template
		);

		return new RenderResult(
			(string) $output,
			array_map( 'strval', array_keys( $unknown_fields ) ),
			array_map( 'strval', array_keys( $unknown_filters ) )
		);
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array{0: bool, 1: mixed} Whether the field holds a printable value, and the value.
	 */
	private function lookup( array $context, string $field ): array {
		if ( array_key_exists( $field, $context ) ) {
			$value = $context[ $field ];
		} else {
			$value = $context;
			foreach ( explode( '.', $field ) as $segment ) {
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					return array( false, null );
				}
				$value = $value[ $segment ];
			}
		}

		$printable = null === $value
			|| is_scalar( $value )
			|| $value instanceof Stringable
			|| $value instanceof DateTimeInterface;

		return array( $printable, $value );
	}

	private function format( mixed $value, string $filter, string $argument ): string {
		switch ( $filter ) {
			case 'date':
			case 'datetime':
				$date = $this->to_date( $value );
				if ( null === $date ) {
					return $this->stringify( $value );
				}
				if ( '' !== $argument ) {
					return $date->format( $argument );
				}
				return $date->format( 'date' === $filter ? $this->date_format : $this->datetime_format );

			case 'upper':
				return mb_strtoupper( $this->stringify( $value ), 'UTF-8' );

			case 'lower':
				return mb_strtolower( $this->stringify( $value ), 'UTF-8' );

			default:
				return $this->stringify( $value );
		}
	}

	private function stringify( mixed $value ): string {
		if ( $value instanceof DateTimeInterface ) {
			return $this->localise( $value )->format( $this->datetime_format );
		}

		if ( is_scalar( $value ) || $value instanceof Stringable ) {
			return (string) $value;
		}

		return '';
	}

	private function to_date( mixed $value ): ?DateTimeImmutable {
		if ( $value instanceof DateTimeInterface ) {
			return $this->localise( $value );
		}

		if ( is_int( $value ) ) {
			return $this->localise( new DateTimeImmutable( '@' . $value ) );
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			try {
				return $this->localise( new DateTimeImmutable( $value, $this->timezone ) );
			} catch ( Exception ) {
				return null;
			}
		}

		return null;
	}

	private function localise( DateTimeInterface $date ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromInterface( $date );

		return null === $this->timezone ? $date : $date->setTimezone( $this->timezone );
	}

	private function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
