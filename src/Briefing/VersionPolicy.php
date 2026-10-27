<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

/**
 * Rules for numbering and describing new briefing versions.
 */
final class VersionPolicy {

	public const MAX_NOTE_LENGTH = 2000;

	/**
	 * Number of the next version to publish.
	 *
	 * The first version is always 1.0, whatever bump was requested. After that a
	 * minor version needs an actual change to the title or body. A major version
	 * may republish unchanged text on purpose, for instance after a machine was
	 * replaced and everyone has to be inducted on it again.
	 *
	 * @throws VersionPolicyException When a minor version would publish nothing new.
	 */
	public function next_version( ?VersionNumber $current, BumpType $bump, bool $content_changed ): VersionNumber {
		if ( null === $current ) {
			return VersionNumber::initial();
		}

		if ( BumpType::Minor === $bump && ! $content_changed ) {
			throw VersionPolicyException::unchanged( $current );
		}

		return $current->bump( $bump );
	}

	/**
	 * Trims and checks the note that explains a version to members and admins.
	 *
	 * @throws VersionPolicyException When the note is empty or too long.
	 */
	public function change_note( string $note ): string {
		$note = trim( $note );

		if ( '' === $note ) {
			throw VersionPolicyException::missing_note();
		}

		if ( mb_strlen( $note, 'UTF-8' ) > self::MAX_NOTE_LENGTH ) {
			throw VersionPolicyException::note_too_long( self::MAX_NOTE_LENGTH );
		}

		return $note;
	}
}
