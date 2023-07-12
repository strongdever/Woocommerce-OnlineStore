<?php

use FKWCS\Gateway\Stripe\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



if ( ! class_exists( 'FKWCS_Compatibility_Common' ) ) {

	class FKWCS_Compatibility_Common {

		public function __construct() {

			add_action( 'fkwcs_webhook_payment_succeed', [ $this, 'maybe_save_webhook_status' ] );
			add_action( 'fkwcs_webhook_payment_on-hold', [ $this, 'maybe_save_webhook_status' ] );
			add_action( 'fkwcs_webhook_payment_failed', [ $this, 'maybe_save_webhook_status' ] );

			add_filter( 'wfocu_front_order_status_after_funnel', array( $this, 'replace_recorded_status_with_ipn_response' ), 10, 2 );
		}


		/**
		 * @param WC_Order $order
		 *
		 * @return void
		 *
		 */
		public function maybe_save_webhook_status( $order ) {

			$current_action     = current_action();
			$is_case_of_webhook = Helper::get_meta(wc_get_order($order->get_id()),'_wfocu_payment_complete_on_hold');

			if ( empty( $is_case_of_webhook ) ) {

				return;
			}

			if ( $current_action === 'fkwcs_webhook_payment_succeed' ) {
				$order->update_meta_data( 'wfocu_stripe_ipn_status', 'succeeded' );

			} elseif ( $current_action === 'fkwcs_webhook_payment_on-hold' ) {
				$order->update_meta_data( 'wfocu_stripe_ipn_status', 'on-hold' );

			} else {
				$order->update_meta_data( 'wfocu_stripe_ipn_status', 'failed' );

			}
			$order->save_meta_data();
		}


		/**
		 * @param $status
		 * @param WC_Order $order
		 */
		public function replace_recorded_status_with_ipn_response( $status, $order ) {

			$get_meta = Helper::get_meta( $order,'wfocu_stripe_ipn_status' );

			if ( empty( $get_meta ) ) {
				return $status;
			}


			switch ( $get_meta ) {
				case 'succeeded':

					return apply_filters( 'woocommerce_payment_complete_order_status', $order->needs_processing() ? 'processing' : 'completed', $order->get_id(), $order );
				case 'on-hold':
					return 'on-hold';
				case 'failed':
				case 'Failed':
				case 'denied':
				case 'Denied':
				case 'Expired':
				case 'expired':
					return 'failed';

			}

			return $status;
		}

	}

	FKWCS_Plugin_Compatibilities::register( new FKWCS_Compatibility_Common(), 'fkwcs_common' );


}


