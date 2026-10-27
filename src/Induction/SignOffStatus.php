<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Induction;

/**
 * Lifecycle of an induction sign-off.
 *
 * pending -> confirmed when the member confirms it. A new major briefing version
 * moves pending and confirmed sign-offs to reinduction_required. When the member
 * is inducted again, their older sign-offs on that machine become superseded.
 */
enum SignOffStatus: string {

	case Pending             = 'pending';
	case Confirmed           = 'confirmed';
	case ReinductionRequired = 'reinduction_required';
	case Superseded          = 'superseded';

	/**
	 * Active sign-offs are the ones a newer major version can invalidate.
	 */
	public function is_active(): bool {
		return self::Pending === $this || self::Confirmed === $this;
	}

	public function label(): string {
		return match ( $this ) {
			self::Pending             => __( 'Awaiting member confirmation', 'induction-records' ),
			self::Confirmed           => __( 'Valid', 'induction-records' ),
			self::ReinductionRequired => __( 'Re-induction required', 'induction-records' ),
			self::Superseded          => __( 'Superseded', 'induction-records' ),
		};
	}
}
