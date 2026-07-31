<?php
/**
 * Lightweight CE access rule assertions (run: php scripts/test-ce-access-rules.php).
 * Does not boot WordPress — validates pure decision helpers mirrored from CTA_CE_Access.
 *
 * @package CTA_LMS
 */

// Mirror of CTA_CE_Access::evaluate-style decisions for CI-free sanity checks.

function cta_test_purchase_always_active( $has_purchase_payment, $enrollment_status ) {
	if ( ! $has_purchase_payment ) {
		return false;
	}
	return in_array( $enrollment_status, array( 'active', 'completed' ), true );
}

function cta_test_membership_active( $source, $status, $expires_at, $now, $membership_live ) {
	if ( 'revoked' === $status ) {
		return false;
	}
	if ( ! in_array( $status, array( 'active', 'completed' ), true ) ) {
		return false;
	}
	if ( 'purchase' === $source ) {
		return true;
	}
	if ( $expires_at ) {
		return strtotime( $expires_at ) > strtotime( $now );
	}
	return (bool) $membership_live;
}

$fail = 0;

// Scenario A: individual purchase → permanent.
if ( ! cta_test_purchase_always_active( true, 'active' ) ) {
	echo "FAIL A1\n";
	++$fail;
}
if ( ! cta_test_purchase_always_active( true, 'completed' ) ) {
	echo "FAIL A2\n";
	++$fail;
}

// Scenario B: membership only → inactive when membership ends.
if ( cta_test_membership_active( 'membership', 'active', '2020-01-01 00:00:00', '2026-07-31 00:00:00', false ) ) {
	echo "FAIL B1 expired annual still active\n";
	++$fail;
}
if ( cta_test_membership_active( 'membership', 'revoked', null, '2026-07-31 00:00:00', false ) ) {
	echo "FAIL B2 revoked still active\n";
	++$fail;
}
if ( ! cta_test_membership_active( 'membership', 'active', null, '2026-07-31 00:00:00', true ) ) {
	echo "FAIL B3 live subscription should be active\n";
	++$fail;
}

// Scenario C: purchase + lapsed membership → still active via purchase.
if ( ! cta_test_purchase_always_active( true, 'completed' ) ) {
	echo "FAIL C1\n";
	++$fail;
}
if ( ! cta_test_membership_active( 'purchase', 'completed', '2020-01-01 00:00:00', '2026-07-31 00:00:00', false ) ) {
	echo "FAIL C2 purchase source ignores expiry\n";
	++$fail;
}

if ( 0 === $fail ) {
	echo "OK all CE access rule scenarios passed\n";
	exit( 0 );
}

echo "FAILED {$fail} assertion(s)\n";
exit( 1 );
