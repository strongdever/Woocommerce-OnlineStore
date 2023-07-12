<?php

namespace FKWCS\Gateway\Stripe;

use WC_Payment_Tokens;
use FKWCS\Gateway\Stripe\Traits\WC_Subscriptions_Trait;
use WC_HTTPS;

class CreditCard extends Abstract_Payment_Gateway {

	use WC_Subscriptions_Trait;

	/**
	 * Gateway id
	 *
	 * @var string
	 */
	public $id = 'fkwcs_stripe';
	public $token = false;
	public $payment_method_types = 'card';
	private static $instance = null;

	public function __construct() {
		parent::__construct();
		$this->init_supports();
	}

	/**
	 * @return CreditCard gateway instance
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup general properties and settings
	 *
	 * @return void
	 */
	protected function init() {
		$this->method_title       = __( 'Stripe Gateway', 'funnelkit-stripe-woo-payment-gateway' );
		$this->method_description = __( ' Accepts payments via Credit or Debit Cards. The gateway supports all popular Card brands. <br/>Use Allowed Card Brands to set up brands as per your choice. ', 'funnelkit-stripe-woo-payment-gateway' );
		$this->subtitle           = __( 'Let your customers pay with major credit and debit cards without leaving your store', 'funnelkit-stripe-woo-payment-gateway' );
		$this->has_fields         = true;
		$this->init_form_fields();
		$this->init_settings();
		$this->maybe_init_subscriptions();
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->inline_cc          = $this->get_option( 'inline_cc' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->enable_saved_cards = $this->get_option( 'enable_saved_cards' );
		$this->capture_method     = $this->get_option( 'charge_type' );
		$this->allowed_cards      = $this->get_option( 'allowed_cards' );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_stripe_js' ] );

		add_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'filter_saved_tokens' ], 10, 3 );
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
		$this->supports = apply_filters( 'fkwcs_card_payment_supports', array_merge( $this->supports, [
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
			'pre-orders',
		] ) );
	}

	/**
	 * Checks whether current page is supported for express checkout
	 *
	 * @return boolean
	 */
	public function is_page_supported() {
		return is_cart() || is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Initialise gateway settings form fields
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters( 'fkwcs_card_payment_form_fields', [
			'enabled'              => [
				'label'   => ' ',
				'type'    => 'checkbox',
				'title'   => __( 'Enable Stripe Gateway', 'funnelkit-stripe-woo-payment-gateway' ),
				'default' => 'no',
			],
			'title'                => [
				'title'       => __( 'Title', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Change the payment gateway title that appears on the checkout.', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => __( 'Credit Card (Stripe)', 'funnelkit-stripe-woo-payment-gateway' ),
				'desc_tip'    => true,
			],
			'description'          => [
				'title'       => __( 'Description', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'textarea',
				'css'         => 'width:25em',
				'description' => __( 'Change the payment gateway description that appears on the checkout.', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => __( 'Pay with your credit card via Stripe', 'funnelkit-stripe-woo-payment-gateway' ),
				'desc_tip'    => true,
			],
			'statement_descriptor' => [
				'title'       => __( 'Statement Descriptor', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Statement descriptors are limited to 22 characters, cannot use the special characters >, <, ", \, *, /, (, ), {, }, and must not consist solely of numbers. This will appear on your customer\'s statement in capital letters.', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => get_bloginfo( 'name' ),
				'desc_tip'    => true,
			],
			'charge_type'          => [
				'title'       => __( 'Charge Type', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select how to charge Order', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => 'automatic',
				'options'     => [
					'automatic' => __( 'Charge', 'funnelkit-stripe-woo-payment-gateway' ),
					'manual'    => __( 'Authorize', 'funnelkit-stripe-woo-payment-gateway' ),
				],
				'desc_tip'    => true,
			],
			'enable_saved_cards'   => [
				'label'       => __( 'Enable Payment via Saved Cards', 'funnelkit-stripe-woo-payment-gateway' ),
				'title'       => __( 'Saved Cards', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'Save card details for future orders', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'inline_cc'            => [
				'label'       => __( 'Enable Inline Credit Card Form', 'funnelkit-stripe-woo-payment-gateway' ),
				'title'       => __( 'Inline Credit Card Form', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'Use inline credit card for card payments', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'allowed_cards'        => [
				'title'    => __( 'Allowed Card Brands', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'multiselect',
				'class'    => 'fkwcs_select_woo',
				'desc_tip' => __( 'Accepts payments using selected cards. If empty all stripe cards are accepted.', 'funnelkit-stripe-woo-payment-gateway' ),
				'options'  => [
					'mastercard' => 'MasterCard',
					'visa'       => 'Visa',
					'amex'       => 'American Express',
					'discover'   => 'Discover',
					'jcb'        => 'JCB',
					'diners'     => 'Diners Club',
					'unionpay'   => 'UnionPay',
				],
				'default'  => [ 'mastercard', 'visa', 'amex', 'discover', 'jcb', 'dinners', 'unionpay' ],
			]
		] );
	}

	/**
	 * Process WooCommerce checkout payment
	 *
	 * @param $order_id Int Order ID
	 * @param $retry  Boolean
	 * @param $force_save_source  Boolean
	 * @param $previous_error
	 * @param $use_order_source
	 *
	 * @return array|mixed|string[]|\WP_Error|null
	 * @throws \Exception
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		do_action( 'fkwcs_before_process_payment', $order_id );
		Helper::log( 'Entering::' . __FUNCTION__ );

		if ( $this->maybe_change_subscription_payment_method( $order_id ) ) {
			return $this->process_change_subscription_payment_method( $order_id, true );
		}
		$order = wc_get_order( $order_id );
		if ( $this->should_save_card( $order ) ) {
			$force_save_source = true;
		}

		if ( 0 >= $order->get_total() ) {
			return $this->complete_free_order( $order );
		}

		if ( $this->is_using_saved_payment_method() ) {
			return $this->process_payment_using_saved_token( $order_id );
		}

		try {
			if ( $use_order_source ) {
				/**
				 * Process subscriptions renewals
				 */
				$prepared_source = $this->prepare_order_source( $order );
			} else {
				$prepared_source = $this->prepare_source( $order, $force_save_source );
			}

			$this->save_payment_method_to_order( $order, $prepared_source );

			$this->validate_minimum_order_amount( $order );

			/**
			 * Prepare Data for the API Call
			 */
			$data = [
				'amount'               => $this->get_formatted_amount( $order->get_total() ),
				'currency'             => get_woocommerce_currency(),
				'description'          => $this->get_order_description( $order ),
				'payment_method_types' => [ $this->payment_method_types ],
				'payment_method'       => $prepared_source->source,
				'customer'             => $prepared_source->customer,
				'capture_method'       => $this->capture_method,
			];

			$data['statement_descriptor'] = $this->clean_statement_descriptor( Helper::get_gateway_descriptor() );


			if ( $this->should_save_card( $order ) ) {
				$data['setup_future_usage'] = 'off_session';
			}


			$data['metadata'] = $this->add_metadata( $order );
			$data             = $this->set_shipping_data( $data, $order );

			$intent_data = $this->make_payment( $order, $prepared_source, $data );
			if ( ! empty( $intent_data ) ) {


				/**
				 * Order Pay page processing
				 */
				if ( did_action( 'woocommerce_before_pay_action' ) ) {

					if ( 'requires_confirmation' === $intent_data->status ) {
						$stripe_api = $this->get_client();
						$c_intent   = $stripe_api->payment_intents( 'confirm', [ $intent_data->id ] );
						$data       = $this->handle_client_response( $c_intent );

						if ( $data->status === 'requires_action' ) {
							$return_url = $this->get_return_url( $order );

							return apply_filters( 'fkwcs_card_payment_return_intent_data', [
								'result'              => 'success',
								'fkwcs_redirect'      => $return_url,
								'payment_method'      => $prepared_source->source,
								'fkwcs_intent_secret' => $intent_data->client_secret,
							] );

						} else {
							$return_url = $this->process_final_order( end( $data->charges->data ), $order );
						}

					}


					return apply_filters( 'fkwcs_card_payment_return_intent_data', [
						'result'   => 'success',
						'redirect' => $return_url
					] );
				}
				/**
				 * @see modify_successful_payment_result()
				 * This modifies the final response return in WooCommerce process checkout request
				 */
				$return_url = $this->get_return_url( $order );

				return apply_filters( 'fkwcs_card_payment_return_intent_data', [
					'result'              => 'success',
					'fkwcs_redirect'      => $return_url,
					'payment_method'      => $prepared_source->source,
					'fkwcs_intent_secret' => $intent_data->client_secret,
					'save_card'           => $this->should_save_card( $order ),
				] );
			} else {
				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			}
		} catch ( \Exception $e ) {
			Helper::log( $e->getMessage() );

			throw new \Exception( $e->getMessage(), 200 );
		}
	}

	/**
	 * Process Order payment using existing customer token saved.
	 *
	 * @param $order_id Int Order ID
	 *
	 * @return array|mixed|string[]|null
	 */
	public function process_payment_using_saved_token( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			$token                   = $this->find_saved_token(); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$stripe_api              = $this->get_client();
			$response                = $stripe_api->payment_methods( 'retrieve', [ $token->get_token() ] );
			$payment_method          = $response['success'] ? $response['data'] : false;
			$prepared_payment_method = Helper::prepare_payment_method( $payment_method, $token );
			$this->save_payment_method_to_order( $order, $prepared_payment_method );
			$return_url = $this->get_return_url( $order );
			/* translators: %1$1s order id, %2$2s order total amount  */
			Helper::log( sprintf( 'Begin processing payment with saved payment method for order %1$1s for the amount of %2$2s', $order_id, $order->get_total() ) );

			if ( empty( $prepared_payment_method->source ) ) {
				throw new \Exception( __( 'We are unable to process payments using the selected method. Please choose a different payment method.', 'funnelkit-stripe-woo-payment-gateway' ) );
			}

			$request = [
				'payment_method'       => $prepared_payment_method->source,
				'payment_method_types' => [ 'card' ],
				'amount'               => $this->get_formatted_amount( $order->get_total() ),
				'currency'             => strtolower( $order->get_currency() ),
				'description'          => $this->get_order_description( $order ),
				'customer'             => $prepared_payment_method->customer,
				'confirm'              => true,
				'capture_method'       => $this->capture_method,
			];

			$request['metadata'] = $this->add_metadata( $order );
			$request             = $this->set_shipping_data( $request, $order );

			$request['statement_descriptor'] = $this->clean_statement_descriptor( Helper::get_gateway_descriptor() );

			$this->validate_minimum_order_amount( $order );
			$request = apply_filters( 'fkwcs_payment_intent_data', $request, $order );
			$intent  = $this->make_payment_by_source( $order, $prepared_payment_method, $request );


			$this->save_intent_to_order( $order, $intent );

			if ( 'requires_confirmation' === $intent->status || 'requires_action' === $intent->status ) {
				return apply_filters( 'fkwcs_card_payment_return_intent_data', [
					'result'              => 'success',
					'token'               => 'yes',
					'fkwcs_redirect'      => $return_url,
					'payment_method'      => $intent->id,
					'fkwcs_intent_secret' => $intent->client_secret,
				] );
			}

			if ( $intent->amount > 0 ) {
				/** Use the last charge within the intent to proceed */
				$return_url = $this->process_final_order( end( $intent->charges->data ), $order );
			} else {
				$order->payment_complete();
			}

			/** Empty cart */
			if ( ! is_null( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			/** Return thank you page redirect URL */
			return [
				'result'   => 'success',
				'redirect' => $return_url,
			];

		} catch ( \Exception $e ) {
			Helper::log( $e->getMessage(), 'warning' );
			wc_add_notice( $e->getMessage(), 'error' );

			/* translators: error message */
			$order->update_status( 'failed', 'Reason: ' . $e->getMessage() );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
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
		$output = [
			'order'                 => $order_id,
			'confirm_payment_nonce' => wp_create_nonce( 'fkwcs_stripe_confirm_payment_intent' ),
			'fkwcs_redirect_to'     => rawurlencode( $result['fkwcs_redirect'] ),
			'save_card'             => $this->should_save_card( $order ),
		];


		if ( isset( $result['token'] ) ) {
			unset( $output['save_card'] );
		}


		// Put the final thank you page redirect into the verification URL.
		$verification_url = add_query_arg( $output, \WC_AJAX::get_endpoint( 'wc_stripe_verify_intent_checkout' ) );

		if ( class_exists( '\WFOCU_Core' ) ) {
			$verification_url = \WFOCU_Core()->public->maybe_add_wfocu_session_param( $verification_url );
		}
		if ( isset( $result['fkwcs_setup_intent_secret'] ) ) {
			$redirect = sprintf( '#fkwcs-confirm-si-%s:%s:%d:%s', $result['fkwcs_setup_intent_secret'], rawurlencode( $verification_url ), $order->get_id(), $this->id );
		} else {
			$redirect = sprintf( '#fkwcs-confirm-pi-%s:%s:%d:%s', $result['fkwcs_intent_secret'], rawurlencode( $verification_url ), $order->get_id(), $this->id );
		}
		Helper::log( 'info' . $order_id . ' redirect link is ' . $redirect );

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

			$order_id     = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect_url = isset( $_GET['fkwcs_redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['fkwcs_redirect_to'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order        = wc_get_order( $order_id );
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

			if ( ! $order->has_status( apply_filters( 'fkwcs_stripe_allowed_payment_processing_statuses', [ 'pending', 'failed' ], $order ) ) ) {
				$redirect_url = $this->get_return_url( $order );
				wp_safe_redirect( $redirect_url );
				exit;

			}

			if ( isset( $_GET['save_card'] ) || 'off_session' === $intent->setup_future_usage ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->save_payment_method( $order, $intent );


				if ( 'setup_intent' === $intent->object ) {
					$mandate_id = isset( $intent->mandate ) ? $intent->mandate : false;
				} else {
					$charge = $this->get_latest_charge_from_intent( $intent );
					if ( isset( $charge->payment_method_details->card->mandate ) ) {
						$mandate_id = $charge->payment_method_details->card->mandate;

					}
				}

				if ( isset( $mandate_id ) && ! empty( $mandate_id ) ) {
					$order->update_meta_data( '_stripe_mandate_id', $mandate_id );
					$order->save_meta_data();
				}

			}

			if ( 'setup_intent' === $intent->object && 'succeeded' === $intent->status ) {
				$order->payment_complete();
				$redirect_url = $this->get_return_url( $order );


				// Remove cart.
				if ( ! is_null( WC()->cart ) ) {
					WC()->cart->empty_cart();
				}

			} else if ( 'succeeded' === $intent->status || 'requires_capture' === $intent->status ) {
				$redirect_url = $this->process_final_order( end( $intent->charges->data ), $order_id );
			}
			Helper::log( "Redirecting to :" . $redirect_url );
		} catch ( \Exception $e ) {
			$redirect_url = $woocommerce->cart->is_empty() ? get_permalink( wc_get_page_id( 'shop' ) ) : wc_get_checkout_url();
			wc_add_notice( esc_html( $e->getMessage() ), 'error' );
		}
		if ( ! isset( $_GET['is_ajax'] ) ) {
			wp_safe_redirect( $redirect_url );
			exit;

		}
		exit;
	}

	/**
	 * After verify intent got called its time to save payment method to the order
	 *
	 * @param $order
	 * @param $intent
	 *
	 * @return void
	 */
	public function save_payment_method( $order, $intent ) {
		$payment_method = $intent->payment_method;
		$response       = $this->get_client()->payment_methods( 'retrieve', [ $payment_method ] );
		$payment_method = $response['success'] ? $response['data'] : false;

		$token = null;
		$user  = $order->get_id() ? $order->get_user() : wp_get_current_user();
		if ( $user instanceof \WP_User ) {
			$user_id = $user->ID;
			$token   = Helper::create_payment_token_for_user( $user_id, $payment_method, $this->id );
			Helper::log( sprintf( 'Payment method tokenized for Order id - %1$1s with token id - %2$2s', $order->get_id(), $token->get_id() ) );
		}

		$prepared_payment_method = Helper::prepare_payment_method( $payment_method, $token );
		$this->save_payment_method_to_order( $order, $prepared_payment_method );
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

		if ( wc_string_to_bool( $response->captured ) ) {
			$order->payment_complete( $response->id );

			/* translators: order id */
			Helper::log( sprintf( 'Payment successful Order id - %1s', $order->get_id() ) );

			/* translators: 1: Charge ID. 2: Brand name 3: last four digit */

			$order->add_order_note( sprintf( __( 'Order charge successful in Stripe. Charge: %s. Payment method: %s ending in %d', 'funnelkit-stripe-woo-payment-gateway' ), $response->id, ucfirst( $response->payment_method_details->card->brand ), $response->payment_method_details->card->last4 ) );

		} else {
			/* translators: transaction id */
			$order->update_status( 'on-hold', sprintf( __( 'Charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization. Attempting to refund the order in part or in full will release the authorization and cancel the payment.', 'funnelkit-stripe-woo-payment-gateway' ), $response->id ) );
			/* translators: transaction id */
			Helper::log( sprintf( 'Charge authorized Order id - %1s', $order->get_id() ) );
		}

		/** Empty cart */
		if ( ! is_null( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}
		$return_url = $this->get_return_url( $order );

		return $return_url;
	}

	/**
	 * Look for saved token
	 *
	 * @return \WC_Payment_Token|null
	 */
	public function find_saved_token() {
		$payment_method = isset( $_POST['payment_method'] ) && ! is_null( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : null; //phpcs:ignore WordPress.Security.NonceVerification.Missing

		$token_request_key = 'wc-' . $payment_method . '-payment-token';
		if ( ! isset( $_POST[ $token_request_key ] ) || 'new' === wc_clean( $_POST[ $token_request_key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			return null;
		}

		$token = WC_Payment_Tokens::get( wc_clean( $_POST[ $token_request_key ] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $token || $token->get_user_id() !== get_current_user_id() ) {
			return null;
		}

		return $token;
	}

	/**
	 * Save payment method to meta of the current order
	 *
	 * @param object $order current WooCommerce order.
	 * @param object $payment_method payment method associated with current order.
	 *
	 * @return void
	 */
	public function save_payment_method_to_order( $order, $payment_method ) {
		Helper::log( 'Entering::' . __FUNCTION__ );

		if ( ! empty( $payment_method->customer ) ) {
			$order->update_meta_data( Helper::get_customer_key(), $payment_method->customer );
		}

		$order->update_meta_data( '_fkwcs_source_id', $payment_method->source );

		if ( ! empty( $payment_method->token ) ) {
			$order->update_meta_data( '_fkwcs_token_id', $payment_method->token );
			$token_obj = WC_Payment_Tokens::get( $payment_method->token );
			if ( ! is_null( $token_obj ) ) {
				$token_obj->add_meta_data( Helper::get_customer_key(), $payment_method->customer );
				$token_obj->save();
			}
		}
		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}

		$this->maybe_update_source_on_subscription_order( $order, $payment_method );
	}

	public function is_using_saved_payment_method() {
		$payment_method = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : $this->id; //phpcs:ignore WordPress.Security.NonceVerification.Missing

		return ( isset( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) && 'new' !== $_POST[ 'wc-' . $payment_method . '-payment-token' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Print the Credit card field
	 *
	 * @return void
	 */
	public function payment_fields() {
		do_action( $this->id . '_before_payment_field_checkout' );
		include __DIR__ . '/parts/credit-card.php';
		do_action( $this->id . '_after_payment_field_checkout' );
	}

	/**
	 * Add the payment method to the customer account
	 *
	 * @return array|void
	 */
	public function add_payment_method() {
		$source_id = '';

		if ( empty( $_POST['fkwcs_source'] ) || ! is_user_logged_in() ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			//phpcs:ignore WordPress.Security.NonceVerification.Missing
			$error_msg = __( 'There was a problem adding the payment method.', 'funnelkit-stripe-woo-payment-gateway' );
			/* translators: error msg */
			Helper::log( sprintf( 'Add payment method Error: %1$1s', $error_msg ) );

			return;
		}

		$customer_id = $this->get_customer_id();

		$source        = wc_clean( wp_unslash( $_POST['fkwcs_source'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$stripe_api    = $this->get_client();
		$response      = $stripe_api->payment_methods( 'retrieve', [ $source ] );
		$source_object = $response['success'] ? $response['data'] : false;

		if ( isset( $source_object ) ) {
			if ( ! empty( $source_object->error ) ) {
				$error_msg = __( 'Invalid stripe source', 'funnelkit-stripe-woo-payment-gateway' );
				wc_add_notice( $error_msg, 'error' );
				/* translators: error msg */
				Helper::log( sprintf( 'Add payment method Error: %1$1s', $error_msg ) );

				return;
			}

			$source_id = $source_object->id;
		}

		$response = $stripe_api->payment_methods( 'attach', [ $source_id, [ 'customer' => $customer_id ] ] );
		$response = $response['success'] ? $response['data'] : false;
		$user     = wp_get_current_user();
		$user_id  = ( $user->ID && $user->ID > 0 ) ? $user->ID : false;
		Helper::create_payment_token_for_user( $user_id, $source_object, $this->id );

		if ( ! $response || is_wp_error( $response ) || ! empty( $response->error ) ) {
			$error_msg = __( 'Unable to attach payment method to customer', 'funnelkit-stripe-woo-payment-gateway' );
			wc_add_notice( $error_msg, 'error' );
			/* translators: error msg */
			Helper::log( sprintf( 'Add payment method Error: %1$1s', $error_msg ) );

			return;
		}

		do_action( 'fkwcs_add_payment_method_' . ( isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '' ) . '_success', $source_id, $source_object ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

		Helper::log( 'New payment method added successfully' );

		return [
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		];
	}

	/**
	 * Get stripe activated payment cards icon.
	 */
	public function get_icon() {
		if ( empty( $this->allowed_cards ) ) {
			return '';
		}
		$ext   = version_compare( WC()->version, '2.6', '>=' ) ? '.svg' : '.png';
		$style = version_compare( WC()->version, '2.6', '>=' ) ? 'style="margin-left: 0.3em"' : '';
		$icons = '<span class="fkwcs_stripe_icons">';

		if ( ( in_array( 'visa', $this->allowed_cards, true ) ) || ( in_array( 'Visa', $this->allowed_cards, true ) ) ) {
			$icons .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa' . $ext ) . '" alt="Visa" width="32" title="VISA" ' . $style . ' />';
		}
		if ( ( in_array( 'mastercard', $this->allowed_cards, true ) ) || ( in_array( 'MasterCard', $this->allowed_cards, true ) ) ) {
			$icons .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard' . $ext ) . '" alt="Mastercard" width="32" title="Master Card" ' . $style . ' />';
		}
		if ( ( in_array( 'amex', $this->allowed_cards, true ) ) || ( in_array( 'American Express', $this->allowed_cards, true ) ) ) {
			$icons .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex' . $ext ) . '" alt="Amex" width="32" title="American Express" ' . $style . ' />';
		}
		if ( ( in_array( 'discover', $this->allowed_cards, true ) ) || ( in_array( 'Discover', $this->allowed_cards, true ) ) ) {
			$icons .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover' . $ext ) . '" alt="Discover" width="32" title="Discover" ' . $style . ' />';
		}
		if ( ( in_array( 'jcb', $this->allowed_cards, true ) ) || ( in_array( 'JCB', $this->allowed_cards, true ) ) ) {
			$icons .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb' . $ext ) . '" alt="JCB" width="32" title="JCB" ' . $style . ' />';
		}
		if ( ( in_array( 'diners', $this->allowed_cards, true ) ) || ( in_array( 'Diners Club', $this->allowed_cards, true ) ) ) {
			$icons .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners' . $ext ) . '" alt="Diners" width="32" title="Diners Club" ' . $style . ' />';
		}
		if ( ( in_array( 'unionpay', $this->allowed_cards, true ) ) || ( in_array( 'Union Pay', $this->allowed_cards, true ) ) ) {
			$icons_path = FKWCS_URL . 'assets/icons/';
			$icons      .= '<img src="' . WC_HTTPS::force_https_url( $icons_path . '/unionpay' . $ext ) . '" alt="Diners" width="32" title="Union Pay" ' . $style . ' />';
		}

		$icons .= '</span>';

		return apply_filters( 'woocommerce_gateway_icon', $icons, $this->id );
	}

	public function enqueue_cc_css() {
		wp_enqueue_style( 'fkwcs-style', FKWCS_URL . 'assets/css/style' . Helper::is_min_suffix() . '.css', [], FKWCS_VERSION );
	}

	/**
	 * Handle free order
	 *
	 * @param \WC_Order $order
	 *
	 * @return array|\WP_Error
	 */
	public function complete_free_order( $order ) {
		$result = $this->process_change_subscription_payment_method( $order->get_id() );
		if ( ! $result ) {
			return new \WP_Error( 'order-error', '<div class="woocommerce-error">' . __( 'Unable to process', 'funnelkit-stripe-woo-payment-gateway' ) . '</div>', [ 'status' => 200 ] );
		}

		return $result;
	}

	/**
	 * Filter saved card to show saved card saved from other gateways too
	 * we are handling only checkout case here as payment method page works by default in woocommerce
	 *
	 * @param WC_Payment_Tokens[] $tokens
	 * @param string $customer_id
	 * @param integer $gateway_id
	 *
	 * @return array|\WC_Payment_Token[]
	 */
	public function filter_saved_tokens( $tokens, $customer_id, $gateway_id ) {

		if ( $gateway_id !== 'fkwcs_stripe' || ! $this->is_available() ) {
			return $tokens;
		}

		if ( count( $this->tokens ) > 0 ) {
			return $this->tokens;
		}

		if ( is_user_logged_in() && $this->supports( 'tokenization' ) ) {
			/*
			 * removing this filter will make sure this would not result in infinite loop
			 */
			remove_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'filter_saved_tokens' ] );

			$tokens_stripe       = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'stripe' );
			$tokens_stripe_cc    = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'stripe_cc' );
			$tokens_fkwcs_stripe = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), $this->id );
			$this->tokens        = array_merge( $this->tokens, $tokens_stripe, $tokens_stripe_cc, $tokens_fkwcs_stripe );
		}

		return $this->tokens;

	}


	/**
	 * Get test mode description
	 *
	 * @return string
	 */
	public function get_test_mode_description() {
		return sprintf( esc_html__( '%1$1s Test Mode Enabled:%2$2s Use demo card 4242424242424242 with any future date and CVV. Check more %3$3sdemo cards%4$4s', 'funnelkit-stripe-woo-payment-gateway' ), '<b>', '</b>', "<a href='https://stripe.com/docs/testing' referrer='noopener' target='_blank'>", '</a>' );
	}


}