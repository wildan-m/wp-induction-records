<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Briefing;

use ForgeBench\InductionRecords\Briefing\BodyNormaliser;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class BodyNormaliserTest extends TestCase {

	#[DataProvider( 'equivalent_bodies' )]
	public function test_invisible_differences_give_the_same_checksum( string $first, string $second ): void {
		$normaliser = new BodyNormaliser();

		$this->assertSame( $normaliser->checksum( $first ), $normaliser->checksum( $second ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function equivalent_bodies(): array {
		return array(
			'windows line endings'   => array( "<p>Wear goggles.</p>\r\n<p>Tie long hair back.</p>", "<p>Wear goggles.</p>\n<p>Tie long hair back.</p>" ),
			'runs of whitespace'     => array( "<p>Wear   safety\n\tgoggles.</p>", '<p>Wear safety goggles.</p>' ),
			'editor comments'        => array( "<!-- wp:paragraph -->\n<p>Wear goggles.</p>\n<!-- /wp:paragraph -->", '<p>Wear goggles.</p>' ),
			'non-breaking spaces'    => array( "<p>Wear&nbsp;goggles&#160;at\u{00A0}all times.</p>", '<p>Wear goggles at all times.</p>' ),
			'indented list markup'   => array( "<ul>\n  <li>Goggles</li>\n  <li>Gloves</li>\n</ul>", '<ul><li>Goggles</li><li>Gloves</li></ul>' ),
			'surrounding whitespace' => array( "\n\n  <p>Wear goggles.</p>  \n", '<p>Wear goggles.</p>' ),
		);
	}

	#[DataProvider( 'different_bodies' )]
	public function test_visible_changes_give_a_different_checksum( string $first, string $second ): void {
		$normaliser = new BodyNormaliser();

		$this->assertNotSame( $normaliser->checksum( $first ), $normaliser->checksum( $second ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function different_bodies(): array {
		return array(
			'changed word'              => array( '<p>Wear goggles.</p>', '<p>Wear gloves.</p>' ),
			'letter case'               => array( '<p>Never leave the laser running.</p>', '<p>never leave the laser running.</p>' ),
			'space between inline tags' => array( '<p><strong>Stop</strong> <em>now</em></p>', '<p><strong>Stop</strong><em>now</em></p>' ),
			'added paragraph'           => array( '<p>Wear goggles.</p>', '<p>Wear goggles.</p><p>Wear gloves.</p>' ),
			'changed link'              => array( '<a href="/sop/laser-1">SOP</a>', '<a href="/sop/laser-2">SOP</a>' ),
		);
	}

	public function test_multibyte_characters_survive_whitespace_collapsing(): void {
		$this->assertSame( '<p>Bandsäge – Ångström</p>', ( new BodyNormaliser() )->normalise( "<p>Bandsäge –\n   Ångström</p>" ) );
	}

	public function test_checksum_is_sha256_of_the_normalised_body(): void {
		$checksum = ( new BodyNormaliser() )->checksum( "<p>Wear goggles.</p>\r\n" );

		$this->assertSame( hash( 'sha256', '<p>Wear goggles.</p>' ), $checksum );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $checksum );
	}
}
