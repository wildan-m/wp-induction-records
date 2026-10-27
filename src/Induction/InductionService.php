<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Induction;

use DateTimeImmutable;
use DateTimeZone;
use ForgeBench\InductionRecords\Archive\ArchiveStorage;
use ForgeBench\InductionRecords\Briefing\BriefingVersion;
use ForgeBench\InductionRecords\Capabilities;
use ForgeBench\InductionRecords\Certificate\CertificateIssuer;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Mail\CertificateMailer;
use ForgeBench\InductionRecords\Mail\MemberMessage;
use ForgeBench\InductionRecords\Membership\SubscriptionGate;
use ForgeBench\InductionRecords\Settings;
use WP_User;

/**
 * The induction workflow: a supervisor records an induction, the member
 * confirms it, and confirming issues, archives and emails the certificate.
 */
final class InductionService {

	/**
	 * Clock skew tolerated when a supervisor enters the induction time.
	 */
	private const FUTURE_TOLERANCE = '+5 minutes';

	public function __construct(
		private readonly VersionRepository $versions,
		private readonly SignOffRepository $signoffs,
		private readonly SubscriptionGate $gate,
		private readonly CertificateIssuer $issuer,
		private readonly ArchiveStorage $archive,
		private readonly CertificateMailer $mailer,
		private readonly Settings $settings
	) {
	}

	/**
	 * Records an induction on the machine's current briefing version. The member
	 * is asked by email to confirm it.
	 *
	 * @throws InductionException When the induction cannot be recorded.
	 */
	public function record( int $member_id, int $briefing_id, int $supervisor_id, DateTimeImmutable $inducted_at ): SignOff {
		if ( ! get_userdata( $member_id ) instanceof WP_User ) {
			throw new InductionException( 'unknown_member', __( 'No member account matches that email address or username.', 'induction-records' ) );
		}

		if ( ! user_can( $supervisor_id, Capabilities::RECORD ) ) {
			throw new InductionException( 'not_allowed', __( 'You are not allowed to record inductions.', 'induction-records' ) );
		}

		if ( $member_id === $supervisor_id ) {
			throw new InductionException( 'self_induction', __( 'Supervisors cannot record their own induction. Ask another supervisor.', 'induction-records' ) );
		}

		if ( ! $this->gate->is_eligible( $member_id ) ) {
			throw new InductionException( 'ineligible', __( 'This member has no active subscription, so they cannot be inducted.', 'induction-records' ) );
		}

		$version = $this->versions->latest_for_briefing( $briefing_id );
		if ( null === $version ) {
			throw new InductionException( 'no_version', __( 'This machine has no published briefing version yet.', 'induction-records' ) );
		}

		if ( $inducted_at > new DateTimeImmutable( self::FUTURE_TOLERANCE ) ) {
			throw new InductionException( 'future_date', __( 'The induction time cannot be in the future.', 'induction-records' ) );
		}

		$existing = $this->signoffs->find_active( $member_id, $briefing_id, $version->version->major() );
		if ( null !== $existing ) {
			throw new InductionException(
				'already_inducted',
				SignOffStatus::Pending === $existing->status
					? __( 'This member already has an induction on this machine waiting for their confirmation.', 'induction-records' )
					: __( 'This member already holds a valid induction on this machine.', 'induction-records' )
			);
		}

		$signoff = $this->load( $this->signoffs->create_signoff( $member_id, $version->id, $supervisor_id, $inducted_at, SignOffStatus::Pending ) );

		$message = $this->message( $signoff, $version );
		if ( null !== $message ) {
			$this->mailer->request_confirmation( $message );
		}

		/**
		 * Fires after a supervisor recorded an induction.
		 *
		 * @param SignOff $signoff The new, pending sign-off.
		 */
		do_action( 'induction_records_induction_recorded', $signoff );

		return $signoff;
	}

