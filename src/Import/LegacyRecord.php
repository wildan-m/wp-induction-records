<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use DateTimeImmutable;

/**
 * A validated row of the paper-records CSV.
 */
final class LegacyRecord {

	/**
	 * @param int               $row         Row number in the file; the header is row 1.
	 * @param DateTimeImmutable $inducted_on Midnight of the induction date in the site's timezone.
	 */
	public function __construct(
		public readonly int $row,
		public readonly string $legacy_ref,
		public readonly string $member_email,
		public readonly string $machine_slug,
		public readonly DateTimeImmutable $inducted_on,
		public readonly string $supervisor_email,
		public readonly string $scan_filename
	) {
	}
}
