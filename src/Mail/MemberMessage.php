<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Mail;

/**
 * Data for an email to a member about one of their inductions. Passed to the
 * subject, body and header filters so they can tailor the message.
 */
final class MemberMessage {

	/**
	 * @param string $pdf_path Absolute path of the archived certificate, or '' when there is none yet.
	 */
	public function __construct(
		public readonly int $signoff_id,
		public readonly string $recipient_email,
		public readonly string $recipient_name,
		public readonly string $machine_name,
		public readonly string $version,
		public readonly string $page_url,
		public readonly string $organisation_name,
		public readonly string $certificate_id,
		public readonly string $pdf_path = ''
	) {
	}
}
