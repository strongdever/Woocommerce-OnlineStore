<?php

namespace FKWCS\Gateway\Stripe;

class Bancontact extends Abstract_Payment_Gateway {

	/**
	 * Gateway id
	 *
	 * @var string
	 */
	public $id = 'fkwcs_stripe_bancontact';
	public $payment_method_types = 'bancontact';

	public function __construct() {
		parent::__construct();
		$this->init_supports();
	}

	/**
	 * Setup general properties and settings
	 *
	 * @return void
	 */
	protected function init() {
		$this->method_title       = __( 'Stripe Bancontact Gateway', 'funnelkit-stripe-woo-payment-gateway' );
		$this->method_description = __( 'Accepts payments via Bancontact. The gateway should be enabled in your Stripe Account. Log into your Stripe account to review the <a href="https://dashboard.stripe.com/account/payments/settings" target="_blank">available gateways</a> <br/>Supported Currency: <strong>EUR</strong>', 'funnelkit-stripe-woo-payment-gateway' );

		$this->subtitle   = __( 'Bancontact is the most popular online payment method in Belgium and over 15 million cards in circulation', 'funnelkit-stripe-woo-payment-gateway' );
		$this->has_fields = true;
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
	}

	/**
	 * Add hooks
	 *
	 * @return void
	 */
	protected function filter_hooks() {
		add_filter( 'woocommerce_payment_successful_result', [ $this, 'modify_successful_payment_result' ], 999, 2 );
	}

	/**
	 * Registers supported filters for payment gateway
	 *
	 * @return void
	 */
	public function init_supports() {
		$this->supports = apply_filters( 'fkwcs_bancontact_payment_supports', array_merge( $this->supports, [
			'products',
			'refunds',
		] ) );
	}

	/**
	 * Initialise gateway settings form fields
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$settings = [


			'enabled'     => [
				'label'   => ' ',
				'type'    => 'checkbox',
				'title'   => __( 'Enable Stripe Bancontact Gateway', 'funnelkit-stripe-woo-payment-gateway' ),
				'default' => 'no',
			],
			'title'       => [
				'title'       => __( 'Title', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Change the payment gateway title that appears on the checkout.', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => __( 'Stripe Bancontact', 'funnelkit-stripe-woo-payment-gateway' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Description', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'textarea',
				'css'         => 'width:25em',
				'description' => __( 'Change the payment gateway description that appears on the checkout.', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => __( 'Pay with Bancontact', 'funnelkit-stripe-woo-payment-gateway' ),
				'desc_tip'    => true,
			]
		];

		$this->form_fields = apply_filters( 'fkwcs_bancontact_payment_form_fields', array_merge( $settings, $this->get_countries_admin_fields( 'specific', [], [ 'BE' ] ) ) );
	}

	/**
	 * Returns all supported currencies for this payment method
	 *
	 * @return mixed|null
	 */
	public function get_supported_currency() {
		return apply_filters( 'wc_stripe_bancontact_supported_currencies', [
			'EUR',
		] );
	}

