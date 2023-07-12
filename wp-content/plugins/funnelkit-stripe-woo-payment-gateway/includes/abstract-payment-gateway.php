<?php

namespace FKWCS\Gateway\Stripe;

use WC_Payment_Gateway;
use WC_AJAX;

abstract class Abstract_Payment_Gateway extends WC_Payment_Gateway {
	private $client = null;
	private static $zero_currencies = [ 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VUV', 'XAF', 'XOF', 'XPF', 'VND' ];
	protected $retry_interval = 1;

	protected $test_mode = '';
	protected $keys = [];
	protected $test_pub_key = '';
	protected $test_secret_key = '';
	protected $live_pub_key = '';
	protected $live_secret_key = '';
	protected $client_secret = '';
	protected $client_pub_key = '';
	protected $debug = false;
	protected $inline_cc = true;
	protected $allowed_cards = [];
	public $enable_saved_cards = false;
	public $refund_supported = false;
	public $payment_method_types = 'card';
	public $is_past_customer = false;

	/**
	 * Construct
	 */
	public function __construct() {
		$this->set_api_keys();
		$this->core_hooks();
		$this->init();
		$this->filter_hooks();

	}

	/**
	 * Set API Keys
	 *
	 * @return void
	 */
	protected function set_api_keys() {
		$this->test_mode       = get_option( 'fkwcs_mode', 'test' );
		$this->test_pub_key    = get_option( 'fkwcs_test_pub_key', '' );
		$this->test_secret_key = get_option( 'fkwcs_test_secret_key', '' );
		$this->live_pub_key    = get_option( 'fkwcs_pub_key', '' );
		$this->live_secret_key = get_option( 'fkwcs_secret_key', '' );
		$this->debug           = 'yes' === get_option( 'fkwcs_debug_log', 'no' );
		Helper::$log_enabled   = $this->debug;

		if ( 'test' === $this->test_mode ) {
			$this->client_secret  = $this->test_secret_key;
			$this->client_pub_key = $this->test_pub_key;
		} else {
			$this->client_secret  = $this->live_secret_key;
			$this->client_pub_key = $this->live_pub_key;
		}

		$this->set_client();
	}

	/**
	 * Get saved publishable key
	 *
	 * @return mixed|string
	 */
	public function get_client_key() {
		return $this->client_pub_key;
	}

	/**
	 * Check if secret or publishable, any key saved
	 *
	 * @return bool
	 */
	public function is_configured() {
		if ( empty( $this->client_secret ) || empty( $this->client_pub_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add hooks
	 *
	 * @return void
	 */
	protected function core_hooks() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_stripe_js' ] );
	}

	/**
	 * Set & init stripe core
	 * @return void
	 */
	public function set_client() {
		if ( empty( $this->client_secret ) || empty( $this->client_pub_key ) ) {
			return;
		}

		$this->client = new Client( $this->client_secret );
	}

	abstract protected function init();

	/**
	 * @return \FKWCS\Gateway\Stripe\Client|null;
	 */
	public function get_client() {
		return $this->client;
	}

	/**
	 * Register Stripe JS
	 *
	 * @return void
	 */
	public function register_stripe_js() {
		wp_register_script( 'fkwcs-stripe-external', 'https://js.stripe.com/v3/', [], FKWCS_VERSION, true );
		wp_register_script( 'fkwcs-stripe-js', FKWCS_URL . 'assets/js/stripe-elements' . Helper::is_min_suffix() . '.js', [
			'jquery',
			'jquery-payment',
			'fkwcs-stripe-external'
		], FKWCS_VERSION, true );
	}

	/**
	 * Checks if current page supports express checkout
	 *
	 * @return boolean
	 */
	public function is_page_supported() {
		return $this->is_product() || is_cart() || is_checkout() || isset( $_GET['pay_for_order'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Helper method to create stripe friendly locale from the wordpress locale
	 *
	 * @param string $wc_locale
	 *
	 * @return string
	 */
	public static function convert_wc_locale_to_stripe_locale( $wc_locale ) {
		// List copied from: https://stripe.com/docs/js/appendix/supported_locales.
		$supported = [
			'ar',     // Arabic.
			'bg',     // Bulgarian (Bulgaria).
			'cs',     // Czech (Czech Republic).
			'da',     // Danish.
			'de',     // German (Germany).
			'el',     // Greek (Greece).
			'en',     // English.
			'en-GB',  // English (United Kingdom).
			'es',     // Spanish (Spain).
			'es-419', // Spanish (Latin America).
			'et',     // Estonian (Estonia).
			'fi',     // Finnish (Finland).
			'fr',     // French (France).
			'fr-CA',  // French (Canada).
			'he',     // Hebrew (Israel).
			'hu',     // Hungarian (Hungary).
			'id',     // Indonesian (Indonesia).
			'it',     // Italian (Italy).
			'ja',     // Japanese.
			'lt',     // Lithuanian (Lithuania).
			'lv',     // Latvian (Latvia).
			'ms',     // Malay (Malaysia).
			'mt',     // Maltese (Malta).
			'nb',     // Norwegian Bokmål.
			'nl',     // Dutch (Netherlands).
			'pl',     // Polish (Poland).
			'pt-BR',  // Portuguese (Brazil).
			'pt',     // Portuguese (Brazil).
			'ro',     // Romanian (Romania).
			'ru',     // Russian (Russia).
			'sk',     // Slovak (Slovakia).
			'sl',     // Slovenian (Slovenia).
			'sv',     // Swedish (Sweden).
			'th',     // Thai.
			'tr',     // Turkish (Turkey).
			'zh',     // Chinese Simplified (China).
			'zh-HK',  // Chinese Traditional (Hong Kong).
			'zh-TW',  // Chinese Traditional (Taiwan).
		];

		// Stripe uses '-' instead of '_' (used in WordPress).
		$locale = str_replace( '_', '-', $wc_locale );

		if ( in_array( $locale, $supported, true ) ) {
			return $locale;
		}

		// The plugin has been fully translated for Spanish (Ecuador), Spanish (Mexico), and
		// Spanish(Venezuela), and partially (88% at 2021-05-14) for Spanish (Colombia).
		// We need to map these locales to Stripe's Spanish (Latin America) 'es-419' locale.
		// This list should be updated if more localized versions of Latin American Spanish are
		// made available.
		$lowercase_locale                  = strtolower( $wc_locale );
		$translated_latin_american_locales = [
			'es_co', // Spanish (Colombia).
			'es_ec', // Spanish (Ecuador).
			'es_mx', // Spanish (Mexico).
			'es_ve', // Spanish (Venezuela).
		];
		if ( in_array( $lowercase_locale, $translated_latin_american_locales, true ) ) {
			return 'es-419';
		}

		// Finally, we check if the "base locale" is available.
		$base_locale = substr( $wc_locale, 0, 2 );
		if ( in_array( $base_locale, $supported, true ) ) {
			return $base_locale;
		}

		// Default to 'auto' so Stripe.js uses the browser locale.
		return 'auto';
	}

	/**
	 * Enqueue Stripe assets and include hooks if page supported
	 *
	 * @return void
	 */
	public function enqueue_stripe_js() {
		if ( ! $this->is_page_supported() || ( is_order_received_page() ) ) {
			return;
		}

		/** If Stripe is not enabled bail */
		if ( false === $this->is_available() ) {
			return;
		}

		/** If no SSL bail */
		if ( 'test' !== $this->test_mode && ! is_ssl() ) {

			return;
		}

		wp_enqueue_script( 'fkwcs-stripe-external' );
		$this->tokenization_script();

		wp_enqueue_script( 'fkwcs-stripe-js' );
		wp_localize_script( 'fkwcs-stripe-js', 'fkwcs_data', $this->localize_data() );
		add_action( 'wp_head', [ $this, 'enqueue_cc_css' ] );
		do_action( 'fkwcs_core_element_js_enqueued' );
	}

	/**
	 * Localize important data
	 *
	 * @return mixed|null
	 */
	protected function localize_data() {
		return apply_filters( 'fkwcs_localized_data', [
			'locale'                  => $this->convert_wc_locale_to_stripe_locale( get_locale() ),
			'is_checkout'             => $this->is_checkout() ? 'yes' : 'no',
			'is_product_page'         => is_product() || wc_post_content_has_shortcode( 'product_page' ),
			'is_cart'                 => is_cart(),
			'pub_key'                 => $this->client_pub_key,
			'admin_ajax'              => admin_url( 'admin-ajax.php' ),
			'fkwcs_nonce'             => wp_create_nonce( 'fkwcs_nonce' ),
			'shipping_required'       => WC()->cart->needs_shipping(),
			'inline_cc'               => $this->inline_cc,
			'enable_saved_cards'      => $this->enable_saved_cards,
			'allowed_cards'           => $this->allowed_cards,
			'is_ssl'                  => is_ssl(),
			'mode'                    => $this->test_mode,
			'js_nonce'                => wp_create_nonce( 'fkwcs_js_nonce' ),
			'stripe_localized'        => Helper::get_localized_messages(),
			'default_cards'           => [
				'mastercard' => __( 'MasterCard', 'funnelkit-stripe-woo-payment-gateway' ),
				'visa'       => __( 'Visa', 'funnelkit-stripe-woo-payment-gateway' ),
				'amex'       => __( 'American Express', 'funnelkit-stripe-woo-payment-gateway' ),
				'discover'   => __( 'Discover', 'funnelkit-stripe-woo-payment-gateway' ),
				'jcb'        => __( 'JCB', 'funnelkit-stripe-woo-payment-gateway' ),
				'diners'     => __( 'Diners Club', 'funnelkit-stripe-woo-payment-gateway' ),
				'unionpay'   => __( 'UnionPay', 'funnelkit-stripe-woo-payment-gateway' ),
			],
			'not_allowed_string'      => __( 'is not allowed', 'funnelkit-stripe-woo-payment-gateway' ),
			'get_home_url'            => get_home_url(),
			'current_user_billing'    => $this->get_current_user_billing_details(),
			'sepa_options'            => [
				'supportedCountries' => [ 'SEPA' ],
				'placeholderCountry' => WC()->countries->get_base_country(),
				'style'              => [
					'base' => [
						'fontSize' => '15px',
						'color'    => '#32325d',
					],
				],
			],
			'empty_sepa_iban_message' => __( 'Please enter a IBAN number to proceed.', 'funnelkit-stripe-woo-payment-gateway' ),
			'empty_bank_message'      => __( 'Please select a bank to proceed.', 'funnelkit-stripe-woo-payment-gateway' ),
			'wc_ajax_endpoint'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'assets_url'              => FKWCS_URL . 'assets/',
			'icons'                   => [
				'applepay_gray'  => FKWCS_URL . 'assets/icons/apple_pay_gray.svg',
				'applepay_light' => FKWCS_URL . 'assets/icons/apple_pay_light.svg',
				'gpay_light'     => FKWCS_URL . 'assets/icons/gpay_light.svg',
				'gpay_gray'      => FKWCS_URL . 'assets/icons/gpay_gray.svg',
			],
			'is_change_payment_page'  => isset( $_GET['change_payment_method'] ) ? 'yes' : 'no', //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'is_add_payment_page'     => is_wc_endpoint_url( 'add-payment-method' ) ? 'yes' : 'no',
			'is_pay_for_order_page'   => is_wc_endpoint_url( 'order-pay' ) ? 'yes' : 'no',
			'debug_log'               => ! empty( get_option( 'fkwcs_debug_log' ) ) ? get_option( 'fkwcs_debug_log' ) : 'no',
			'debug_msg'               => __( 'Stripe enabled Payment Request is not available in this browser', 'funnelkit-stripe-woo-payment-gateway' ),
		] );
	}

	/**
	 * Get current user billing details
	 *
	 * @return mixed|void|null
	 */
	public function get_current_user_billing_details() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user    = wp_get_current_user();
		$details = [];
		if ( ! empty( $user->display_name ) ) {
			$details['name'] = $user->display_name;
		}

		if ( ! empty( $user->user_email ) ) {
			$details['email'] = $user->user_email;
		}

		return apply_filters( 'fkwcs_current_user_billing_details', $details, get_current_user_id() );
	}

	/**
	 * Clean/Trim statement descriptor as per stripe requirement.
	 *
	 * @param string $statement_descriptor User Input.
	 *
	 * @return string optimized statement descriptor.
	 */
	public function clean_statement_descriptor( $statement_descriptor = '' ) {
		$disallowed_characters = [ '<', '>', '\\', '*', '"', "'", '/', '(', ')', '{', '}' ];

		/** Strip any tags */
		$statement_descriptor = wp_strip_all_tags( $statement_descriptor );

		/** Strip any HTML entities */
		// Props https://stackoverflow.com/questions/657643/how-to-remove-html-special-chars .
		$statement_descriptor = preg_replace( '/&#?[a-z0-9]{2,8};/i', '', $statement_descriptor );

		/** Next, remove any remaining disallowed characters */
		$statement_descriptor = str_replace( $disallowed_characters, '', $statement_descriptor );

		/** Trim any whitespace at the ends and limit to 22 characters */
		$statement_descriptor = substr( trim( $statement_descriptor ), 0, 22 );

		return $statement_descriptor;
	}

	/**
	 * Controller method to process full OR partial refunds
	 *
	 * @param integer $order_id
	 * @param string $amount
	 * @param string $reason
	 *
	 * @return bool|void|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		if ( 0 >= $amount ) {
			return false;
		}

		try {
			$order  = wc_get_order( $order_id );
			$intent = Helper::get_meta( $order, '_fkwcs_intent_id' );

			if ( empty( $intent ) ) {
				$intent = Helper::get_meta( $order, '_stripe_intent_id' );

			} else {
				$intent = $intent['id'];
			}
			$response        = $this->create_refund_request( $order, $amount, $reason, $intent );
			$refund_response = $response['success'] ? $response['data'] : false;
			if ( $refund_response ) {
				if ( isset( $refund_response->balance_transaction ) ) {
					Helper::update_balance( $order, $refund_response->balance_transaction, true );
				}

				$refund_time = gmdate( 'Y-m-d H:i:s', time() ); 
				$order->update_meta_data( '_fkwcs_refund_id', $refund_response->id );
				$order->save_meta_data();
				$order->add_order_note( __( 'Reason : ', 'funnelkit-stripe-woo-payment-gateway' ) . $reason . '.<br>' . __( 'Amount : ', 'funnelkit-stripe-woo-payment-gateway' ) . get_woocommerce_currency_symbol() . $amount . '.<br>' . __( 'Status : ', 'funnelkit-stripe-woo-payment-gateway' ) . ucfirst( $refund_response->status ) . ' [ ' . $refund_time . ' ] ' . ( is_null( $refund_response->id ) ? '' : '<br>' . __( 'Transaction ID : ', 'funnelkit-stripe-woo-payment-gateway' ) . $refund_response->id ) );
				Helper::log( __( 'Refund initiated: ', 'funnelkit-stripe-woo-payment-gateway' ) . __( 'Reason : ', 'funnelkit-stripe-woo-payment-gateway' ) . $reason . __( 'Amount : ', 'funnelkit-stripe-woo-payment-gateway' ) . get_woocommerce_currency_symbol() . $amount . __( 'Status : ', 'funnelkit-stripe-woo-payment-gateway' ) . ucfirst( $refund_response->status ) . ' [ ' . $refund_time . ' ] ' . ( is_null( $refund_response->id ) ? '' : __( 'Transaction ID : ', 'funnelkit-stripe-woo-payment-gateway' ) . $refund_response->id ) );

				if ( 'succeeded' === $refund_response->status ) {
					return true;
				} else {
					return new \WP_Error( 'error', __( 'Your refund process is ', 'funnelkit-stripe-woo-payment-gateway' ) . ucfirst( $refund_response->status ) );
				}
			} else {
				$order->add_order_note( __( 'Reason : ', 'funnelkit-stripe-woo-payment-gateway' ) . $reason . '.<br>' . __( 'Amount : ', 'funnelkit-stripe-woo-payment-gateway' ) . get_woocommerce_currency_symbol() . $amount . '.<br>' . __( ' Status : Failed ', 'funnelkit-stripe-woo-payment-gateway' ) );
				Helper::log( $response['message'] );

				return new \WP_Error( 'error', $response['message'] );
			}
		} catch ( \Exception $e ) {
			Helper::log( $e->getMessage() );
		}
	}


	/**
	 * Generate Customer data for stripe customer id
	 *
	 * @param $args
	 *
	 * @return array
	 */
	protected function generate_customer_request( $args = [] ) {
		$billing_email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing

		$user_id = get_current_user_id();
		$user    = null;
		if ( $user_id > 0 ) {
			$user               = get_user_by( 'id', $user_id );
			$billing_first_name = get_user_meta( $user->ID, 'billing_first_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
			$billing_last_name  = get_user_meta( $user->ID, 'billing_last_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta

			/** If billing first name does not exists try the user first name */
			if ( empty( $billing_first_name ) ) {
				$billing_first_name = get_user_meta( $user->ID, 'first_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
			}

			/** If billing last name does not exists try the user last name */
			if ( empty( $billing_last_name ) ) {
				$billing_last_name = get_user_meta( $user->ID, 'last_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
			}

			// translators: %1$s First name, %2$s Second name, %3$s Username.
			$description = sprintf( __( 'Name: %1$s %2$s, Username: %3$s', 'funnelkit-stripe-woo-payment-gateway' ), $billing_first_name, $billing_last_name, $user->user_login );

			$defaults = [
				'email'       => $user->user_email,
				'description' => $description,
			];

			$billing_full_name = trim( $billing_first_name . ' ' . $billing_last_name );
			if ( ! empty( $billing_full_name ) ) {
				$defaults['name'] = $billing_full_name;
			}
		} else {
			$billing_first_name = isset( $_POST['billing_first_name'] ) ? wc_clean( wp_unslash( $_POST['billing_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$billing_last_name  = isset( $_POST['billing_last_name'] ) ? wc_clean( wp_unslash( $_POST['billing_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			// translators: %1$s First name, %2$s Second name.
			$description = sprintf( __( 'Name: %1$s %2$s, Guest', 'funnelkit-stripe-woo-payment-gateway' ), $billing_first_name, $billing_last_name );

			$defaults = [
				'email'       => $billing_email,
				'description' => $description,
			];

			$billing_full_name = trim( $billing_first_name . ' ' . $billing_last_name );
			if ( ! empty( $billing_full_name ) ) {
				$defaults['name'] = $billing_full_name;
			}
		}

		$defaults['metadata'] = apply_filters( 'fkwcs_stripe_customer_metadata', [], $user );

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Handle API response
	 *
	 * @param $response
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function handle_client_response( $response, $throw_exception = true ) {
		if ( true === wc_string_to_bool( $response['success'] ) ) {
			return $response['data'];
		}

		$localized_messages = Helper::get_localized_messages();
		$localized_message  = '';

		if ( 'card_error' === wc_clean( $response['type'] ) ) {
			if ( $response['code'] === 'card_declined' ) {
				if ( isset( $response['decline_code'] ) ) {
					$localized_message = isset( $localized_messages[ $response['decline_code'] ] ) ? $localized_messages[ wc_clean( $response['decline_code'] ) ] : wc_clean( $response['message'] );
				}
			} else {
				$localized_message = isset( $localized_messages[ $response['code'] ] ) ? $localized_messages[ wc_clean( $response['code'] ) ] : wc_clean( $response['message'] );

			}

		} else {
			$localized_message = isset( $localized_messages[ $response['type'] ] ) ? $localized_messages[ wc_clean( $response['type'] ) ] : wc_clean( $response['message'] );

		}

		if ( $throw_exception ) {
			throw new \Exception( $localized_message );

		} else {
			return (object) $response;
		}
	}

	/**
	 * Validates minimum order amount requirement
	 *
	 * @param $order
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < Helper::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new \Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'funnelkit-stripe-woo-payment-gateway' ), wc_price( Helper::get_minimum_amount() / 100 ) ) );
		}
	}


	/**
	 * Verifies whether a certain ZIP code is valid for the US, incl. 4-digit extensions.
	 *
	 * @param string $zip The ZIP code to verify.
	 *
	 * @return boolean
	 */
	public function is_valid_us_zip_code( $zip ) {
		return ! empty( $zip ) && preg_match( '/^\d{5,5}(-\d{4,4})?$/', $zip );
	}

	/**
	 * Create payment intent using source
	 *
	 * @param $order
	 * @param $prepared_source
	 * @param $data
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	public function make_payment_by_source( $order, $prepared_source, $data ) {
		$intent_data = [];
		if ( apply_filters( 'fkwcs_execute_payment_intent', true, $order, $prepared_source, $data ) ) {
			$stripe_api  = $this->get_client();
			$response    = $stripe_api->payment_intents( 'create', [ $data ] );
			$intent_data = $this->handle_client_response( $response );
		}

		return apply_filters( 'fkwcs_execute_payment_intent_data', $intent_data, $order, $prepared_source, $data );
	}

	/**
	 * @param $order \WC_Order
	 * @param $prepared_source
	 * @param $data
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	public function make_payment( $order, $prepared_source, $data ) {
		$intent_data = [];
		if ( apply_filters( 'fkwcs_execute_payment_intent', true, $order, $prepared_source, $data ) ) {
			$idempotency_key = $prepared_source->source . '_' . $order->get_order_key();
			$intent_data     = $this->get_payment_intent( $order, $idempotency_key, $data );

		}

		return apply_filters( 'fkwcs_execute_payment_intent_data', $intent_data, $order, $prepared_source, $data );
	}

	/**
	 * Get payment intent from order meta
	 *
	 * @param \WC_Order $order
	 * @param $idempotency_key
	 * @param $args
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_payment_intent( $order, $idempotency_key, $args ) {
		$stripe_api    = $this->get_client();
		$intent_secret = Helper::get_meta( $order, '_fkwcs_intent_id' );
		$retry_count   = Helper::get_meta( $order, '_fkwcs_retry_count' );
		if ( ! empty( $intent_secret ) ) {
			$secret   = $intent_secret;
			$response = $stripe_api->payment_intents( 'retrieve', [ $secret['id'] ] );
			if ( $response['success'] && ( 'succeeded' === $response['data']->status || 'success' === $response['data']->status ) ) {
				/**
				 * this code here confirms if we have the intent in the order meta and that intent is succeeded
				 * then we need to go ahead and mark the order complete in WooCommerce
				 */
				$this->save_payment_method( $order, $response['data'] );
				$redirect_url = $this->process_final_order( end( $response['data']->charges->data ), $order->get_id() );
				wp_send_json( apply_filters( 'fkwcs_card_payment_return_intent_data', [
					'result'   => 'success',
					'redirect' => $redirect_url
				] ) );
			}
		}

		if ( empty( $args['customer'] ) ) {
			unset( $args['customer'] );
		}

		if ( ! empty( $retry_count ) ) {
			$idempotency_key = $idempotency_key . '_' . $retry_count;

		}
		$args = apply_filters( 'fkwcs_payment_intent_data', $args, $order );

		$args     = [
			[ $args ],
			[ 'idempotency_key' => $idempotency_key ],
		];
		$response = $stripe_api->payment_intents( 'create', $args );
		$intent   = $this->handle_client_response( $response );

		if ( empty( $retry_count ) ) {
			$order->update_meta_data( '_fkwcs_retry_count', 1 );
		} else {
			$order->update_meta_data( '_fkwcs_retry_count', absint( $retry_count ) + 1 );

		}
		$this->save_intent_to_order( $order, $intent );


		return $intent;
	}

	/**
	 * Get stripe customer ID account from order meta
	 *
	 * @param $order
	 *
	 * @return array|mixed|string|null
	 */
	public function get_order_stripe_customer( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		$customer_key = '_fkwcs_customer_id';

		$customer_id = Helper::get_meta( $order, $customer_key );
		if ( ! empty( $customer_id ) ) {
			return $customer_id;
		}

		$customer = false;
		if ( ! $customer_id ) {
			$customer = $this->create_stripe_customer( $order, $order->get_billing_email() );
		}

		if ( $customer ) {
			$order->update_meta_data( $customer_key, $customer->id );
			$order->save();

			return $customer->id;
		}

		return null;
	}

	/**
	 * Get/Retrieve stripe customer ID if exists
	 *
	 * @param \WC_Order $order current woocommerce order.
	 *
	 * @return mixed customer id
	 */
	public function get_customer_id( $order = false ) {
		$user = wp_get_current_user();

		$user_id = ( $user->ID && $user->ID > 0 ) ? $user->ID : false;

		if ( $order instanceof \WC_Order && 0 !== $order->get_customer_id() ) {
			$user_id = $order->get_customer_id();
		}
		if ( false === $user_id ) {
			return $this->get_order_stripe_customer( $order );
		}

		$customer_key = '_fkwcs_customer_id';
		$customer_id  = get_user_option( $customer_key, $user_id );
		if ( $customer_id ) {
			return $customer_id;
		}

		/**
		 * Try and get stripe customer ID from the WooCommerce stripe
		 */
		$customer_id = get_user_option( '_stripe_customer_id', $user_id );
		if ( $customer_id ) {
			return $customer_id;
		}

		$customer = false;
		if ( ! $customer_id ) {
			$customer = $this->create_stripe_customer( $order, $user->email );
		}

		if ( $customer ) {
			if ( $user_id ) {
				update_user_option( $user_id, $customer_key, $customer->id, false );
			}

			return $customer->id;
		}
	}

	/**
	 * Creates stripe customer object
	 *
	 * @param object $order woocommerce order object.
	 * @param boolean|string $user_email user email id.
	 *
	 * @return array|false
	 *
	 */
	public function create_stripe_customer( $order = false, $user_email = false ) {
		$args = [];

		if ( $order instanceof \WC_Order ) {
			$args = [
				'description' => __( 'Customer for Order #', 'funnelkit-stripe-woo-payment-gateway' ) . $order->get_order_number(),
				'email'       => $user_email ? $user_email : $order->get_billing_email(),
				'address'     => [ // sending name and billing address to stripe to support indian exports.
					'city'        => method_exists( $order, 'get_billing_city' ) ? $order->get_billing_city() : $order->billing_city,
					'country'     => method_exists( $order, 'get_billing_country' ) ? $order->get_billing_country() : $order->billing_country,
					'line1'       => method_exists( $order, 'get_billing_address_1' ) ? $order->get_billing_address_1() : $order->billing_address_1,
					'line2'       => method_exists( $order, 'get_billing_address_2' ) ? $order->get_billing_address_2() : $order->billing_address_2,
					'postal_code' => method_exists( $order, 'get_billing_postcode' ) ? $order->get_billing_postcode() : $order->billing_postcode,
					'state'       => method_exists( $order, 'get_billing_state' ) ? $order->get_billing_state() : $order->billing_state,
				],
				'name'        => ( method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name ) . ' ' . ( method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name ),
			];
		} else {
			$user_id = get_current_user_id();

			$user               = get_user_by( 'id', $user_id );
			$billing_first_name = get_user_meta( $user->ID, 'billing_first_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
			$billing_last_name  = get_user_meta( $user->ID, 'billing_last_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta

			/** If billing first name does not exists try the user first name */
			if ( empty( $billing_first_name ) ) {
				$billing_first_name = get_user_meta( $user->ID, 'first_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
			}

			/** If billing last name does not exists try the user last name */
			if ( empty( $billing_last_name ) ) {
				$billing_last_name = get_user_meta( $user->ID, 'last_name', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
			}

			// translators: %1$s First name, %2$s Second name, %3$s Username.
			$description = sprintf( __( 'Name: %1$s %2$s, Username: %3$s', 'funnelkit-stripe-woo-payment-gateway' ), $billing_first_name, $billing_last_name, $user->user_login );

			$args = [
				'email'       => $user->user_email,
				'description' => $description,
			];

			$billing_full_name = trim( $billing_first_name . ' ' . $billing_last_name );
			if ( ! empty( $billing_full_name ) ) {
				$args['name'] = $billing_full_name;
			}

		}

		$args     = apply_filters( 'fkwcs_create_stripe_customer_args', $args );
		$client   = $this->get_client();
		$response = $client->customers( 'create', [ $args ] );
		$response = $response['success'] ? $response['data'] : false;
		if ( empty( $response->id ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Returns amount as per currency type
	 *
	 * @param string $total amount to be processed.
	 * @param string $currency transaction currency.
	 *
	 * @return int
	 */
	public function get_formatted_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::$zero_currencies, true ) ) {
			/** Zero decimal currencies accepted by stripe */
			return absint( $total );
		}

		return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) ); // In cents.
	}

	/**
	 * Get Order description string
	 *
	 * @param \WC_Order $order
	 *
	 * @return string
	 */
	public function get_order_description( $order ) {


		return apply_filters( 'fkwcs_get_order_description', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' - ' . __( 'Order', 'woocommerce' ) . " " . $order->get_order_number(), $order );
	}

	/**
	 * Checks conditions whether current card should be saved or not
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return boolean
	 */
	public function should_save_card( $order ) {  //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
		return true;
	}


	/**
	 * Create setup intent
	 *
	 * @param $source
	 * @param $customer_id
	 * @param $type
	 * @param $confirm
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function create_setup_intent( $source, $customer_id = '', $order = false, $type = 'card' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
		$customer_id = ! empty( $customer_id ) ? $customer_id : $this->get_customer_id();
		$client      = $this->get_client();
		$response    = apply_filters( 'fkwcs_payment_intent_data', [
			'payment_method_types' => [ $type ],
			'payment_method'       => $source,
			'customer'             => $customer_id
		], $order, true );

		$response = $client->setup_intents( 'create', [ $response ] );
		$obj      = $this->handle_client_response( $response );

		return $obj;
	}

	/**
	 * Confirm setup intent
	 *
	 * @param $source
	 * @param $customer_id
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function confirm_setup_intent( $source, $customer_id ) {
		$client   = $this->get_client();
		$response = [
			'payment_method_types' => [ 'card' ],
			'payment_method'       => $source,
			'customer'             => $customer_id,
			'off_session'          => 'true'
		];
		$response = $client->setup_intents( 'confirm', [ $response ] );
		$obj      = $this->handle_client_response( $response );

		return $obj;
	}

	/**
	 * Checks if type payment method
	 *
	 * @param $source_id
	 *
	 * @return false|int
	 */
	public function is_type_payment_method( $source_id ) {
		return ( preg_match( '/^pm_/', $source_id ) );
	}

	/**
	 * Get intent from the order
	 *
	 * @param $order \WC_Order
	 *
	 * @return false|mixed
	 * @throws \Exception
	 */
	public function get_intent_from_order( $order ) {
		$intent = Helper::get_meta( $order, '_fkwcs_intent_id' );


		$client = $this->get_client();
		if ( ! empty( $intent ) ) {
			$response = $client->payment_intents( 'retrieve', [ $intent['id'] ] );
			$obj      = $this->handle_client_response( $response );

			return $obj;
		}

		/** The order doesn't have a payment intent, but it may have a setup intent. */
		$intent = Helper::get_meta( $order, '_fkwcs_setup_intent' );


		if ( ! empty( $intent ) ) {
			$response = $client->setup_intents( 'retrieve', [ $intent['id'] ] );
			$obj      = $this->handle_client_response( $response );

			return $obj;
		}

		return false;
	}

	/**
	 * Prepare order source for API call
	 *
	 * @param $order \WC_Order
	 *
	 * @return object
	 */
	public function prepare_order_source( $order = null ) {
		if ( ! $order instanceof \WC_Order ) {
			return (object) [
				'customer'       => false,
				'source'         => false,
				'source_object'  => false,
				'payment_method' => null,
			];
		}

		$client             = $this->get_client();
		$stripe_customer_id = $this->get_customer_id( $order );

		$stripe_source = false;
		$source_object = false;

		$source_id = Helper::get_meta( $order, '_fkwcs_source_id' );
		if ( $source_id ) {
			$stripe_source = $source_id;
			$response      = $client->payment_methods( 'retrieve', [ $source_id ] );
			$source_object = $response['success'] ? $response['data'] : false;
		} elseif ( apply_filters( 'fkwcs_stripe_use_default_customer_source', true ) ) {
			/*
			 * We can attempt to charge the customer's default source
			 * by sending empty source id.
			 */
			$stripe_source = '';
		}

		return (object) [
			'customer'       => $stripe_customer_id,
			'source'         => $stripe_source,
			'source_object'  => $source_object,
			'payment_method' => null,
		];
	}

	/**
	 * Handle free order
	 *
	 * @param $order \WC_Order
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function complete_free_order( $order ) {
		$result = $this->process_change_subscription_payment_method( $order->get_id() );
		if ( ! $result ) {
			throw new \Exception( __( 'Unable to process', 'FunnelKit-stripe-woo-payment-gateaway' ), 200 );
		}

		return $result;
	}

	/**
	 * Create a SetupIntent for future payments, and saves it to the order
	 *
	 * @param $order
	 * @param $prepared_source
	 *
	 * @return mixed The client secret of the intent, used for confirmation in JS.
	 * @throws \Exception
	 */
	public function setup_intent( $order, $prepared_source ) {
		$client = $this->get_client();

		$data = [
			'payment_method'       => $prepared_source->source,
			'customer'             => $prepared_source->customer,
			'payment_method_types' => [ 'card' ],
			'usage'                => 'off_session',
		];

		$response    = $client->setup_intents( 'create', [ $data ] );
		$obj         = $this->handle_client_response( $response );
		$intent_data = [
			'id'            => $obj->id,
			'client_secret' => $obj->client_secret,
		];
		$order->update_meta_data( '_fkwcs_setup_intent', $intent_data );
		$order->save();

		return $obj;
	}

	/**
	 * Log exception or error before redirecting
	 *
	 * @param $e
	 * @param $redirect_url
	 *
	 * @return void
	 */
	protected function handle_error( $e, $redirect_url ) {
		$message = sprintf( 'PaymentIntent verification exception: %s', $e->getMessage() );
		Helper::log( $message );

		/** `is_ajax` is only used for PI error reporting, a response is not expected */
		if ( isset( $_GET['is_ajax'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			exit;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Request for charge contains the metadata for the intent
	 *
	 * @param $order \WC_Order
	 * @param $prepared_source
	 * @param $amount
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function create_and_confirm_intent_for_off_session( $order, $prepared_source ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.Variables.VariableAnalysis.UnusedParameter


		$request = [
			'payment_method'       => $prepared_source->source,
			'payment_method_types' => [ $this->payment_method_types ],
			'amount'               => Helper::get_stripe_amount( $order->get_total(), strtolower( $order->get_currency() ) ),
			'currency'             => strtolower( $order->get_currency() ),
			'description'          => $this->get_order_description( $order ),
			'customer'             => $prepared_source->customer,
			'off_session'          => 'true',
			'confirm'              => 'true',
			'confirmation_method'  => 'automatic',
			'statement_descriptor' => $this->clean_statement_descriptor( Helper::get_gateway_descriptor() )

		];

		if ( empty( $prepared_source->source ) ) {
			unset( $request['payment_method'] );
		}
		if ( isset( $prepared_source->customer ) ) {
			$request['customer'] = $prepared_source->customer;
		}
		$request['metadata'] = $this->add_metadata($order);
		$request  = apply_filters( 'fkwcs_payment_intent_data', $request, $order );
		$client   = $this->get_client();
		$response = $client->payment_intents( 'create', [ $request ] );

		return (object) $response;
	}

	/**
	 * Checks if authentication required for payment in the response
	 *
	 * @param $response
	 *
	 * @return bool
	 */
	public function is_authentication_required_for_payment( $response ) {
		return ( ! empty( $response->error ) && 'authentication_required' === $response->error->code ) || ( ! empty( $response->last_payment_error ) && 'authentication_required' === $response->last_payment_error->code );
	}

	/**
	 * @param $source_object
	 * @param $error
	 *
	 * @return bool
	 */
	public function need_update_idempotency_key( $source_object, $error ) {
		return ( $error && 1 < $this->retry_interval && ! empty( $source_object ) && 'chargeable' === $source_object->status && $this->is_same_idempotency_error( $error ) );
	}

	/**
	 * Checks if any error in the given argument
	 *
	 * @param $error
	 *
	 * @return bool
	 */
	public function is_no_such_source_error( $error ) {
		return ( $error && ( 'invalid_request_error' === $error->type || 'payment_method' === $error->type ) && preg_match( '/No such (source|PaymentMethod)/i', $error->message ) );
	}

	/**
	 * Checks if source missing error
	 *
	 * @param $error
	 *
	 * @return bool
	 */
	public function is_no_linked_source_error( $error ) {
		return ( $error && ( 'invalid_request_error' === $error->type || 'payment_method' === $error->type ) && preg_match( '/does not have a linked source with ID/i', $error->message ) );
	}

	/**
	 * Checks to see if error is of same idempotency key
	 * Error due to retries with different parameters
	 *
	 * @param $error
	 *
	 * @return bool
	 */
	public function is_same_idempotency_error( $error ) {
		return ( $error && 'idempotency_error' === $error->type && preg_match( '/Keys for idempotent requests can only be used with the same parameters they were first used with./i', $error->message ) );
	}

	/**
	 * Locks an order for payment intent processing for 5 minutes.
	 *
	 * @param \WC_Order $order The order that is being paid.
	 * @param \stdClass $intent The intent that is being processed.
	 *
	 * @return bool            A flag that indicates whether the order is already locked.
	 */
	public function lock_order_payment( $order, $intent = null ) {
		$order_id       = $order->get_id();
		$transient_name = 'fkwcs_stripe_processing_intent_' . $order_id;
		$processing     = get_transient( $transient_name );

		/** Block the process if the same intent is already being handled */
		if ( '-1' === $processing || ( isset( $intent->id ) && $processing === $intent->id ) ) {
			return true;
		}

		/** Save the new intent as a transient, eventually overwriting another one */
		set_transient( $transient_name, empty( $intent ) ? '-1' : $intent->id, 5 * MINUTE_IN_SECONDS );

		return false;
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 *
	 * @param \WC_Order $order The order that is being unlocked.
	 */
	public function unlock_order_payment( $order ) {
		$order_id = $order->get_id();
		delete_transient( 'fkwcs_stripe_processing_intent_' . $order_id );
	}

	/**
	 * Save the intent data in the order
	 *
	 * @param $order \WC_Order
	 * @param $intent
	 *
	 * @return void
	 */
	public function save_intent_to_order( $order, $intent ) {
		if ( 'payment_intent' === $intent->object ) {
			Helper::add_payment_intent_to_order( $intent, $order );
		} elseif ( 'setup_intent' === $intent->object ) {
			$order->update_meta_data( '_fkwcs_setup_intent', $intent->id );
		}
		$charge = $this->get_latest_charge_from_intent( $intent );

		if ( isset( $charge->payment_method_details->card->mandate ) ) {
			$mandate_id = $charge->payment_method_details->card->mandate;
			$order->update_meta_data( '_stripe_mandate_id', $mandate_id );
		}
		if ( is_callable( [ $order, 'save_meta_data' ] ) ) {
			$order->save_meta_data();
		}
	}

	/**
	 * Checks if a retryable error
	 *
	 * @param $error
	 *
	 * @return bool
	 */
	public function is_retryable_error( $error ) {
		if ( isset( $error->code ) && 'payment_intent_mandate_invalid' === $error->code ) {
			return false;
		}

		return ( 'invalid_request_error' === $error->type || 'idempotency_error' === $error->type || 'rate_limit_error' === $error->type || 'api_connection_error' === $error->type || 'api_error' === $error->type );
	}

	/**
	 * Checks if a current page is a product page
	 *
	 * @return bool
	 */
	public function is_product() {
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
	}

	/**
	 * Checks if a current page is a cart page
	 *
	 * @return bool
	 */
	public function is_cart() {
		return is_cart() || wc_post_content_has_shortcode( 'woocommerce_cart' );
	}

	/**
	 * Checks if a current page is a checkout page
	 *
	 * @return bool
	 */
	public function is_checkout() {
		return is_checkout() || wc_post_content_has_shortcode( 'woocommerce_checkout' );
	}

	/**
	 * Prepare source OR payment method
	 *
	 * @param $order
	 * @param $force_save_source
	 *
	 * @return object|void
	 */
	public function prepare_source( $order, $force_save_source = false ) {
		$customer_id    = $this->get_customer_id( $order );
		$source_object  = '';
		$source_id      = '';
		$payment_method = 'fkwcs_stripe';
		$stripe_api     = $this->get_client();

		/** New CC info was entered and we have a new source to process */
		if ( ! empty( $_POST['fkwcs_source'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$stripe_source = wc_clean( wp_unslash( $_POST['fkwcs_source'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$response      = $stripe_api->payment_methods( 'retrieve', [ $stripe_source ] );
			$source_object = $response['success'] ? $response['data'] : false;
			if ( ! $source_object ) {
				return;
			}
			$source_id = $source_object->id;
			if ( true === $force_save_source ) {
				// Attach Source to customer
				$response      = $stripe_api->payment_methods( 'attach', [ $source_id, [ 'customer' => $customer_id ] ] );
				$source_object = $response['success'] ? $response['data'] : '';
			}
		}

		/** Get payment source id by token id */
		if ( empty( $source_id ) && ! empty( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) && 'new' !== $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$token_id      = wc_clean( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$token_details = \WC_Payment_Tokens::get( $token_id );
			$source_id     = $token_details->get_token();
		}

		if ( empty( $source_object ) ) {
			$response      = $stripe_api->payment_methods( 'retrieve', [ $source_id ] );
			$source_object = $response['success'] ? $response['data'] : false;
		}
		if ( ! empty( $source_object ) && empty( $source_object->customer ) && ! empty( $customer_id ) ) {
			$source_object->customer = $customer_id;
		}

		return Helper::prepare_payment_method( $source_object, false );
	}

	/**
	 * Setup refund request.
	 *
	 * @param object $order order.
	 * @param string $amount refund amount.
	 * @param string $reason reason of refund.
	 * @param string $intent_secret_id secret key.
	 *
	 * @return array|\WP_Error
	 */
	public function create_refund_request( $order, $amount, $reason, $intent_secret_id ) {
		$response = $this->get_client()->payment_intents( 'retrieve', [ $intent_secret_id ] );
		$status   = $response['success'] && isset( $response['data']->charges->data[0]->captured ) ? $response['data']->charges->data[0]->captured : false;
		if ( ! $status ) {
			Helper::log( 'Un captured Amount cannot be refunded' );

			return new \WP_Error( 'error', __( 'Un captured Amount cannot be refunded', 'funnelkit-stripe-woo-payment-gateway' ) );
		}

		$intent_response = $response['data'];
		$currency        = $intent_response->currency;
		$client_details  = $this->get_client()->get_clients_details();
		$refund_params   = apply_filters( 'fkwcs_refund_request_args', [
			'payment_intent' => $intent_secret_id,
			'amount'         => $this->get_formatted_amount( $amount, $currency ),
			'reason'         => 'requested_by_customer',
			'metadata'       => [
				'order_id'          => $order->get_order_number(),
				'customer_ip'       => $client_details['ip'],
				'agent'             => $client_details['agent'],
				'referer'           => $client_details['referer'],
				'reason_for_refund' => $reason,
			],
		] );

		return $this->execute_refunds( $refund_params );
	}

	/**
	 * Execute refunds
	 *
	 * @param array $params a full config to support API call for the refunds
	 * https://stripe.com/docs/api/refunds/create
	 *
	 * @return array
	 */
	public function execute_refunds( $params ) {
		return $this->get_client()->refunds( 'create', [ $params ] );
	}

	/**
	 * Get the transaction URL linked to Stripe dashboard
	 *
	 * @param $order
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		if ( 'test' === $this->test_mode ) {
			$this->view_transaction_url = 'https://dashboard.stripe.com/test/payments/%s';
		} else {
			$this->view_transaction_url = 'https://dashboard.stripe.com/payments/%s';
		}

		return parent::get_transaction_url( $order );
	}

	/**
	 * Modify WC public endpoints
	 *
	 * @return mixed|null
	 */
	public static function get_available_public_endpoints() {
		$endpoints = [
			'fkwcs_button_payment_request'  => 'process_smart_checkout',
			'wc_stripe_create_order'        => 'process_smart_checkout',
			'fkwcs_update_shipping_address' => 'update_shipping_address',
			'fkwcs_update_shipping_option'  => 'update_shipping_option',
			'fkwcs_add_to_cart'             => 'ajax_add_to_cart',
			'fkwcs_selected_product_data'   => 'ajax_fkwcs_selected_product_data',
			'fkwcs_get_cart_details'        => 'ajax_get_cart_details',
		];

		return apply_filters( 'fkwcs_public_endpoints', $endpoints );
	}

	public static function get_public_endpoints() {
		$public_endpoints = self::get_available_public_endpoints();
		if ( empty( $public_endpoints ) || 0 === count( $public_endpoints ) ) {
			return [];
		}

		$endpoints = [];
		foreach ( $public_endpoints as $key => $function ) {
			$endpoints[ $key ] = \WC_AJAX::get_endpoint( $key );
		}

		return $endpoints;
	}

	/**
	 * All payment gateways icons that work with Stripe. Some icons references
	 * WC core icons.
	 *
	 * @return array
	 */
	public function payment_icons() {
		return apply_filters( 'fkwcs_stripe_payment_icons', [
			'bancontact' => '<img src="' . FKWCS_URL . 'assets/icons/bancontact.svg" class="stripe-bancontact-icon stripe-icon" alt="Bancontact" />',
			'ideal'      => '<img src="' . FKWCS_URL . 'assets/icons/ideal.svg" class="stripe-ideal-icon stripe-icon" alt="iDEAL" />',
			'p24'        => '<img src="' . FKWCS_URL . 'assets/icons/p24.svg" class="stripe-p24-icon stripe-icon" alt="P24" />',
			'sepa'       => '<img src="' . FKWCS_URL . 'assets/icons/sepa.svg" class="stripe-sepa-icon stripe-icon" alt="SEPA" />',
		] );
	}

	/**
	 * Get contact details from WC order
	 *
	 * @param $order \WC_Order
	 *
	 * @return object $details
	 */
	public function get_owner_details( $order ) {
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();

		$details = [];

		$name  = $billing_first_name . ' ' . $billing_last_name;
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone'] = $phone;
		}

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $email ) ) {
			$details['email'] = $email;
		}

		$details['address']['line1']       = $order->get_billing_address_1();
		$details['address']['line2']       = $order->get_billing_address_2();
		$details['address']['state']       = $order->get_billing_state();
		$details['address']['city']        = $order->get_billing_city();
		$details['address']['postal_code'] = $order->get_billing_postcode();
		$details['address']['country']     = $order->get_billing_country();

		return apply_filters( 'fkwcs_stripe_owner_details', $details, $order );
	}

	/**
	 * Get return URL
	 *
	 * @param $order
	 * @param $id
	 *
	 * @return string
	 */
	public function get_stripe_return_url( $order = null, $id = null ) {
		if ( is_object( $order ) ) {
			if ( empty( $id ) ) {
				$id = uniqid();
			}

			$order_id = $order->get_id();

			$args       = [
				'utm_nooverride' => '1',
				'order_id'       => $order_id,
			];
			$return_url = $this->get_return_url( $order );
			Helper::log( "Return URL: $return_url" );

			return wp_sanitize_redirect( esc_url_raw( add_query_arg( $args, $return_url ) ) );
		}

		$return_url = $this->get_return_url();
		Helper::log( "Return URL: $return_url" );

		return wp_sanitize_redirect( esc_url_raw( add_query_arg( [ 'utm_nooverride' => '1' ], $this->get_return_url() ) ) );
	}

	/**
	 * Get WooCommerce store currency
	 *
	 * @return string
	 */
	public function get_currency() {
		global $wp;

		if ( isset( $wp->query_vars['order-pay'] ) ) {
			$order = wc_get_order( absint( $wp->query_vars['order-pay'] ) );

			return $order->get_currency();
		}

		return get_woocommerce_currency();
	}

	/**
	 * Get billing country for gateways
	 *
	 * @return string $billing_country
	 */
	public function get_billing_country() {
		global $wp;

		if ( isset( $wp->query_vars['order-pay'] ) ) {
			$order           = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			$billing_country = $order->get_billing_country();
		} else {
			$customer        = WC()->customer;
			$billing_country = $customer ? $customer->get_billing_country() : null;

			if ( ! $billing_country ) {
				$billing_country = WC()->countries->get_base_country();
			}
		}

		return $billing_country;
	}

	/**
	 * Return a description for (admin sections) describing the required currency & or billing country(s).
	 *
	 * @return string
	 */
	public function payment_description() {
		$desc = '';
		if ( method_exists( $this, 'get_supported_currency' ) && $this->get_currency() ) {
			// translators: %s: supported currency.
			$desc = sprintf( __( 'This gateway supports the following currencies only : <strong>%s</strong>.', 'funnelkit-stripe-woo-payment-gateway' ), implode( ', ', $this->get_supported_currency() ) );
		}

		return $this->get_description( $desc );
	}

	/**
	 * Get default form fields
	 *
	 * @return mixed|null
	 */
	public function get_default_settings() {
		$method_title = $this->method_title;

		$settings = [
			'enabled'     => [
				'label'   => ' ',
				'type'    => 'checkbox',
				// translators: %s: Method title.
				'title'   => sprintf( __( 'Enable %s', 'funnelkit-stripe-woo-payment-gateway' ), $method_title ),
				'default' => 'no',
			],
			'title'       => [
				'title'       => __( 'Title', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'text',
				// translators: %s: Method title.
				'description' => sprintf( __( 'Title of the %s gateway.', 'funnelkit-stripe-woo-payment-gateway' ), $method_title ),
				'default'     => $method_title,
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Description', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'textarea',
				'css'         => 'width:25em',
				/* translators: gateway title */
				'description' => sprintf( __( 'Description of the %1s gateway.', 'funnelkit-stripe-woo-payment-gateway' ), $method_title ),
				'desc_tip'    => true,
			]
		];

		$settings = array_merge( $settings, $this->get_countries_admin_fields() );

		return apply_filters( 'fkwcs_default_methods_default_settings', $settings );
	}

	/**
	 * @param $location Selling location for gateway
	 * @param $except_country Except country for gateway
	 * @param $specific_country specific country for gateway
	 *
	 * @return array[]
	 */
	public function get_countries_admin_fields( $location = 'all', $except_country = [], $specific_country = [] ) {
		return [
			'allowed_countries'  => [
				'title'       => __( 'Selling location(s)', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => $location,
				'type'        => 'select',
				'class'       => 'wc-enhanced-select wc-stripe-allowed-countries fkwcs-allowed-countries',
				'css'         => 'min-width: 350px;',
				'desc_tip'    => true,
				/* translators: gateway title */
				'description' => sprintf( __( 'This option lets you limit the %1$s to which countries you are willing to sell to.', 'funnelkit-stripe-woo-payment-gateway' ), $this->method_title ),
				'options'     => array(
					'all'        => __( 'Sell to all countries', 'funnelkit-stripe-woo-payment-gateway' ),
					'all_except' => __( 'Sell to all countries, except for&hellip;', 'funnelkit-stripe-woo-payment-gateway' ),
					'specific'   => __( 'Sell to specific countries', 'funnelkit-stripe-woo-payment-gateway' ),
				),
			],
			'except_countries'   => [
				'title'             => __( 'Sell to all countries, except for&hellip;', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'              => 'multi_select_countries',
				'options'           => [],
				'default'           => $except_country,
				'class'             => 'fkwcs-except-countries',
				'desc_tip'          => true,
				'css'               => 'min-width: 350px;',
				'description'       => __( 'If any of the selected countries matches with the customer\'s billing country, then this payment method will not be visible on the checkout page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'sanitize_callback' => function ( $value ) {
					return is_array( $value ) ? $value : array();
				},
			],
			'specific_countries' => [
				'title'             => __( 'Sell to specific countries', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'              => 'multi_select_countries',
				'options'           => [],
				'default'           => $specific_country,
				'desc_tip'          => true,
				'class'             => 'fkwcs-specific-countries',
				'css'               => 'min-width: 350px;',
				'description'       => __( 'If any of the selected countries matches with the customer\'s billing country, then this payment method will be visible on the checkout page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'sanitize_callback' => function ( $value ) {
					return is_array( $value ) ? $value : array();
				},
			],
		];
	}


	/**
	 * Prepare shipping data to pass onto api calls
	 *
	 * @param array $data
	 * @param \WC_Order $order
	 *
	 * @return mixed
	 */
	public function set_shipping_data( $data, $order ) {
		if ( ! empty( $order->get_shipping_postcode() ) ) {
			$data['shipping'] = [

				/**
				 * Prepare shipping data for the api call
				 */
				'name'    => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
				'address' => [
					'line1'       => $order->get_shipping_address_1(),
					'line2'       => $order->get_shipping_address_2(),
					'city'        => $order->get_shipping_city(),
					'country'     => $order->get_shipping_country(),
					'postal_code' => $order->get_shipping_postcode(),
					'state'       => $order->get_shipping_state(),
				],
			];
		}

		return $data;
	}


	/**
	 * Prepare metadata to the api calls to create charge/PI
	 *
	 * @param \WC_Order $order
	 *
	 * @return mixed
	 */
	public function add_metadata( $order ) {
		$metadata = [
			'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'customer_email' => $order->get_billing_email(),
			'order_number'   => $order->get_order_number(),
			'order_id'       => $order->get_id(),
			'site_url'       => esc_url( get_site_url() ),
			'wp_user_id'     => $order->get_user_id(),
			'customer_ip'    => $order->get_customer_ip_address(),
			'user_agent'     => wc_get_user_agent()
		];

		$items = [];
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$items[] = sprintf( '%s x %s', $item->get_name(), $item->get_quantity() );
		}
		if ( 500 > strlen( implode( ', ', $items ) ) ) {
			$metadata['products'] = implode( ', ', $items );

		}

		return apply_filters( 'fkwcs_payment_metadata', $metadata, $order );
	}

	/**
	 * Add metadata to stripe
	 *
	 * @param int $order_id WooCommerce order Id.
	 *
	 * @return array
	 *
	 */
	public function get_metadata( $order_id ) {
		$order              = wc_get_order( $order_id );
		$details            = [];
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();
		$name               = $billing_first_name . ' ' . $billing_last_name;

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $order->get_billing_email() ) ) {
			$details['email'] = $order->get_billing_email();
		}

		if ( ! empty( $order->get_billing_phone() ) ) {
			$details['phone'] = $order->get_billing_phone();
		}

		if ( ! empty( $order->get_billing_address_1() ) ) {
			$details['address'] = $order->get_billing_address_1();
		}

		if ( ! empty( $order->get_billing_city() ) ) {
			$details['city'] = $order->get_billing_city();
		}

		if ( ! empty( $order->get_billing_country() ) ) {
			$details['country'] = $order->get_billing_country();
		}

		$details['site_url'] = get_site_url();

		return apply_filters( 'fkwcs_metadata_details', $details, $order );
	}

	/**
	 * Allow gateways to declare whether they support offer refund
	 *
	 * @param \WC_Order $order
	 *
	 * @return bool
	 */
	public function is_refund_supported( $order = false ) {
		if ( $this->refund_supported ) {
			return apply_filters( 'fkwcs_payment_gateway_refund_supported', true, $order );
		}

		return false;
	}

	/**
	 * Checks if subscription plugin exists and order contains subscription items
	 *
	 * @param $order_id
	 *
	 * @return bool
	 */
	public function has_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}


	/**
	 * Get key value from the order meta or look for relative area
	 *
	 * @param string $meta_key
	 * @param \WC_Order $order
	 * @param string $context
	 *
	 * @return mixed
	 */
	public function get_order_stripe_data( $meta_key, $order ) {
		$value = Helper::get_meta( $order, $meta_key );

		if ( ! empty( $value ) ) {
			return $value;
		}

		/** value is empty so check metadata from other plugins */
		$keys = array();
		switch ( $meta_key ) {
			case '_fkwcs_source_id':
				$keys = Helper::get_compatibility_keys( '_fkwcs_source_id' );
				break;
			case '_fkwcs_customer_id':
				$keys = Helper::get_compatibility_keys( '_fkwcs_customer_id' );
				break;
			case '_fkwcs_intent_id':
				$keys = Helper::get_compatibility_keys( '_fkwcs_intent_id' );
		}

		if ( empty( $keys ) ) {
			return $value;
		}

		/**
		 * Now that we know we have meta found from other gateway, lets save the value as our key
		 */
		$meta_data = $order->get_meta_data();
		if ( $meta_data ) {
			$keys       = array_intersect( wp_list_pluck( $meta_data, 'key' ), $keys );
			$array_keys = array_keys( $keys );
			if ( ! empty( $array_keys ) ) {
				$value = $meta_data[ current( $array_keys ) ]->value;
				$order->update_meta_data( $meta_key, $value );
				$order->save_meta_data();
			}
		}

		return $value;
	}

	/**
	 * Checks if gateway is available
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}


		if ( false === $this->is_configured() ) {
			return false;
		}

		return true;
	}

	/**
	 * Create multiple countries selection HTML
	 *
	 * @param $key
	 * @param $data
	 *
	 * @return false|string
	 */
	public function generate_multi_select_countries_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$value     = (array) $this->get_option( $key );
		$data      = wp_parse_args( $data, array(
			'title'       => '',
			'class'       => '',
			'style'       => '',
			'description' => '',
			'desc_tip'    => false,
			'id'          => $field_key,
			'options'     => [],
		) );

		ob_start();

		if ( empty( $value ) ) {
			$value = $data['default'];

		}
		$selections = (array) $value;

		if ( ! empty( $data['options'] ) ) {
			$countries = array_intersect_key( WC()->countries->countries, array_flip( $data['options'] ) );
		} else {
			$countries = WC()->countries->countries;
		}

		asort( $countries );
		?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $data['id'] ); ?>"><?php echo esc_html( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); //phpcs:ignore ?></label>
            </th>
            <td class="forminp">
                <select multiple="multiple" name="<?php echo esc_attr( $data['id'] ); ?>[]" style="width:350px"
                        data-placeholder="<?php esc_attr_e( 'Choose countries / regions&hellip;', 'funnelkit-stripe-woo-payment-gateway' ); ?>"
                        aria-label="<?php esc_attr_e( 'Country / Region', 'funnelkit-stripe-woo-payment-gateway' ); ?>" class="wc-enhanced-select <?php esc_attr_e( $data['class'] ) ?>">
					<?php
					if ( ! empty( $countries ) ) {
						foreach ( $countries as $key => $val ) {
							echo '<option value="' . esc_attr( $key ) . '"' . wc_selected( $key, $selections ) . '>' . esc_html( $val ) . '</option>'; //phpcs:ignore
						}
					}
					?>
                </select>
				<?php echo $this->get_description_html( $data ); //phpcs:ignore ?>
                <br/>
                <a class="select_all button" href="#"><?php esc_html_e( 'Select all', 'funnelkit-stripe-woo-payment-gateway' ); ?></a>
                <a class="select_none button" href="#"><?php esc_html_e( 'Select none', 'funnelkit-stripe-woo-payment-gateway' ); ?></a>
            </td>
        </tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate countries from a given list
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return array|string
	 */
	public function validate_multi_select_countries_field( $key, $value ) {
		return is_array( $value ) ? array_map( 'wc_clean', array_map( 'stripslashes', $value ) ) : '';
	}

	/**
	 * Checks if a given payment gateway is available locally
	 * Invoked from gateways like iDeal, Sepa, Alipay etc
	 *
	 * @return bool
	 */
	public function is_available_local_gateway() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currency(), true ) ) {
			return false;
		}

		if ( ! empty( $this->get_option( 'allowed_countries' ) ) && 'all_except' === $this->get_option( 'allowed_countries' ) ) {
			return ! in_array( $this->get_billing_country(), $this->get_option( 'except_countries', array() ), true );
		} elseif ( ! empty( $this->get_option( 'allowed_countries' ) ) && 'specific' === $this->get_option( 'allowed_countries' ) ) {
			return in_array( $this->get_billing_country(), $this->get_option( 'specific_countries', array() ), true );
		}

		return parent::is_available();
	}

	/**
	 * Get test mode description
	 *
	 * @return string
	 */
	public function get_test_mode_description() {
		return '';
	}

	public function get_latest_charge_from_intent( $intent ) {
		if ( ! empty( $intent->charges->data ) ) {
			return end( $intent->charges->data );
		} else {
			return $this->get_charge_object( $intent->latest_charge );
		}
	}


	/**
	 * Get charge object by charge ID.
	 *
	 * @param string $charge_id The charge ID to get charge object for.
	 *
	 * @return string|object
	 * @throws \Exception Error while retrieving charge object.
	 * @since 1.2.0
	 */
	public function get_charge_object( $charge_id = '' ) {
		if ( empty( $charge_id ) ) {
			return '';
		}

		$charge_object = $this->get_client()->charges( 'GET', [ $charge_id ] );

		if ( $charge_object['success'] === false ) {
			throw new \Exception( $charge_object['success'] );
		}

		return $charge_object['data'];
	}


}
