<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Mail;

/**
 * Sends induction emails through wp_mail().
 *
 * Subjects, bodies and headers are filterable:
 * induction_records_certificate_email_subject, induction_records_certificate_email_body,
 * induction_records_confirmation_email_subject, induction_records_confirmation_email_body
 * and induction_records_email_headers. Each filter also receives the MemberMessage.
 */
final class CertificateMailer {

	/**
	 * Emails the archived certificate to the member as a PDF attachment.
	 *
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public function send_certificate( MemberMessage $message ): bool {
		if ( '' === $message->pdf_path ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: machine name, 2: certificate reference */
			__( 'Your %1$s induction certificate (%2$s)', 'induction-records' ),
			$message->machine_name,
			$message->certificate_id
		);

		$body = $this->paragraphs(
			array(
				/* translators: %s: member name */
				sprintf( __( 'Hi %s,', 'induction-records' ), $message->recipient_name ),
				sprintf(
					/* translators: 1: machine name, 2: briefing version, 3: certificate reference */
					__( 'Your induction on the %1$s (briefing version %2$s) is complete. Certificate %3$s is attached to this email.', 'induction-records' ),
					$message->machine_name,
					$message->version,
					$message->certificate_id
				),
				/* translators: %s: URL of the member's inductions page */
				sprintf( __( 'You can download your certificates at any time from your inductions page: %s', 'induction-records' ), $message->page_url ),
				$message->organisation_name,
			)
		);

		$subject = (string) apply_filters( 'induction_records_certificate_email_subject', $subject, $message );
		$body    = (string) apply_filters( 'induction_records_certificate_email_body', $body, $message );
		$headers = (array) apply_filters( 'induction_records_email_headers', array(), 'certificate', $message );

		// The archive uses random file names, so give the attachment a readable one.
		$attachments = array(
			sprintf( 'induction-certificate-%s.pdf', $message->certificate_id ) => $message->pdf_path,
		);

		return wp_mail( $message->recipient_email, $subject, $body, $headers, $attachments );
	}

	/**
	 * Asks the member to read the briefing and confirm an induction a supervisor recorded.
	 *
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public function request_confirmation( MemberMessage $message ): bool {
		$subject = sprintf(
			/* translators: %s: machine name */
			__( 'Please confirm your %s induction', 'induction-records' ),
			$message->machine_name
		);

		$body = $this->paragraphs(
			array(
				/* translators: %s: member name */
				sprintf( __( 'Hi %s,', 'induction-records' ), $message->recipient_name ),
				sprintf(
					/* translators: 1: machine name, 2: briefing version */
					__( 'A supervisor has recorded your induction on the %1$s (briefing version %2$s). Please read the briefing and confirm the induction on your inductions page. Your certificate is issued as soon as you confirm.', 'induction-records' ),
					$message->machine_name,
					$message->version
				),
				$message->page_url,
				$message->organisation_name,
			)
		);

		$subject = (string) apply_filters( 'induction_records_confirmation_email_subject', $subject, $message );
		$body    = (string) apply_filters( 'induction_records_confirmation_email_body', $body, $message );
		$headers = (array) apply_filters( 'induction_records_email_headers', array(), 'confirmation', $message );

		return wp_mail( $message->recipient_email, $subject, $body, $headers );
	}

	/**
	 * @param list<string> $paragraphs
	 */
	private function paragraphs( array $paragraphs ): string {
		return implode( "\n\n", array_filter( $paragraphs, static fn( string $paragraph ): bool => '' !== trim( $paragraph ) ) );
	}
}
