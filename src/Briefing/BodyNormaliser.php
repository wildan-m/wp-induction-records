<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Briefing;

/**
 * Normalises briefing HTML before it is hashed.
 *
 * Differences a member cannot see (line endings, indentation, editor comments,
 * non-breaking spaces, whitespace around block elements) produce the same
 * checksum. Anything that changes the rendered text, including the space
 * between two inline elements and letter case, produces a different one.
 */
final class BodyNormaliser {

	private const BLOCK_TAGS = 'address|article|aside|blockquote|br|dd|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hr|li|ol|p|section|table|tbody|td|tfoot|th|thead|tr|ul';

	public function normalise( string $html ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $html );
		$text = (string) preg_replace( '/<!--.*?-->/s', '', $text );
		$text = str_ireplace( array( "\u{00A0}", '&nbsp;', '&#160;', '&#xa0;' ), ' ', $text );

		// ASCII whitespace only, so the bytes of a multibyte character are never touched.
		$text = (string) preg_replace( '/[ \t\n\f\x0B]+/', ' ', $text );
		$text = (string) preg_replace( '# ?(</?(?:' . self::BLOCK_TAGS . ')\b[^>]*>) ?#i', '$1', $text );

		return trim( $text );
	}

	/**
	 * SHA-256 of the normalised body, as stored with each briefing version.
	 */
	public function checksum( string $html ): string {
		return hash( 'sha256', $this->normalise( $html ) );
	}
}
