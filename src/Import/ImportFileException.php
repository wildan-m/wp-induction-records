<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Import;

use RuntimeException;

/**
 * The import file as a whole cannot be used (unreadable, empty, bad header).
 * Nothing is imported when this is thrown.
 */
final class ImportFileException extends RuntimeException {
}
