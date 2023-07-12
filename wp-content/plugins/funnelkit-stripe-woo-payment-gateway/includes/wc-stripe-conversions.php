<?php

namespace FKWCS\Gateway;

class WC_Stripe_Conversions {

	public static function init() {
		add_filter( 'woocommerce_order_get_payment_method', array( __CLASS__, 'change_payment_method' ), 99, 2 );
		add_filter( 'woocommerce_subscription_get_payment_method', array( __CLASS__, 'change_payment_method' ), 99, 2 );
	}

	/**
	 *
	 * @param string $payment_method Like stripe,stripe_cc
	 */
	public static function change_payment_method( $payment_method ) {

		if ( true === self::maybe_prevent_change_method() ) {
			return $payment_method;
		}
		switch ( $payment_method ) {
			case 'stripe':
			case 'stripe_cc':
			case 'stripe_applepay':
			case 'stripe_googlepay':
				if ( did_action( 'woocommerce_checkout_order_processed' ) ) {
					return $payment_method;
				}
				$payment_method = 'fkwcs_stripe';
				break;
		}

		return $payment_method;
	}

	private static function maybe_prevent_change_method() {

		if ( isset( $_GET['wc-ajax'] ) && 'wc_stripe_frontend_request' === wc_clean( $_GET['wc-ajax'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return true;
		}

		return false;
	}
}

WC_Stripe_Conversions::init();