	/**
	 * Confirms a pending induction for the member it belongs to, then issues,
	 * archives and emails the certificate. Confirming twice is harmless.
	 *
	 * @throws InductionException When the sign-off is not the member's or can no longer be confirmed.
	 */
	public function confirm( int $signoff_id, int $member_id, string $ip_address, string $user_agent ): SignOff {
		$signoff = $this->signoffs->find( $signoff_id );
		if ( null === $signoff || $signoff->member_id !== $member_id ) {
			throw new InductionException( 'not_found', __( 'That induction could not be found.', 'induction-records' ) );
		}

		if ( SignOffStatus::Confirmed === $signoff->status ) {
			return $signoff;
		}

		if ( SignOffStatus::Pending !== $signoff->status ) {
			throw new InductionException( 'not_pending', __( 'This induction can no longer be confirmed because the briefing has changed. Please ask a supervisor to induct you again.', 'induction-records' ) );
		}

		$version = $this->version_of( $signoff );

		$confirmed_at = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$certificate  = $this->issuer->issue( $signoff, $version, $confirmed_at );
		$pdf_path     = $this->archive->store( $signoff->member_id, $certificate->pdf );

		if ( ! $this->signoffs->mark_confirmed( $signoff->id, $confirmed_at, $ip_address, $user_agent, $certificate->rendered_hash, $pdf_path ) ) {
			// A parallel request confirmed it first; keep that request's certificate.
			$this->archive->delete( $pdf_path );
			return $this->load( $signoff->id );
		}

		$this->signoffs->supersede_others( $signoff->member_id, $version->briefing_id, $signoff->id );

		$confirmed = $this->load( $signoff->id );
		$this->send_certificate( $confirmed, $version );

		/**
		 * Fires after a member confirmed an induction and the certificate was issued.
		 *
		 * @param SignOff $signoff The confirmed sign-off.
		 */
		do_action( 'induction_records_induction_confirmed', $confirmed );

		return $confirmed;
	}

	/**
	 * Emails the archived certificate (or scanned paper record) again.
	 *
	 * @return bool Whether wp_mail() accepted the message.
	 * @throws InductionException When the sign-off has no certificate on file.
	 */
	public function resend_certificate( int $signoff_id ): bool {
		$signoff = $this->signoffs->find( $signoff_id );

		if ( null === $signoff || ! $signoff->has_document() || ! $this->archive->exists( (string) $signoff->pdf_path ) ) {
			throw new InductionException( 'no_document', __( 'There is no certificate on file for this induction.', 'induction-records' ) );
		}

		return $this->send_certificate( $signoff, $this->version_of( $signoff ) );
	}

	private function send_certificate( SignOff $signoff, BriefingVersion $version ): bool {
		$message = $this->message( $signoff, $version );

		return null !== $message && $this->mailer->send_certificate( $message );
	}

	private function message( SignOff $signoff, BriefingVersion $version ): ?MemberMessage {
		$member = get_userdata( $signoff->member_id );
		if ( ! $member instanceof WP_User || '' === $member->user_email ) {
			return null;
		}

		return new MemberMessage(
			$signoff->id,
			$member->user_email,
			$member->display_name,
			$version->title,
			$version->version->to_string(),
			$this->settings->member_page_url(),
			$this->settings->organisation_name(),
			$signoff->certificate_id(),
			null !== $signoff->pdf_path ? $this->archive->path( $signoff->pdf_path ) : ''
		);
	}

	private function version_of( SignOff $signoff ): BriefingVersion {
		$version = $this->versions->find( $signoff->briefing_version_id );
		if ( null === $version ) {
			throw new InductionException( 'not_found', __( 'The briefing version of this induction is missing.', 'induction-records' ) );
		}

		return $version;
	}

	private function load( int $signoff_id ): SignOff {
		$signoff = $this->signoffs->find( $signoff_id );
		if ( null === $signoff ) {
			throw new InductionException( 'not_found', __( 'That induction could not be found.', 'induction-records' ) );
		}

		return $signoff;
	}
}