	/**
	 * Checks if payment method available
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->is_available_local_gateway();
	}

	/**
	 * Get payment gateway icons
	 *
	 * @return mixed|string|null
	 */
	public function get_icon() {
		$icons     = $this->payment_icons();
		$icons_str = '';
		$icons_str .= ! empty( $icons['bancontact'] ) ? $icons['bancontact'] : '';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force payment source to be saved.
	 *
	 * @return array|void
	 * @throws \Exception If payment will not be accepted.
	 *
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
		try {
			$order = wc_get_order( $order_id );

			/** This will throw exception if not valid */
			$this->validate_minimum_order_amount( $order );
			$customer_id     = $this->get_customer_id( $order );
			$idempotency_key = $order->get_order_key() . time();
			$data            = [
				'amount'               => $this->get_formatted_amount( $order->get_total() ),
				'currency'             => $this->get_currency(),
				'description'          => $this->get_order_description( $order ),
				'metadata'             => $this->get_metadata( $order_id ),
				'payment_method_types' => [ $this->payment_method_types ],
				'customer'             => $customer_id,
			];

			$data['metadata'] = $this->add_metadata( $order );
			$data             = $this->set_shipping_data( $data, $order );

			$data['statement_descriptor'] = $this->clean_statement_descriptor( Helper::get_gateway_descriptor() );


			$intent_data = $this->get_payment_intent( $order, $idempotency_key, $data );

			Helper::log( sprintf( __( 'Begin processing payment with Bancontact for order %1$1s for the amount of %2$2s', 'funnelkit-stripe-woo-payment-gateway' ), $order_id, $order->get_total() ) );

			if ( $intent_data ) {
				/**
				 * @see modify_successful_payment_result()
				 * This modifies the final response return in WooCommerce process checkout request
				 */
				$return_url = $this->get_return_url( $order );

				return [
					'result'              => 'success',
					'fkwcs_redirect'      => $return_url,
					'fkwcs_intent_secret' => $intent_data->client_secret,
				];
			} else {
				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			}
		} catch ( \Exception $e ) {
			Helper::log( $e->getMessage(), 'warning' );
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Append the urlencode stripe intentSecret data with existing
	 * WooCommerce Redirect url for 3ds Verification.
	 *
	 * @param $result
	 * @param $order_id Int Order ID
	 *
	 * @return array
	 */
	public function modify_successful_payment_result( $result, $order_id ) {
		if ( empty( $order_id ) ) {
			return $result;
		}

		$order = wc_get_order( $order_id );
		if ( $this->id !== $order->get_payment_method() ) {
			return $result;
		}

		if ( ! isset( $result['fkwcs_intent_secret'] ) && ! isset( $result['fkwcs_setup_intent_secret'] ) ) {
			return $result;
		}

		/** Put the final thank you page redirect into the verification URL */
		$verification_url = add_query_arg( [
			'order'                 => $order_id,
			'confirm_payment_nonce' => wp_create_nonce( 'fkwcs_stripe_confirm_payment_intent' ),
			'fkwcs_redirect_to'     => rawurlencode( $result['fkwcs_redirect'] ),
		], \WC_AJAX::get_endpoint( 'fkwcs_stripe_bancontact_verify_payment_intent' ) );

		if ( isset( $result['fkwcs_setup_intent_secret'] ) ) {
			$redirect = sprintf( '#fkwcs-confirm-si-%s:%s:%d:%s', $result['fkwcs_setup_intent_secret'], rawurlencode( $verification_url ), $order->get_id(), $this->id );
		} else {
			$redirect = sprintf( '#fkwcs-confirm-pi-%s:%s:%d:%s', $result['fkwcs_intent_secret'], rawurlencode( $verification_url ), $order->get_id(), $this->id );
		}

		return [
			'result'   => 'success',
			'redirect' => $redirect,
		];
	}

	/**
	 * Verify intent secret and redirect to the thankyou page
	 *
	 * @return void
	 */
	public function verify_intent() {
		global $woocommerce;

		try {

			if ( ! isset( $_GET['confirm_payment_nonce'] ) || false === wp_verify_nonce( wc_clean( $_GET['confirm_payment_nonce'] ), 'fkwcs_stripe_confirm_payment_intent' ) ) {
				throw new \Exception( __( 'Nonce verification failed.', 'funnelkit-stripe-woo-payment-gateway' ) );
			}

			$order_id = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect = isset( $_GET['fkwcs_redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['fkwcs_redirect_to'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order    = wc_get_order( $order_id );
		} catch ( \Exception $e ) {
			/* translators: Error message text */
			$message = sprintf( __( 'Payment verification error: %s', 'funnelkit-stripe-woo-payment-gateway' ), $e->getMessage() );
			wc_add_notice( esc_html( $message ), 'error' );
			$redirect_url = $woocommerce->cart->is_empty() ? get_permalink( wc_get_page_id( 'shop' ) ) : wc_get_checkout_url();
			$this->handle_error( $e, $redirect_url );
		}

		try {
			$intent = $this->get_intent_from_order( $order );

			if ( false === $intent ) {
				throw new \Exception( 'Intent Not Found' );
			}

			if ( 'setup_intent' === $intent->object && 'succeeded' === $intent->status ) {
				$order->payment_complete();
				WC()->cart->empty_cart();

			} else if ( 'succeeded' === $intent->status || 'requires_capture' === $intent->status ) {
				$redirect_to = $this->process_final_order( end( $intent->charges->data ), $order_id );
			} else {
				$redirect = $woocommerce->cart->is_empty() ? get_permalink( wc_get_page_id( 'shop' ) ) : wc_get_checkout_url();
			}

			$redirect_url =  ! empty( $redirect ) ? $redirect : $redirect_to;

		} catch ( \Exception $e ) {
			$redirect_url = $woocommerce->cart->is_empty() ? get_permalink( wc_get_page_id( 'shop' ) ) : wc_get_checkout_url();
			wc_add_notice( esc_html( $e->getMessage() ), 'error' );
		}
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Save Meta Data Like Balance Charge ID & status
	 * Add respective  order notes according to stripe charge status
	 *
	 * @param $response
	 * @param $order_id Int Order ID
	 *
	 * @return string
	 */
	public function process_final_order( $response, $order_id ) {
		$order = wc_get_order( $order_id );

		if ( isset( $response->balance_transaction ) ) {
			Helper::update_balance( $order, $response->balance_transaction );
		}

		if ( true === $response->captured ) {
			$order->payment_complete( $response->id );
			/* translators: order id */
			Helper::log( sprintf( 'Payment successful Order id - %1s', $order->get_id() ) );

			$order->add_order_note( __( 'Payment Status: ', 'funnelkit-stripe-woo-payment-gateway' ) . ucfirst( $response->status ) . ', ' . __( 'Source: Payment is Completed via ', 'funnelkit-stripe-woo-payment-gateway' ) . $response->payment_method_details->bancontact->iban_last4 . '(' . $response->payment_method_details->bancontact->bank_name . ')' );
			$order->add_order_note( __( 'Charge ID ' . $response->id ) );
		} else {
			/* translators: transaction id */
			$order->update_status( 'on-hold', sprintf( __( 'Charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization. Attempting to refund the order in part or in full will release the authorization and cancel the payment.', 'funnelkit-stripe-woo-payment-gateway' ), $response->id ) );
			/* translators: transaction id */
			Helper::log( sprintf( 'Charge authorized Order id - %1s', $order->get_id() ) );
		}

		WC()->cart->empty_cart();
		$return_url = $this->get_return_url( $order );
		Helper::log( "Return URL: $return_url" );

		return $return_url;
	}

	/**
	 * Print the gateway field
	 *
	 * @return void
	 */
	public function payment_fields() {
		do_action( $this->id . '_before_payment_field_checkout' );
		include __DIR__ . '/parts/bancontact.php';
		do_action( $this->id . '_after_payment_field_checkout' );
	}
}
