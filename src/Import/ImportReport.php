<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

/**
 * Per-row outcome of an import run.
 */
final class ImportReport {

	public const CREATED      = 'created';
	public const WOULD_CREATE = 'would-create';
	public const SKIPPED      = 'skipped';
	public const WOULD_SKIP   = 'would-skip';
	public const ERROR        = 'error';

	/**
	 * @var list<array{row: int, legacy_ref: string, result: string, detail: string}>
	 */
	private array $rows = array();

	public function __construct(
		public readonly bool $dry_run
	) {
	}

	public function created( int $row, string $legacy_ref, string $detail ): void {
		$this->add( $row, $legacy_ref, $this->dry_run ? self::WOULD_CREATE : self::CREATED, $detail );
	}

	public function skipped( int $row, string $legacy_ref, string $detail ): void {
		$this->add( $row, $legacy_ref, $this->dry_run ? self::WOULD_SKIP : self::SKIPPED, $detail );
	}

	public function error( int $row, string $legacy_ref, string $detail ): void {
		$this->add( $row, $legacy_ref, self::ERROR, $detail );
	}

	/**
	 * @return list<array{row: int, legacy_ref: string, result: string, detail: string}>
	 */
	public function rows(): array {
		return $this->rows;
	}

	public function count( string $result ): int {
		return count( array_filter( $this->rows, static fn( array $entry ): bool => $result === $entry['result'] ) );
	}

	public function has_errors(): bool {
		return $this->count( self::ERROR ) > 0;
	}

	public function summary(): string {
		if ( $this->dry_run ) {
			return sprintf(
				'Dry run: %1$d would be created, %2$d would be skipped, %3$d with errors. Nothing was written.',
				$this->count( self::WOULD_CREATE ),
				$this->count( self::WOULD_SKIP ),
				$this->count( self::ERROR )
			);
		}

		return sprintf(
			'%1$d created, %2$d skipped, %3$d with errors.',
			$this->count( self::CREATED ),
			$this->count( self::SKIPPED ),
			$this->count( self::ERROR )
		);
	}

	private function add( int $row, string $legacy_ref, string $result, string $detail ): void {
		$this->rows[] = array(
			'row'        => $row,
			'legacy_ref' => $legacy_ref,
			'result'     => $result,
			'detail'     => $detail,
		);
	}
}
