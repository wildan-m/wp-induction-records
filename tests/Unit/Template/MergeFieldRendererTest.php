<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Template;

use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Template\MergeFieldRenderer;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class MergeFieldRendererTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private static function context(): array {
		return array(
			'member'      => array( 'display_name' => 'Test Member 1' ),
			'machine'     => array(
				'name' => 'Laser cutter',
				'slug' => 'laser-cutter',
			),
			'briefing'    => array( 'version' => '2.1' ),
			'inducted_at' => new DateTimeImmutable( '2026-03-01 23:30:00', new DateTimeZone( 'UTC' ) ),
		);
	}

	public function test_replaces_fields_from_a_nested_context(): void {
		$result = ( new MergeFieldRenderer() )->render(
			'<p>{{member.display_name}} on the {{machine.name}}, version {{briefing.version}}</p>',
			self::context()
		);

		$this->assertSame( '<p>Test Member 1 on the Laser cutter, version 2.1</p>', $result->output );
		$this->assertTrue( $result->is_complete() );
	}

	public function test_accepts_flat_keys_with_dots(): void {
		$result = ( new MergeFieldRenderer() )->render( '{{member.display_name}}', array( 'member.display_name' => 'Test Member 2' ) );

		$this->assertSame( 'Test Member 2', $result->output );
	}

	public function test_allows_spaces_and_any_letter_case_inside_the_braces(): void {
		$result = ( new MergeFieldRenderer() )->render( '{{ Member.Display_Name }} / {{ machine.name | upper }}', self::context() );

		$this->assertSame( 'Test Member 1 / LASER CUTTER', $result->output );
	}

	public function test_escapes_values_but_not_the_template(): void {
		$context = array( 'member' => array( 'display_name' => '<script>alert("x")</script> & O\'Neil' ) );

		$result = ( new MergeFieldRenderer() )->render( '<strong>{{member.display_name}}</strong>', $context );

		$this->assertSame( '<strong>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; O&#039;Neil</strong>', $result->output );
	}

	public function test_leaves_unknown_fields_visible_and_reports_each_once(): void {
		$result = ( new MergeFieldRenderer() )->render(
			'{{member.shoe_size}} {{member.display_name}} {{machine.colour}} {{member.shoe_size}}',
			self::context()
		);

		$this->assertSame( '{{member.shoe_size}} Test Member 1 {{machine.colour}} {{member.shoe_size}}', $result->output );
		$this->assertSame( array( 'member.shoe_size', 'machine.colour' ), $result->unknown_fields );
		$this->assertFalse( $result->is_complete() );
	}

	public function test_a_field_that_holds_a_group_is_unknown(): void {
		$result = ( new MergeFieldRenderer() )->render( '{{member}}', self::context() );

		$this->assertSame( '{{member}}', $result->output );
		$this->assertSame( array( 'member' ), $result->unknown_fields );
	}

	public function test_leaves_unknown_filters_visible_and_reports_them(): void {
		$result = ( new MergeFieldRenderer() )->render( '{{machine.name|shout}}', self::context() );

		$this->assertSame( '{{machine.name|shout}}', $result->output );
		$this->assertSame( array(), $result->unknown_fields );
		$this->assertSame( array( 'shout' ), $result->unknown_filters );
		$this->assertFalse( $result->is_complete() );
	}

	public function test_date_filter_uses_the_default_format_in_the_site_timezone(): void {
		$renderer = new MergeFieldRenderer( 'j F Y', 'j F Y H:i', new DateTimeZone( 'Pacific/Auckland' ) );

		// 23:30 UTC on 1 March is already 2 March in New Zealand.
		$this->assertSame( '2 March 2026', $renderer->render( '{{inducted_at|date}}', self::context() )->output );
	}

	public function test_date_filter_accepts_a_format(): void {
		$renderer = new MergeFieldRenderer( 'j F Y', 'j F Y H:i', new DateTimeZone( 'UTC' ) );

		$this->assertSame( '2026-03-01', $renderer->render( '{{inducted_at|date:Y-m-d}}', self::context() )->output );
	}

	public function test_datetime_filter_and_bare_dates_use_the_datetime_format(): void {
		$renderer = new MergeFieldRenderer( 'j F Y', 'j M Y, H:i', new DateTimeZone( 'Europe/London' ) );

		$result = $renderer->render( '{{inducted_at|datetime}} / {{inducted_at}}', self::context() );

		$this->assertSame( '1 Mar 2026, 23:30 / 1 Mar 2026, 23:30', $result->output );
	}

	public function test_date_filter_reads_date_strings(): void {
		$renderer = new MergeFieldRenderer( 'j F Y', 'j F Y H:i', new DateTimeZone( 'UTC' ) );

		$this->assertSame( '14 June 2019', $renderer->render( '{{day|date}}', array( 'day' => '2019-06-14' ) )->output );
	}

	public function test_date_filter_prints_values_it_cannot_read_as_they_are(): void {
		$result = ( new MergeFieldRenderer() )->render( '{{day|date}}', array( 'day' => 'sometime <soon>' ) );

		$this->assertSame( 'sometime &lt;soon&gt;', $result->output );
	}

	public function test_case_filters_handle_multibyte_text(): void {
		$result = ( new MergeFieldRenderer() )->render( '{{tool|upper}} {{tool|lower}}', array( 'tool' => 'Bandsäge' ) );

		$this->assertSame( 'BANDSÄGE bandsäge', $result->output );
	}

	public function test_null_renders_as_empty_text(): void {
		$result = ( new MergeFieldRenderer() )->render( '[{{member.nickname}}]', array( 'member' => array( 'nickname' => null ) ) );

		$this->assertSame( '[]', $result->output );
		$this->assertTrue( $result->is_complete() );
	}

	public function test_numbers_and_stringable_objects_are_printed(): void {
		$reference = new class() {
			public function __toString(): string {
				return 'IR-000042';
			}
		};

		$result = ( new MergeFieldRenderer() )->render(
			'{{count}} {{reference}}',
			array(
				'count'     => 3,
				'reference' => $reference,
			)
		);

		$this->assertSame( '3 IR-000042', $result->output );
	}

	public function test_text_that_only_resembles_a_placeholder_is_left_alone(): void {
		$template = '{{ }} {{1st.place}} {member.display_name} {{member..display_name}}';

		$result = ( new MergeFieldRenderer() )->render( $template, self::context() );

		$this->assertSame( $template, $result->output );
		$this->assertTrue( $result->is_complete() );
	}

	public function test_hash_is_sha256_of_the_exact_output(): void {
		$result = ( new MergeFieldRenderer() )->render( '<p>{{member.display_name}}</p>', self::context() );

		$this->assertSame( hash( 'sha256', '<p>Test Member 1</p>' ), $result->sha256() );
	}
}
