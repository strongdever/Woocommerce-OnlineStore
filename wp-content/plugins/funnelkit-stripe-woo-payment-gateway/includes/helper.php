<?php

namespace FKWCS\Gateway\Stripe;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 */
abstract class Helper {
	private static $zero_currencies = [ 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VUV', 'XAF', 'XOF', 'XPF', 'VND' ];
	public static $log_enabled = true;
	const PAYMENT_METHODS_TRANSIENT_KEY = 'fkwcs_payment_methods_';
	const FKWCS_STRIPE_FEE = '_fkwcs_stripe_fee';
	const FKWCS_STRIPE_NET = '_fkwcs_stripe_net';
	const FKWCS_STRIPE_CURRENCY = '_fkwcs_stripe_currency';
	/**
	 * Default gateway values
	 *
	 * @var array
	 */
	private static $gateway_defaults = [
		'woocommerce_fkwcs_stripe_settings' => [
			'enabled'                                 => 'no',
			'inline_cc'                               => 'yes',
			'allowed_cards'                           => [
				'mastercard',
				'visa',
				'diners',
				'discover',
				'amex',
				'jcb',
				'unionpay',
			],
			'express_checkout_location'               => [
				'product',
				'cart',
				'checkout',
			],
			'express_checkout_enabled'                => 'no',
			'express_checkout_button_text'            => 'Pay now',
			'express_checkout_button_theme'           => 'dark',
			'express_checkout_button_height'          => '40',
			'express_checkout_title'                  => 'Express Checkout',
			'express_checkout_product_page_position'  => 'above',
			'express_checkout_separator_product'      => 'OR',
			'express_checkout_button_width'           => '',
			'express_checkout_button_alignment'       => 'left',
			'express_checkout_separator_cart'         => 'OR',
			'express_checkout_separator_checkout'     => 'OR',
			'express_checkout_checkout_page_position' => 'above-checkout',
		]
	];

	/**
	 * Get Stripe amount to pay
	 *
	 * @param float $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_stripe_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::no_decimal_currencies(), true ) ) {
			return absint( $total );
		} else {
			return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) ); // In cents.
		}
	}

	public static function get_gateway_settings( $gateway = 'fkwcs_stripe' ) {
		$default_settings = [];
		$setting_name     = 'woocommerce_' . $gateway . '_settings';
		$saved_settings   = get_option( $setting_name, [] );

		if ( isset( self::$gateway_defaults[ $setting_name ] ) ) {
			$default_settings = self::$gateway_defaults[ $setting_name ];
		}

		$settings = array_merge( $default_settings, $saved_settings );

		return apply_filters( 'fkwcs_gateway_settings', $settings );
	}


	/**
	 * List of currencies supported by Stripe that has no decimals
	 * https://stripe.com/docs/currencies#zero-decimal from https://stripe.com/docs/currencies#presentment-currencies
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return [
			'bif', // Burundian Franc
			'clp', // Chilean Peso
			'djf', // Djiboutian Franc
			'gnf', // Guinean Franc
			'jpy', // Japanese Yen
			'kmf', // Comorian Franc
			'krw', // South Korean Won
			'mga', // Malagasy Ariary
			'pyg', // Paraguayan Guaraní
			'rwf', // Rwandan Franc
			'ugx', // Ugandan Shilling
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
		];
	}

	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < self::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new \Exception( 'Did not meet minimum amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'funnelkit-stripe-woo-payment-gateway' ), wc_price( self::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Checks Stripe minimum order value authorized per currency
	 */
	public static function get_minimum_amount() {

		switch ( get_woocommerce_currency() ) {

			case 'GBP':
				$minimum_amount = 30;
				break;
			case 'DKK':
				$minimum_amount = 250;
				break;
			case 'NOK':
			case 'SEK':
				$minimum_amount = 300;
				break;
			case 'JPY':
				$minimum_amount = 5000;
				break;
			case 'MXN':
				$minimum_amount = 1000;
				break;
			case 'HKD':
				$minimum_amount = 400;
				break;
			default:
				$minimum_amount = 50;
				break;
		}

		return $minimum_amount;
	}

	/**
	 * Tokenize card payment
	 *
	 * @param int $user_id id of current user placing .
	 * @param object $payment_method payment method object.
	 *
	 * @return object token object.
	 */
	public static function create_payment_token_for_user( $user_id, $payment_method, $gateway_id ) {
		$token = new \WC_Payment_Token_CC();
		$token->set_expiry_month( $payment_method->card->exp_month );
		$token->set_expiry_year( $payment_method->card->exp_year );
		$token->set_card_type( strtolower( $payment_method->card->brand ) );
		$token->set_last4( $payment_method->card->last4 );
		$token->set_gateway_id( $gateway_id );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );
		$token->save();

		return $token;
	}

