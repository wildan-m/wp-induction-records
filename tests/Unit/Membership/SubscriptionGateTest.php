<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Tests\Unit\Membership;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use ForgeBench\InductionRecords\Membership\SubscriptionGate;
use ForgeBench\InductionRecords\Tests\Unit\TestCase;

final class SubscriptionGateTest extends TestCase {

	public function test_everyone_is_eligible_without_woocommerce_subscriptions(): void {
		$gate = new SubscriptionGate( null );

		$this->assertFalse( $gate->is_enforced() );
		$this->assertTrue( $gate->is_eligible( 42 ) );
	}

	public function test_members_need_an_active_subscription_when_the_gate_applies(): void {
		$gate = new SubscriptionGate( static fn( int $user_id ): bool => 42 === $user_id );

		$this->assertTrue( $gate->is_enforced() );
		$this->assertTrue( $gate->is_eligible( 42 ) );
		$this->assertFalse( $gate->is_eligible( 7 ) );
	}

	public function test_the_gate_can_be_switched_off(): void {
		Filters\expectApplied( 'induction_records_require_active_subscription' )->andReturn( false );

		$gate = new SubscriptionGate( static fn(): bool => false );

		$this->assertFalse( $gate->is_enforced() );
		$this->assertTrue( $gate->is_eligible( 7 ) );
	}

	public function test_sites_can_decide_eligibility_themselves(): void {
		Filters\expectApplied( 'induction_records_member_is_eligible' )->once()->with( true, 7 )->andReturn( false );

		$this->assertFalse( ( new SubscriptionGate( null ) )->is_eligible( 7 ) );
	}

	public function test_checks_active_and_pending_cancellation_subscriptions(): void {
		Functions\expect( 'wcs_user_has_subscription' )
			->once()
			->with( 42, '', array( 'active', 'pending-cancel' ) )
			->andReturn( true );

		$this->assertTrue( SubscriptionGate::from_environment()->is_eligible( 42 ) );
	}
}
