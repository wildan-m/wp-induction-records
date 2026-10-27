<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Membership;

use Closure;

/**
 * Optional WooCommerce Subscriptions gate: when Subscriptions is active, only
 * members with an active subscription can be inducted.
 *
 * Paper records imported with WP-CLI describe past inductions and are not
 * gated. Sites can switch the gate off with the
 * induction_records_require_active_subscription filter, or decide eligibility
 * themselves with induction_records_member_is_eligible.
 */
final class SubscriptionGate {

	/**
	 * @param Closure(int): bool|null $has_active_subscription Null when WooCommerce Subscriptions is not available.
	 */
	public function __construct(
		private readonly ?Closure $has_active_subscription
	) {
	}

	public static function from_environment(): self {
		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			return new self( null );
		}

		return new self(
			// "pending-cancel" subscriptions stay active until the end of the paid period.
			static fn( int $user_id ): bool => (bool) wcs_user_has_subscription( $user_id, '', array( 'active', 'pending-cancel' ) )
		);
	}

	public function is_enforced(): bool {
		return null !== $this->has_active_subscription
			&& (bool) apply_filters( 'induction_records_require_active_subscription', true );
	}

	public function is_eligible( int $user_id ): bool {
		$eligible = true;
		if ( null !== $this->has_active_subscription && $this->is_enforced() ) {
			$eligible = ( $this->has_active_subscription )( $user_id );
		}

		return (bool) apply_filters( 'induction_records_member_is_eligible', $eligible, $user_id );
	}
}
