<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Archive;

use InvalidArgumentException;

/**
 * Derives archive paths that reveal nothing about the member.
 *
 * Each member gets one folder named by an HMAC of their user id under a
 * site secret, and each file gets a random name. Paths never contain names,
 * email addresses or guessable ids, and only paths in exactly that shape are
 * accepted back, which rules out traversal.
 */
final class ArchivePaths {

	private const RELATIVE_PATH = '#^[a-f0-9]{32}/[a-f0-9]{32}\.pdf$#D';

	public function __construct(
		private readonly string $secret
	) {
		if ( strlen( $secret ) < 32 ) {
			throw new InvalidArgumentException( 'The archive secret must be at least 32 characters long.' );
		}
	}

	/**
	 * Opaque, stable folder name for a member: 32 lowercase hex characters.
	 */
	public function member_folder( int $member_id ): string {
		if ( $member_id < 1 ) {
			throw new InvalidArgumentException( 'A member id must be a positive integer.' );
		}

		return substr( hash_hmac( 'sha256', 'member:' . $member_id, $this->secret ), 0, 32 );
	}

	/**
	 * New random path for a member's file, relative to the archive root.
	 */
	public function new_relative_path( int $member_id ): string {
		return $this->member_folder( $member_id ) . '/' . bin2hex( random_bytes( 16 ) ) . '.pdf';
	}

	public function is_valid_relative_path( string $relative_path ): bool {
		return 1 === preg_match( self::RELATIVE_PATH, $relative_path );
	}
}