	/**
	 * @param $payment_method
	 * @param $token \WC_Payment_Token
	 *
	 * @return object
	 */
	public static function prepare_payment_method( $payment_method, $token ) {
		return (object) apply_filters( 'fkwcs_prepare_payment_method_args', [
			'token_id'       => $token instanceof \WC_Payment_Token_CC ? $token->get_id() : '',
			'customer'       => ( false !== $payment_method ) ? $payment_method->customer : '',
			'source'         => ( false !== $payment_method ) ? $payment_method->id : '',
			'source_object'  => $payment_method,
			'payment_method' => ( false !== $payment_method ) ? $payment_method->id : '',
		] );
	}

	public static function format_amount( $currency, $amount ) {
		$amount = self::get_original_amount( $amount, $currency );

		return number_format( $amount, 2, '.', '' );
	}

	public static function get_original_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::$zero_currencies, true ) ) {
			// Zero decimal currencies accepted by stripe.
			return absint( $total );
		} else {
			return (float) wc_format_decimal( ( (float) $total / 100 ), wc_get_price_decimals() ); // In cents.
		}
	}


	public static function get_stripe_fee( $order ) {
		if ( empty( $order ) ) {
			return false;
		}


		return (float) self::get_meta( $order, self::FKWCS_STRIPE_FEE );
	}


	public static function get_stripe_net( $order ) {
		if ( empty( $order ) ) {
			return false;
		}

		return (float) self::get_meta( $order, self::FKWCS_STRIPE_NET );
	}

	/**
	 * Get stripe currency
	 *
	 * @param object $order WooCommerce Order.
	 *
	 * @return string
	 */
	public static function get_stripe_currency( $order ) {
		if ( empty( $order ) ) {
			return false;
		}

		return self::get_meta( $order, self::FKWCS_STRIPE_CURRENCY );
	}

	public static function update_stripe_transaction_data( $order, $data ) {
		( ! empty( $data['fee'] ) ) ? $order->update_meta_data( self::FKWCS_STRIPE_FEE, $data['fee'] ) : $order->update_meta_data( self::FKWCS_STRIPE_CURRENCY, 0 );
		( ! empty( $data['net'] ) ) ? $order->update_meta_data( self::FKWCS_STRIPE_NET, $data['net'] ) : $order->update_meta_data( self::FKWCS_STRIPE_NET, 0 );
		( ! empty( $data['currency'] ) ) ? $order->update_meta_data( self::FKWCS_STRIPE_CURRENCY, $data['currency'] ) : $order->update_meta_data( self::FKWCS_STRIPE_CURRENCY, '' );
	}


	/**
	 * Adds payment intent id and order note to order if payment intent is not already saved
	 *
	 * @param array $payment_intent
	 * @param \WC_Order $order
	 */
	public static function add_payment_intent_to_order( $payment_intent, $order ) {


		$order->add_order_note( sprintf( /* translators: $1%s payment intent ID */ __( 'Stripe payment intent created (Payment Intent ID: %1$s)', 'funnelkit-stripe-woo-payment-gateway' ), $payment_intent->id ) );
		$order->update_meta_data( '_fkwcs_intent_id', [
			'id'            => $payment_intent->id,
			'client_secret' => $payment_intent->client_secret,
		] );
		$order->save();
	}

	/**
	 * Localize Stripe messages based on code
	 *
	 * @return array
	 */
	public static function get_localized_messages() {
		return apply_filters( 'fkwcs_stripe_localized_messages', [
			'stripe_cc_generic'                => __( 'There was an error processing your credit card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incomplete_number'                => __( 'Your card number is incomplete.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incomplete_expiry'                => __( 'Your card\'s expiration date is incomplete.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incomplete_cvc'                   => __( 'Your card\'s security code is incomplete.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incomplete_zip'                   => __( 'Your card\'s zip code is incomplete.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incorrect_number'                 => __( 'The card number is incorrect. Check the card\'s number or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incorrect_cvc'                    => __( 'The card\'s security code is incorrect. Check the card\'s security code or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incorrect_zip'                    => __( 'The card\'s ZIP code is incorrect. Check the card\'s ZIP code or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_number'                   => __( 'The card number is invalid. Check the card details or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_characters'               => __( 'This value provided to the field contains characters that are unsupported by the field.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_cvc'                      => __( 'The card\'s security code is invalid. Check the card\'s security code or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_expiry_month'             => __( 'The card\'s expiration month is incorrect. Check the expiration date or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_expiry_year'              => __( 'The card\'s expiration year is incorrect. Check the expiration date or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incorrect_address'                => __( 'The card\'s address is incorrect. Check the card\'s address or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'expired_card'                     => __( 'The card has expired. Check the expiration date or use a different card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'card_declined'                    => __( 'The card has been declined.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_expiry_year_past'         => __( 'Your card\'s expiration year is in the past.', 'funnelkit-stripe-woo-payment-gateway' ),
			'account_number_invalid'           => __( 'The bank account number provided is invalid (e.g., missing digits). Bank account information varies from country to country. We recommend creating validations in your entry forms based on the bank account formats we provide.', 'funnelkit-stripe-woo-payment-gateway' ),
			'amount_too_large'                 => __( 'The specified amount is greater than the maximum amount allowed. Use a lower amount and try again.', 'funnelkit-stripe-woo-payment-gateway' ),
			'amount_too_small'                 => __( 'The specified amount is less than the minimum amount allowed. Use a higher amount and try again.', 'funnelkit-stripe-woo-payment-gateway' ),
			'authentication_required'          => __( 'The payment requires authentication to proceed. If your customer is off session, notify your customer to return to your application and complete the payment. If you provided the error_on_requires_action parameter, then your customer should try another card that does not require authentication.', 'funnelkit-stripe-woo-payment-gateway' ),
			'balance_insufficient'             => __( 'The transfer or payout could not be completed because the associated account does not have a sufficient balance available. Create a new transfer or payout using an amount less than or equal to the account\'s available balance.', 'funnelkit-stripe-woo-payment-gateway' ),
			'bank_account_declined'            => __( 'The bank account provided can not be used to charge, either because it is not verified yet or it is not supported.', 'funnelkit-stripe-woo-payment-gateway' ),
			'bank_account_exists'              => __( 'The bank account provided already exists on the specified Customer object. If the bank account should also be attached to a different customer, include the correct customer ID when making the request again.', 'funnelkit-stripe-woo-payment-gateway' ),
			'bank_account_unusable'            => __( 'The bank account provided cannot be used for payouts. A different bank account must be used.', 'funnelkit-stripe-woo-payment-gateway' ),
			'bank_account_unverified'          => __( 'Your Connect platform is attempting to share an unverified bank account with a connected account.', 'funnelkit-stripe-woo-payment-gateway' ),
			'bank_account_verification_failed' => __( 'The bank account cannot be verified, either because the microdeposit amounts provided do not match the actual amounts, or because verification has failed too many times.', 'funnelkit-stripe-woo-payment-gateway' ),
			'card_decline_rate_limit_exceeded' => __( 'This card has been declined too many times. You can try to charge this card again after 24 hours. We suggest reaching out to your customer to make sure they have entered all of their information correctly and that there are no issues with their card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'charge_already_captured'          => __( 'The charge you\'re attempting to capture has already been captured. Update the request with an uncaptured charge ID.', 'funnelkit-stripe-woo-payment-gateway' ),
			'charge_already_refunded'          => __( 'The charge you\'re attempting to refund has already been refunded. Update the request to use the ID of a charge that has not been refunded.', 'funnelkit-stripe-woo-payment-gateway' ),
			'charge_disputed'                  => __( 'The charge you\'re attempting to refund has been charged back. Check the disputes documentation to learn how to respond to the dispute.', 'funnelkit-stripe-woo-payment-gateway' ),
			'charge_exceeds_source_limit'      => __( 'This charge would cause you to exceed your rolling-window processing limit for this source type. Please retry the charge later, or contact us to request a higher processing limit.', 'funnelkit-stripe-woo-payment-gateway' ),
			'charge_expired_for_capture'       => __( 'The charge cannot be captured as the authorization has expired. Auth and capture charges must be captured within seven days.', 'funnelkit-stripe-woo-payment-gateway' ),
			'charge_invalid_parameter'         => __( 'One or more provided parameters was not allowed for the given operation on the Charge. Check our API reference or the returned error message to see which values were not correct for that Charge.', 'funnelkit-stripe-woo-payment-gateway' ),
			'email_invalid'                    => __( 'The email address is invalid (e.g., not properly formatted). Check that the email address is properly formatted and only includes allowed characters.', 'funnelkit-stripe-woo-payment-gateway' ),
			'idempotency_key_in_use'           => __( 'The idempotency key provided is currently being used in another request. This occurs if your integration is making duplicate requests simultaneously.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_charge_amount'            => __( 'The specified amount is invalid. The charge amount must be a positive integer in the smallest currency unit, and not exceed the minimum or maximum amount.', 'funnelkit-stripe-woo-payment-gateway' ),
			'invalid_source_usage'             => __( 'The source cannot be used because it is not in the correct state (e.g., a charge request is trying to use a source with a pending, failed, or consumed source). Check the status of the source you are attempting to use.', 'funnelkit-stripe-woo-payment-gateway' ),
			'missing'                          => __( 'Both a customer and source ID have been provided, but the source has not been saved to the customer. To create a charge for a customer with a specified source, you must first save the card details.', 'funnelkit-stripe-woo-payment-gateway' ),
			'postal_code_invalid'              => __( 'The ZIP code provided was incorrect.', 'funnelkit-stripe-woo-payment-gateway' ),
			'processing_error'                 => __( 'An error occurred while processing the card. Try again later or with a different payment method.', 'funnelkit-stripe-woo-payment-gateway' ),
			'card_not_supported'               => __( 'The card does not support this type of purchase.', 'funnelkit-stripe-woo-payment-gateway' ),
			'call_issuer'                      => __( 'The card has been declined for an unknown reason.', 'funnelkit-stripe-woo-payment-gateway' ),
			'card_velocity_exceeded'           => __( 'The customer has exceeded the balance or credit limit available on their card.', 'funnelkit-stripe-woo-payment-gateway' ),
			'currency_not_supported'           => __( 'The card does not support the specified currency.', 'funnelkit-stripe-woo-payment-gateway' ),
			'do_not_honor'                     => __( 'The card has been declined. Reason: Do not honer.', 'funnelkit-stripe-woo-payment-gateway' ),
			'fraudulent'                       => __( 'The payment has been declined as Stripe suspects it is fraudulent.', 'funnelkit-stripe-woo-payment-gateway' ),
			'generic_decline'                  => __( 'The card has been declined for an unknown reason.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incorrect_pin'                    => __( 'The PIN entered is incorrect. ', 'funnelkit-stripe-woo-payment-gateway' ),
			'insufficient_funds'               => __( 'The card has insufficient funds to complete the purchase.', 'funnelkit-stripe-woo-payment-gateway' ),
			'empty_element'                    => __( 'Please select a payment method before proceeding.', 'funnelkit-stripe-woo-payment-gateway' ),
			'empty_element_sepa_debit'         => __( 'Please enter your IBAN before proceeding.', 'funnelkit-stripe-woo-payment-gateway' ),
			'empty_element_ideal'              => __( 'Please select a bank before proceeding', 'funnelkit-stripe-woo-payment-gateway' ),
			'incomplete_iban'                  => __( 'The IBAN you entered is incomplete.', 'funnelkit-stripe-woo-payment-gateway' ),
			'incomplete_boleto_tax_id'         => __( 'Please enter a valid CPF / CNPJ', 'funnelkit-stripe-woo-payment-gateway' ),
			'test_mode_live_card'              => __( 'Your card was declined. Your request was in test mode, but you used a real credit card. Only test cards can be used in test mode.', 'funnelkit-stripe-woo-payment-gateway' ),
			'server_side_confirmation_beta'    => __( 'You do not have permission to use the PaymentElement card form. Please send a request to https://support.stripe.com/ and ask for the "server_side_confirmation_beta" to be added to your account.', 'funnelkit-stripe-woo-payment-gateway' ),
			'phone_required'                   => __( 'Please provide a billing phone number.', 'funnelkit-stripe-woo-payment-gateway' ),
			'ach_instant_only'                 => __( 'Your payment could not be processed at this time because your bank account does not support instant verification.', 'funnelkit-stripe-woo-payment-gateway' ),
		] );
	}

	public static function get_payment_mode() {
		if ( 'test' === get_option( 'fkwcs_mode', 'test' ) ) {
			$mode = 'test';
		} else {
			$mode = 'live';
		}

		return $mode;
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values: emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info', $source = 'fkwcs-stripe' ) {

		if ( self::$log_enabled ) {

			if ( is_array( $message ) || is_object( $message ) ) {
				$message = wp_json_encode( $message ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}
			wc_get_logger()->log( $level, \WC_Geolocation::get_ip_address() . "::" . $message . "\n", array( 'source' => $source ) );
		}
	}

	/**
	 * Method to retrieve balances from the api and update the required meta
	 *
	 * @param \WC_Order $order
	 * @param string $transaction_id
	 * @param bool $is_refund_transaction Whether its the case of refund transaction or not, if yes we should do the calculation otherwise override meta
	 *
	 * @return void
	 */
	public static function update_balance( $order, $transaction_id, $is_refund_transaction = false ) {

		$test_mode       = get_option( 'fkwcs_mode', 'test' );
		$test_secret_key = get_option( 'fkwcs_test_secret_key', '' );
		$live_secret_key = get_option( 'fkwcs_secret_key', '' );

		if ( 'test' === $test_mode ) {
			$client_secret = $test_secret_key;
		} else {
			$client_secret = $live_secret_key;
		}

		$stripe   = new Client( $client_secret );
		$response = $stripe->balance_transactions( 'retrieve', [ $transaction_id ] );
		$balance  = $response['success'] ? $response['data'] : false;

		if ( ! $balance ) {
			self::log( 'Unable to update stripe transaction balance' );

			return;
		}

		$fee = ! empty( $balance->fee ) ? self::format_amount( $order, $balance->fee ) : 0;
		$net = ! empty( $balance->net ) ? self::format_amount( $order, $balance->net ) : 0;


		if ( $is_refund_transaction === true ) {
			$fee = (float) self::get_stripe_fee( $order ) + (float) $fee;
			$net = (float) self::get_stripe_net( $order ) + (float) $net;
		}

		$currency = ! empty( $balance->currency ) ? strtoupper( $balance->currency ) : null;

		$data = [
			'fee'      => $fee,
			'net'      => $net,
			'currency' => $currency,
		];

		self::update_stripe_transaction_data( $order, $data );

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
	}


	// Function to get the webhook url for setup in admin and front
	public static function get_webhook_url() {
		// Return REST URL
		return esc_url( rest_url( 'fkwcs/v1/webhook' ) );
	}

	public static function get_enabled_webhook_events( $events = array() ) {

		return apply_filters( 'fkwcs_stripe_webhook_events', array_values( array_unique( array_merge( array(
			'charge.failed',
			'charge.succeeded',
			'charge.pending',
			'source.chargeable',
			'payment_intent.succeeded',
			'payment_intent.requires_action',
			'charge.refunded',
			'charge.dispute.created',
			'charge.dispute.closed',
			'review.opened',
			'review.closed',
			'payment_intent.payment_failed',
			'charge.captured'
		), $events ) ) ) );


	}


	public static function get_customer_key() {
		return '_fkwcs_customer_id';

	}

	public static function is_min_suffix() {
		if ( ! defined( 'FKWCS_IS_DEV' ) ) {
			return '.min';
		}

		return '';
	}

	public static function is_payment_method_page() {
		return 0 < did_action( 'woocommerce_account_payment-methods_endpoint' );
	}


	/**
	 * Get the descriptor of the gateway, right now we are fetching it from the stripe CC settings
	 * @return string
	 */
	public static function get_gateway_descriptor() {

		$gateways = WC()->payment_gateways()->payment_gateways();

		return $gateways['fkwcs_stripe']->get_option( 'statement_descriptor' );

	}


	/**
	 * List all possible compatible  kes
	 *
	 * @param $meta_key
	 *
	 * @return string[]
	 */
	public static function get_compatibility_keys( $meta_key ) {

		$config = array(
			'_fkwcs_source_id'   => array( '_stripe_source_id', '_payment_method_token' ),
			'_fkwcs_customer_id' => array( '_stripe_customer_id', '_wc_stripe_customer' ),
			'_fkwcs_intent_id'   => array( '_stripe_intent_id', '_payment_intent_id' ),
		);

		return $config[ $meta_key ];
	}


	/**
	 * Wrapper function for the HPOS compat, we are here trying possible way to fetch the meta
	 *
	 * @param \WC_Order $order
	 * @param string $key
	 *
	 * @return false|void
	 */
	public static function get_meta( $order, $key ) {
		global $wpdb;
		if ( ! $order instanceof \WC_Abstract_Order ) {
			return false;
		}

		$meta = $order->get_meta( $key, true );

		if ( empty( $meta ) ) {
			$meta = get_post_meta( $order->get_id(), $key, true );
		}

		if ( empty( $meta ) && self::is_hpos_enabled() ) {
			$meta_table = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_meta_table_name();

			$meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value from {$meta_table} where meta_key = '{$key}' and order_id = %d", $order->get_id() ) ); //phpcs:ignore

		}

		return $meta;


	}

	/**
	 * Checks if HPOS enabled
	 *
	 * @return bool
	 */
	public static function is_hpos_enabled() {
		return ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
	}




}
