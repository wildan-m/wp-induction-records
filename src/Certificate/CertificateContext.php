<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Certificate;

use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Induction\SignOff;

/**
 * Merge fields available in the certificate template.
 */
final class CertificateContext {

	/**
	 * Field names, as listed on the settings screen.
	 *
	 * @return list<string>
	 */
	public static function fields(): array {
		return array(
			'organisation.name',
			'certificate.id',
			'member.display_name',
			'supervisor.display_name',
			'machine.name',
			'machine.slug',
			'briefing.version',
			'briefing.checksum',
			'briefing.published_at',
			'briefing.change_note',
			'inducted_at',
			'confirmed_at',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build(
		string $organisation_name,
		SignOff $signoff,
		BriefingVersion $version,
		string $machine_slug,
		string $member_name,
		string $supervisor_name,
		DateTimeImmutable $confirmed_at
	): array {
		return array(
			'organisation' => array( 'name' => $organisation_name ),
			'certificate'  => array( 'id' => $signoff->certificate_id() ),
			'member'       => array( 'display_name' => $member_name ),
			'supervisor'   => array( 'display_name' => $supervisor_name ),
			'machine'      => array(
				'name' => $version->title,
				'slug' => $machine_slug,
			),
			'briefing'     => array(
				'version'      => $version->version->to_string(),
				'checksum'     => $version->body_checksum,
				'published_at' => $version->published_at,
				'change_note'  => $version->change_note,
			),
			'inducted_at'  => $signoff->inducted_at,
			'confirmed_at' => $confirmed_at,
		);
	}

	/**
	 * Context filled with stand-in values, used to check a template before it is saved.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview( string $organisation_name ): array {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		return array(
			'organisation' => array( 'name' => $organisation_name ),
			'certificate'  => array( 'id' => SignOff::format_certificate_id( 1 ) ),
			'member'       => array( 'display_name' => 'Member' ),
			'supervisor'   => array( 'display_name' => 'Supervisor' ),
			'machine'      => array(
				'name' => 'Machine',
				'slug' => 'machine',
			),
			'briefing'     => array(
				'version'      => '1.0',
				'checksum'     => hash( 'sha256', '' ),
				'published_at' => $now,
				'change_note'  => 'First version.',
			),
			'inducted_at'  => $now,
			'confirmed_at' => $now,
		);
	}
}
