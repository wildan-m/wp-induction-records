<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

use DomainException;

/**
 * A publish request that breaks a versioning rule. The reason code lets the
 * admin screen show a translated message.
 */
final class VersionPolicyException extends DomainException {

	public const UNCHANGED     = 'unchanged';
	public const MISSING_NOTE  = 'missing_note';
	public const NOTE_TOO_LONG = 'note_too_long';
	public const EMPTY_CONTENT = 'empty_content';

	private function __construct(
		public readonly string $reason,
		string $message
	) {
		parent::__construct( $message );
	}

	public static function unchanged( VersionNumber $current ): self {
		return new self(
			self::UNCHANGED,
			sprintf( 'Nothing changed since version %s, so there is no minor version to publish.', $current->to_string() )
		);
	}

	public static function missing_note(): self {
		return new self( self::MISSING_NOTE, 'A change note is required.' );
	}

	public static function note_too_long( int $limit ): self {
		return new self( self::NOTE_TOO_LONG, sprintf( 'The change note is longer than %d characters.', $limit ) );
	}

	public static function empty_content(): self {
		return new self( self::EMPTY_CONTENT, 'A briefing needs a title and body text before it can be published.' );
	}
}
