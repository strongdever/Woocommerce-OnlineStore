<?php
/**
 * Stripe Webhook Class
 */

namespace FKWCS\Gateway\Stripe;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DateTime;
use Exception as Exception;
use Stripe\Exception\SignatureVerificationException as SignatureException;
use UnexpectedValueException as UnexpectedException;

class Webhook {

	private static $instance = null;

	const FKWCS_LIVE_BEGAN_AT = 'fkwcs_live_webhook_began_at';
	const FKWCS_LIVE_LAST_SUCCESS_AT = 'fkwcs_live_webhook_last_success_at';
	const FKWCS_LIVE_LAST_FAILURE_AT = 'fkwcs_live_webhook_last_failure_at';
	const FKWCS_LIVE_LAST_ERROR = 'fkwcs_live_webhook_last_error';

	const FKWCS_TEST_BEGAN_AT = 'fkwcs_test_webhook_began_at';
	const FKWCS_TEST_LAST_SUCCESS_AT = 'fkwcs_test_webhook_last_success_at';
	const FKWCS_TEST_LAST_FAILURE_AT = 'fkwcs_test_webhook_last_failure_at';
	const FKWCS_TEST_LAST_ERROR = 'fkwcs_test_webhook_last_error';


	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );

		add_action( 'woocommerce_api_wc_stripe', [ $this, 'control_webhook' ] );
		add_filter( 'rest_pre_dispatch', [ $this, 'control_webhook' ], 10, 3 );

	}

	/**
	 * Initiator
	 *
	 * @return Webhook
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * This method simply overrides webhook for the WooCommerce stripe gateway, so that stripe will no longer notify sellers about webhook endpoint returning 400.
	 * @return null|void
	 */
	public function control_webhook( $return = null, $rest = null, $request = null ) { //  phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.VoidReturn,WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement


		if ( current_action() === 'woocommerce_api_wc_stripe' ) {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) || ! isset( $_GET['wc-api'] ) || ( 'wc_stripe' !== $_GET['wc-api'] ) ) { //  phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
		}

		if ( current_filter() === 'rest_pre_dispatch' ) {
			if ( $request->get_route() !== '/wc-stripe/v1/webhook' ) {
				return $return;
			}
		}

		http_response_code( 200 );
		exit();
	}

	/**
	 * Returns message about interaction with Stripe webhook
	 *
	 * @param mixed $mode mode of operation.
	 *
	 * @return string
	 */
	public static function get_webhook_interaction_message( $mode = false ) {
		if ( ! $mode ) {
			$mode = Helper::get_payment_mode();
		}
		$last_success    = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_LAST_SUCCESS_AT' );
		$last_success_at = get_option( $last_success );

		$last_failure    = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_LAST_FAILURE_AT' );
		$last_failure_at = get_option( $last_failure );

		$began    = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_BEGAN_AT' );
		$start_at = get_option( $began );

		$status = 'none';

		if ( $last_success_at && $last_failure_at ) {
			$status = ( $last_success_at >= $last_failure_at ) ? 'success' : 'failure';
		} elseif ( $last_success_at ) {
			$status = 'success';
		} elseif ( $last_failure_at ) {
			$status = 'failure';
		} elseif ( $start_at ) {
			$status = 'began';
		}

		switch ( $status ) {
			case 'success':
				/* translators: time, status */ return sprintf( __( 'Last webhook call was %1$1s. Status : %2$2s', 'funnelkit-stripe-woo-payment-gateway' ), self::time_elapsed_string( gmdate( 'Y-m-d H:i:s e', $last_success_at ) ), '<b>' . ucfirst( $status ) . '</b>' );

			case 'failure':
				$err_const = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_LAST_ERROR' );
				$error     = get_option( $err_const );
				/* translators: error message */
				$reason = ( $error ) ? sprintf( __( 'Reason : %1s', 'funnelkit-stripe-woo-payment-gateway' ), '<b>' . $error . '</b>' ) : '';

				/* translators: time, status, reason */

				return sprintf( __( 'Last webhook call was %1$1s. Status : %2$2s. %3$3s', 'funnelkit-stripe-woo-payment-gateway' ), self::  time_elapsed_string( gmdate( 'Y-m-d H:i:s e', $last_failure_at ) ), '<b>' . ucfirst( $status ) . '</b>', $reason );

			case 'began':
				/* translators: timestamp */ return sprintf( __( 'No webhook call since %1s.', 'funnelkit-stripe-woo-payment-gateway' ), gmdate( 'Y-m-d H:i:s e', $start_at ) );

			default:
				$endpoint_secret = '';
				if ( 'live' === $mode ) {
					$endpoint_secret = get_option( 'fkwcs_live_webhook_secret', '' );
				} elseif ( 'test' === $mode ) {
					$endpoint_secret = get_option( 'fkwcs_test_webhook_secret', '' );
				}
				if ( ! empty( trim( $endpoint_secret ) ) ) {
					$current_time = time();
					update_option( $began, $current_time );

					/* translators: timestamp */

					return sprintf( __( 'No webhook call since %1s.', 'funnelkit-stripe-woo-payment-gateway' ), gmdate( 'Y-m-d H:i:s e', $current_time ) );
				}

				return '';
		}
	}

	/**
	 * Registers endpoint for Stripe webhook
	 *
	 * @return void
	 */
	public function register_endpoints() {
		register_rest_route( 'fkwcs', '/v1/webhook', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'webhook_listener' ],
			'permission_callback' => function () {
				return true;
			},
		) );
	}

	/**
	 * This function listens webhook events from Stripe.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function webhook_listener() {


		$payload = file_get_contents( 'php://input' ); //phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile

		$mode            = $this->get_mode( $payload );
		$endpoint_secret = '';
		if ( 'live' === $mode ) {
			$endpoint_secret = get_option( 'fkwcs_live_webhook_secret' );
		} elseif ( 'test' === $mode ) {
			$endpoint_secret = get_option( 'fkwcs_test_webhook_secret' );
		}

		if ( empty( trim( $endpoint_secret ) ) ) {
			http_response_code( 400 );
			exit();
		}

		$began = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_BEGAN_AT' );

		if ( ! get_option( $began ) ) {
			update_option( $began, time() );
		}

		$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? wc_clean( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) : '';

		try {
			$event = \Stripe\Webhook::constructEvent( $payload, $sig_header, $endpoint_secret );
			Helper::log( 'Webhook data: ' . wp_json_encode( $event->toArray() ) );
		} catch ( UnexpectedException|SignatureException $e ) {
			Helper::log( 'Webhook error : ' . $e->getMessage() . ' Full Payload below: ' . $payload );
			$error_at = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_LAST_FAILURE_AT' );
			update_option( $error_at, time() );
			$error = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_LAST_ERROR' );
			update_option( $error, $e->getMessage() );
			http_response_code( 400 );
			exit();
		}

		Helper::log( 'intent type: ' . $event->type );
		$object = isset( $event->data->object ) ? $event->data->object : null;

		if ( is_null( $object ) ) {
			Helper::log( 'object is null: ' . $event->type );
			http_response_code( 400 );
			exit;
		}

		switch ( $event->type ) {
			case 'charge.captured':
				$this->charge_capture( $object );
				break;
			case 'charge.refunded':
				$this->charge_refund( $object );
				break;
			case 'charge.dispute.created':
				$this->charge_dispute_created( $object );
				break;
			case 'charge.dispute.closed':
				$this->charge_dispute_closed( $object );
				break;
			case 'payment_intent.succeeded':
				$this->payment_intent_succeeded( $object );
				break;
			case 'payment_intent.payment_failed':
				$this->payment_intent_failed( $object );
				break;
			case 'charge.failed':
				$this->charge_failed( $object );
				break;
			case 'review.opened':
				$this->review_opened( $object );
				break;
			case 'review.closed':
				$this->review_closed( $object );
				break;

		}
		$success = constant( 'self::FKWCS_' . strtoupper( $mode ) . '_LAST_SUCCESS_AT' );
		update_option( $success, time() );
		http_response_code( 200 );
		exit;
	}

	/**
	 * Captures charge for un-captured charges via webhook calls
	 *
	 * @param $charge
	 *
	 * @return void
	 */
	public function charge_capture( $charge ) {
		Helper::log( "Charge capture" );
		$order_id = $this->maybe_get_order_id_from_charge( $charge );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via charge ID: ' . $charge->id );

			return;
		}

		try {
			$order = wc_get_order( $order_id );
			if ( 'fkwcs_stripe_sepa' === $order->get_payment_method() ) {
				$order->set_transaction_id( $charge->id );
				$this->make_charge( $charge, $order );
			}
			do_action( 'fkwcs_webhook_event_charge_capture', $charge, $order );
		} catch ( \WC_Data_Exception $exception ) {
			Helper::log( " Charge Failed " . $exception->getMessage() );
		}


	}

	/**
	 * Make charge via webhook call
	 *
	 * @param object $intent Stripe intent object.
	 * @param \WC_Order $order WC order object.
	 *
	 * @return void
	 */
	public function make_charge( $intent, $order ) {
		if ( $intent->amount_refunded > 0 ) {
			$partial_amount = $intent->amount_captured;
			$currency       = strtoupper( $intent->currency );
			$partial_amount = Helper::get_original_amount( $partial_amount, $currency );
			$order->set_total( $partial_amount );
			/* translators: order id */
			Helper::log( sprintf( __( 'Stripe charge partially captured with amount %1$1s Order id - %2$2s', 'funnelkit-stripe-woo-payment-gateway' ), $partial_amount, $order->get_id() ) );
			/* translators: partial captured amount */
			$order->add_order_note( sprintf( __( 'This charge was partially captured via Stripe Dashboard with the amount : %s', 'funnelkit-stripe-woo-payment-gateway' ), $partial_amount ) );
		} else {
			$order->payment_complete( $intent->id );
			/* translators: order id */
			Helper::log( sprintf( __( 'Stripe charge completely captured Order id - %1s', 'funnelkit-stripe-woo-payment-gateway' ), $order->get_id() ) );
			/* translators: transaction id */
			$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'funnelkit-stripe-woo-payment-gateway' ), $intent->id ) );
		}

		if ( isset( $intent->balance_transaction ) ) {
			Helper::update_balance( $order, $intent->balance_transaction );
		}

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
	}

	/**
	 * Refunds WooCommerce order via webhook call
	 *
	 * @param object $charge Stripe Charge object.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function charge_refund( $charge ) {

		Helper::log( "charge refund" );
		$order_id = $this->maybe_get_order_id_from_charge( $charge );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via charge ID: ' . $charge->id );

			return;
		}
		try {
			$order = wc_get_order( $order_id );
			if ( 0 === strpos( $order->get_payment_method(), 'fkwcs_' ) ) {

				$intent = $this->get_intent_from_order( $order );

				if ( empty( $intent ) ) {
					Helper::log( 'Could not find intent in the order ' . $charge->id );

					return;
				}
				if ( $intent !== $charge->payment_intent ) {
					Helper::log( 'Intent in order doesn\'t match with the payload. ' . $charge->id );

					return;
				}
				$transaction_id = $order->get_transaction_id();
				$captured       = $charge->captured;
				$refund_id      = Helper::get_meta($order, '_fkwcs_refund_id' );
				$currency       = strtoupper( $charge->currency );
				$raw_amount     = $charge->refunds->data[0]->amount;

				$raw_amount = Helper::get_original_amount( $raw_amount, $currency );

				$amount = wc_price( $raw_amount, [ 'currency' => $currency ] );

				if ( ! $captured ) {
					if ( 'cancelled' !== $order->get_status() ) {
						/* translators: amount (including currency symbol) */
						$order->add_order_note( sprintf( __( 'Pre-Authorization for %s voided from the Stripe Dashboard.', 'funnelkit-stripe-woo-payment-gateway' ), $amount ) );
						$order->update_status( 'cancelled' );
					}

					return;
				}

				if ( $charge->refunds->data[0]->id === $refund_id ) {
					return;
				}


				if ( $transaction_id ) {

					$reason = __( 'Refunded via Stripe dashboard', 'funnelkit-stripe-woo-payment-gateway' );

					$refund = wc_create_refund( [
						'order_id' => $order_id,
						'amount'   => ( $charge->amount_refunded > 0 ) ? $raw_amount : false,
						'reason'   => $reason,
					] );

					if ( is_wp_error( $refund ) ) {
						Helper::log( $refund->get_error_message() );
					}

					$refund_id = $charge->refunds->data[0]->id;
					set_transient( '_fkwcs_refund_id_cache_' . $order_id, $refund_id, 60 );
					$order->update_meta_data( '_fkwcs_refund_id', $refund_id );
					$order->save_meta_data();
					if ( isset( $charge->refunds->data[0]->balance_transaction ) ) {
						Helper::update_balance( $order, $charge->refunds->data[0]->balance_transaction, true );
					}

					$status      = 'fkwcs_sepa' === $order->get_payment_method() ? __( 'Pending to Success', 'funnelkit-stripe-woo-payment-gateway' ) : __( 'Success', 'funnelkit-stripe-woo-payment-gateway' );
					$refund_time = gmdate( 'Y-m-d H:i:s', time() );
					$order->add_order_note( __( 'Reason : ', 'funnelkit-stripe-woo-payment-gateway' ) . $reason . '.<br>' . __( 'Amount : ', 'funnelkit-stripe-woo-payment-gateway' ) . $amount . '.<br>' . __( 'Status : ', 'funnelkit-stripe-woo-payment-gateway' ) . $status . ' [ ' . $refund_time . ' ] <br>' . __( 'Transaction ID : ', 'funnelkit-stripe-woo-payment-gateway' ) . $refund_id );
					Helper::log( $reason . ' : Amount: ' . get_woocommerce_currency_symbol() . str_pad( $raw_amount, 2, 0 ) . 'Transaction ID :' . $refund_id );
				}
			}
		} catch ( Exception $exception ) {
			Helper::log( $exception->getMessage() );
		}
	}

	/**
	 * Handles charge.dispute.create webhook and changes order status to 'On Hold'
	 *
	 * @param Object $dispute - Stripe webhook object.
	 *
	 * @return void
	 */
	public function charge_dispute_created( $dispute ) {
		$order_id = $this->get_order_id_from_intent_query( $dispute->payment_intent );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via intent ID: ' . $dispute->payment_intent );

			return;
		}

		$order = wc_get_order( $order_id );
		$order->update_status( 'on-hold', __( 'This order is under dispute. Please respond via Stripe dashboard.', 'funnelkit-stripe-woo-payment-gateway' ) );
		$order->update_meta_data( 'fkwcs_status_before_dispute', $order->get_status() );
		self::send_failed_order_email( $order_id );
	}

	/**
	 * Handles charge.dispute.closed webhook and update order status accordingly
	 *
	 * @param object $dispute dispute object received from Stripe webhook.
	 *
	 * @return void
	 */
	public function charge_dispute_closed( $dispute ) {
		Helper::log( 'charge dispute closed' );
		$order_id = $this->get_order_id_from_intent_query( $dispute->payment_intent );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order for dispute ID: ' . $dispute->id );

			return;
		}

		$order   = wc_get_order( $order_id );
		$message = '';
		switch ( $dispute->status ) {
			case 'lost':
				$message = __( 'The disputed order lost or accepted.', 'funnelkit-stripe-woo-payment-gateway' );
				break;

			case 'won':
				$message = __( 'The disputed order resolved in your favour.', 'funnelkit-stripe-woo-payment-gateway' );
				break;

			case 'warning_closed':
				$message = __( 'The inquiry or retrieval closed.', 'funnelkit-stripe-woo-payment-gateway' );
				break;
		}

		$status = 'lost' === $dispute->status ? 'failed' : Helper::get_meta($order, 'fkwcs_status_before_dispute' );
		$order->update_status( $status, $message );
	}

	/**
	 * Handles webhook call of event payment_intent.succeeded
	 *
	 * @param object $intent intent object received from Stripe.
	 *
	 * @return void
	 */
	public function payment_intent_succeeded( $intent ) {
		$order_id = $this->maybe_get_order_id_from_intent( $intent );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via payment intent: ' . $intent->id );

			return;
		}

		$order = wc_get_order( $order_id );
		do_action( 'fkwcs_webhook_event_intent_succeeded', $intent, $order );

		if ( 'fkwcs_stripe' === $order->get_payment_method() && false === Helper::get_meta($order,'_fkwcs_maybe_check_for_auth')) {
			return;
		}

		if ( 'manual' === $intent->capture_method && 0 === strpos( $order->get_payment_method(), 'fkwcs_' ) ) {
			$this->make_charge( $intent, $order );
		} else {
			if ( ! $order->has_status( [ 'pending', 'failed', 'on-hold', 'wfocu-pri-order' ] ) ) {
				return;
			}
			Helper::log( "Webhook Source Id: " . $intent->payment_method . " Customer: " . $intent->customer );

			$order->update_meta_data( '_fkwcs_source_id', $intent->payment_method );
			$order->update_meta_data( '_fkwcs_customer_id', $intent->customer );
			$order->save_meta_data();

			$charge = end( $intent->charges->data );
			/* translators: transaction id, order id */
			Helper::log( "Webhook: Stripe PaymentIntent $charge->id succeeded for order $order_id" );
			$this->process_response( $charge, $order );
		}
	}

	/**
	 * Handled Charge Failed Webhook Event
	 *
	 * @param $charge Object
	 *
	 * @return void
	 */
	public function charge_failed( $charge ) {
		Helper::log( 'Charge Failed Webhook Event' );

		$order_id = $this->maybe_get_order_id_from_charge( $charge );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via payment: ' . $charge->payment_intent );

			return;
		}

		$order = wc_get_order( $order_id );
		$mode  = get_option( 'fkwcs_mode', 'test' );
		if ( 'live' === $mode ) {
			$client_secret = get_option( 'fkwcs_secret_key' );
		} else {
			$client_secret = get_option( 'fkwcs_test_secret_key' );
		}

		if ( empty( $client_secret ) ) {
			return;
		}


		$client   = new Client( $client_secret );
		$response = $client->payment_intents( 'retrieve', [ $charge->payment_intent ] );
		$intent   = $response['success'] ? $response['data'] : false;
		if ( false === $intent ) {
			$order->update_status( 'failed', __( 'Payment Intent not found', 'funnelkit-stripe-woo-payment-gateway' ) );

			return;
		}

		/* translators: The error message that was received from Stripe. */
		$error_message = isset( $intent->last_payment_error ) ? sprintf( __( 'Reason: %s', 'funnelkit-stripe-woo-payment-gateway' ), $intent->last_payment_error->message ) : '';
		/* translators: The error message that was received from Stripe. */
		$message = $error_message;
		$order->update_status( 'failed', $message );

		$this->send_failed_order_email( $order_id );
		do_action( 'fkwcs_webhook_payment_failed', $order );

	}

	/**
	 * Handles webhook call payment_intent.payment_failed
	 *
	 * @param  $intent Object Stripe webhook object.
	 *
	 * @return void
	 */
	public function payment_intent_failed( $intent ) {
		Helper::log( 'Payment intent failed' );
		$order_id = $this->maybe_get_order_id_from_intent( $intent );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via payment intent: ' . $intent->id );

			return;
		}

		$order = wc_get_order( $order_id );
		/* translators: The error message that was received from Stripe. */
		$error_message = $intent->last_payment_error ? sprintf( __( 'Reason: %s', 'funnelkit-stripe-woo-payment-gateway' ), $intent->last_payment_error->message ) : '';
		/* translators: The error message that was received from Stripe. */
		$message = sprintf( __( 'Stripe SCA authentication failed. %s', 'funnelkit-stripe-woo-payment-gateway' ), $error_message );
		$order->update_status( 'failed', $message );

		$this->send_failed_order_email( $order_id );
		do_action( 'fkwcs_webhook_payment_failed', $order );

	}

	/**
	 * Handles review.opened webhook
	 *
	 * @param $review - Stripe webhook object.
	 *
	 * @return void
	 */
	public function review_opened( $review ) {
		Helper::log( 'Review opened' );
		$payment_intent = sanitize_text_field( $review->payment_intent );
		$order_id       = $this->get_order_id_from_intent_query( $payment_intent );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via review ID: ' . $review->id );

			return;
		}

		$order = wc_get_order( $order_id );
		$order->update_status( 'on-hold', __( 'This order is under review. Please respond via stripe dashboard.', 'funnelkit-stripe-woo-payment-gateway' ) );
		$order->update_meta_data( 'fkwcs_status_before_review', $order->get_status() );
		$this->send_failed_order_email( $order_id );
	}

	/**
	 * Handles review.closed webhook
	 *
	 * @param $review - Stripe webhook object.
	 *
	 * @return void
	 */
	public function review_closed( $review ) {
		Helper::log( 'review closed' );
		$payment_intent = sanitize_text_field( $review->payment_intent );
		$order_id       = $this->get_order_id_from_intent_query( $payment_intent );
		if ( ! $order_id ) {
			Helper::log( 'Could not find order via review ID: ' . $review->id );

			return;
		}

		$order = wc_get_order( $order_id );
		/* translators: Review reason from Stripe */
		$message = sprintf( __( 'Review for this order has been resolved. Reason: %s', 'funnelkit-stripe-woo-payment-gateway' ), $review->reason );
		$order->update_status( Helper::get_meta( $order,'fkwcs_status_before_review' ), $message );
	}

	/**
	 * Fetch WooCommerce order id from payment intent
	 *
	 * @param string $payment_intent payment intent received from Stripe.
	 *
	 * @return string|null order id.
	 */
	public function get_order_id_from_intent_query( $payment_intent ) {
		global $wpdb;

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {

			$order_ids = wc_get_orders( [
				'type'       => 'shop_order',
				'limit'      => 1,
				'return'     => 'ids',
				'meta_query' => [
					[
						'key'   => '_fkwcs_intent_id',
						'value' => $payment_intent
					]
				]
			] );
			$order_id  = ! empty( $order_ids ) ? $order_ids[0] : null;

		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts AS posts LEFT JOIN $wpdb->postmeta AS postmeta ON posts.ID = postmeta.post_id WHERE posts.post_type = %s AND postmeta.meta_key = %s AND postmeta.meta_value LIKE %s LIMIT 1", 'shop_order', '_fkwcs_intent_id', '%'.$payment_intent.'%' ) );
		}


		return $order_id;


	}

	/**
	 * Sends order failure email.
	 *
	 * @param int $order_id WooCommerce order id.
	 *
	 * @return void
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Shows time difference as  - XX minutes ago.
	 *
	 * @param String $datetime time of last event.
	 * @param boolean $full show full time difference.
	 *
	 * @return string
	 */
	public static function time_elapsed_string( $datetime, $full = false ) {
		try {
			$current = new DateTime();
			$ago     = new DateTime( $datetime );
			$diff    = $current->diff( $ago );

			$diff->w = floor( $diff->d / 7 );
			$diff->d -= $diff->w * 7;

			$string = array(
				'y' => 'year',
				'm' => 'month',
				'w' => 'week',
				'd' => 'day',
				'h' => 'hour',
				'i' => 'minute',
				's' => 'second',
			);
			foreach ( $string as $k => &$v ) {
				if ( $diff->$k ) {
					$v = $diff->$k . ' ' . $v . ( $diff->$k > 1 ? 's' : '' );
				} else {
					unset( $string[ $k ] );
				}
			}

			if ( ! $full ) {
				$string = array_slice( $string, 0, 1 );
			}

			return $string ? implode( ', ', $string ) . ' ago' : 'just now';
		} catch ( Exception $e ) {
			return 'just now';
		}

	}

	/**
	 * Process response for saved cards
	 *
	 * @param object $response intent response.
	 * @param \WC_Order $order order response.
	 *
	 * @return Object
	 */
	public function process_response( $response, $order ) {

		$order_id = $order->get_id();
		$captured = ( isset( $response->captured ) && $response->captured ) ? 'yes' : 'no';

		$order->update_meta_data( '_fkwcs_charge_captured', $captured );

		if ( isset( $response->balance_transaction ) ) {
			Helper::update_balance( $order, $response->balance_transaction );
		}

		if ( 'yes' === $captured ) {
			/**
			 * Charge can be captured but in a pending state. Payment methods
			 * that are asynchronous may take couple days to clear. Webhook will
			 * take care of the status changes.
			 */
			if ( 'pending' === $response->status || 'processing' === $response->status ) {
				$order_stock_reduced = Helper::get_meta( $order,'_order_stock_reduced' );

				if ( ! $order_stock_reduced ) {
					wc_reduce_stock_levels( $order_id );
				}

				$order->set_transaction_id( $response->id );
				$others_info = 'fkwcs_stripe_sepa' === $order->get_payment_method() ? __( 'Payment will be completed once payment_intent.succeeded webhook received from Stripe.', 'funnelkit-stripe-woo-payment-gateway' ) : '';

				/* translators: transaction id, other info */
				$order->update_status( 'on-hold', sprintf( __( 'Stripe charge awaiting payment: %1$s. %2$s', 'funnelkit-stripe-woo-payment-gateway' ), $response->id, $others_info ) );
			}

			if ( 'succeeded' === $response->status ) {
				$order->payment_complete( $response->id );

				do_action( 'fkwcs_webhook_payment_succeed', $order );
				/* translators: transaction id */
				$message = sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'funnelkit-stripe-woo-payment-gateway' ), $response->id );
				Helper::log( $message );
				$order->add_order_note( $message );
			}

			if ( 'failed' === $response->status ) {
				$message = __( 'Payment processing failed. Please retry.', 'funnelkit-stripe-woo-payment-gateway' );
				Helper::log( $message );
				$order->add_order_note( $message );
			}
		} else {
			$order->set_transaction_id( $response->id );

			if ( $order->has_status( [ 'pending', 'failed', 'on-hold' ] ) ) {
				wc_reduce_stock_levels( $order_id );
			}

			/* translators: transaction id */
			$order_info = 'fkwcs_stripe_sepa' === $order->get_payment_method() ? sprintf( __( 'Stripe charge awaiting payment: %1$s. Payment will be completed once payment_intent.succeeded webhook received from Stripe.', 'funnelkit-stripe-woo-payment-gateway' ), $response->id ) : sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization. Attempting to refund the order in part or in full will release the authorization and cancel the payment.', 'funnelkit-stripe-woo-payment-gateway' ), $response->id );

			$order->update_status( 'on-hold', $order_info );
			do_action( 'fkwcs_webhook_payment_on-hold', $order );

		}

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}

		do_action( 'fkwcs_process_response', $response, $order );

		return $response;
	}

	public function maybe_get_order_id_from_charge( $charge ) {

		if ( isset( $charge->metadata->order_id ) ) {
			$order = wc_get_order( $charge->metadata->order_id );
			if ( $order ) {
				return $charge->metadata->order_id;
			}
		}

		return $this->get_order_id_from_intent_query( $charge->payment_intent );


	}


	/**
	 * Maybe get order ID from the intent object
	 *
	 * @param Object $intent
	 *
	 * @return mixed|string|null
	 */
	public function maybe_get_order_id_from_intent( $intent ) {

		if ( isset( $intent->metadata->order_id ) ) {
			$order = wc_get_order( $intent->metadata->order_id );
			if ( $order ) {
				return $intent->metadata->order_id;
			}
		}

		return $this->get_order_id_from_intent_query( $intent->id );

	}


	/**
	 * Method to get dynamic live mode from the payload data, local settings as fallback
	 *
	 * @param string $payload
	 *
	 * @return string live on live and test on test mode
	 */
	public function get_mode( $payload ) {

		if ( empty( $payload ) ) {
			return Helper::get_payment_mode();
		}
		$json_payload = json_decode( $payload, true );
		if ( ! is_array( $json_payload ) || ! array_key_exists( 'livemode', $json_payload ) ) {
			return Helper::get_payment_mode();
		}

		return $json_payload['livemode'] ? 'live' : "test";
	}

	/**
	 * Get Intent ID from the order
	 * this method takes care of all the other compatibility keys for the intent and return all possible results
	 *
	 * @param \WC_Order $order
	 *
	 * @return false|mixed
	 */
	public function get_intent_from_order( $order ) {
		$value = Helper::get_meta($order, '_fkwcs_intent_id' );
		if ( ! empty( $value ) ) {
			return $value['id'];
		}
		$keys = Helper::get_compatibility_keys( '_fkwcs_intent_id' );

		foreach ( $keys as $key ) {
			$value = Helper::get_meta( $order,$key );
			if ( ! empty( $value ) ) {
				return $value;
			}
		}

		return false;

	}
}
