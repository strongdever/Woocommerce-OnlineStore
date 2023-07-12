<?php

namespace FKWCS\Gateway\Stripe;


class AJAX {

	protected static $instance = null;

	private function __construct() {
		add_action( 'wp_ajax_fkwcs_create_setup_intent', [ $this, 'create_intent' ] );
		add_action( 'wc_ajax_fkwcs_stripe_sepa_verify_payment_intent', [ $this, 'verify_intent_sepa' ] );
		add_action( 'wc_ajax_fkwcs_stripe_verify_payment_intent', [ $this, 'verify_intent_card' ] );
		add_action( 'wc_ajax_wc_stripe_verify_intent_checkout', [ $this, 'verify_intent_card' ], - 1 );
		add_action( 'wc_ajax_fkwcs_stripe_ideal_verify_payment_intent', [ $this, 'verify_intent_ideal' ] );
		add_action( 'wc_ajax_fkwcs_stripe_p24_verify_payment_intent', [ $this, 'verify_intent_p24' ] );
		add_action( 'wc_ajax_fkwcs_stripe_bancontact_verify_payment_intent', [ $this, 'verify_intent_bancontact' ] );
		add_action( 'wc_ajax_wfocu_front_handle_fkwcs_stripe_payments', [ $this, 'ajax_for_upsells' ] );
		add_action( 'wc_ajax_wfocu_front_handle_fkwcs_sepa_payments', [ $this, 'ajax_for_upsells_sepa' ] );
		add_action( 'wp_ajax_fkwcs_js_errors', [ $this, 'log_frontend_error' ] );
		add_action( 'wp_ajax_nopriv_fkwcs_js_errors', [ $this, 'log_frontend_error' ] );
		add_action( 'woocommerce_payment_token_class', [ $this, 'modify_token_class' ], 15, 2 );

		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_request' ] );

	}


	/**
	 * @return Ajax
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	public function create_intent() {

		if ( empty( wc_clean( $_POST['fkwcs_source'] ) ) || empty( wc_clean( $_POST['fkwcs_nonce'] ) ) || ! wp_verify_nonce( wc_clean( $_POST['fkwcs_nonce'] ), 'fkwcs_nonce' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json( [ 'status' => false, 'message' => 'Something went wrong' ] );
		}
		Helper::log( 'Entering::' . __FUNCTION__ );

		$source = htmlspecialchars( wc_clean( $_POST['fkwcs_source'] ) );

		$response = WC()->payment_gateways()->payment_gateways()['fkwcs_stripe']->create_setup_intent( $source );

		$resp = [ 'status' => 'success', 'data' => $response ];

		wp_send_json( $resp );
	}

	public function verify_intent_card() {
		WC()->payment_gateways()->payment_gateways()['fkwcs_stripe']->verify_intent();
	}

	public function verify_intent_sepa() {
		WC()->payment_gateways()->payment_gateways()['fkwcs_stripe_sepa']->verify_intent();
	}

	public function verify_intent_ideal() {
		WC()->payment_gateways()->payment_gateways()['fkwcs_stripe_ideal']->verify_intent();
	}

	public function verify_intent_p24() {
		WC()->payment_gateways()->payment_gateways()['fkwcs_stripe_p24']->verify_intent();
	}

	public function verify_intent_bancontact() {
		WC()->payment_gateways()->payment_gateways()['fkwcs_stripe_bancontact']->verify_intent();
	}

	/**
	 * Added token class
	 *
	 * @param string $class token class name.
	 * @param string $type gateway name.
	 *
	 * @return string
	 *
	 */
	public function modify_token_class( $class, $type ) {
		if ( 'fkwcs_stripe_sepa' === $type ) {
			return 'FKWCS\Inc\Token';
		}

		return $class;
	}

	public function ajax_for_upsells() {
		WFOCU_Core()->gateways->get_integration( 'fkwcs_stripe' )->process_client_payment();
	}

	/**
	 * Log Frontend Error when payment throw error after submit button pressed
	 * @return void
	 */
	public function log_frontend_error() {
		$_security = wc_clean( filter_input( INPUT_POST, '_security' ) );
		if ( is_null( $_security ) || ! wp_verify_nonce( $_security, 'fkwcs_js_nonce' ) ) {
			wp_send_json_error( [ 'status' => 'false', 'message' => __( 'invalid nonce', 'funnelkit-stripe-woo-payment-gateway' ) ] );
		}
		if ( ! isset( $_POST['error'] ) ) {
			wp_send_json( [ 'status' => 'false' ] );
		}
		$error = wp_json_encode( wc_clean( $_POST['error'] ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$str   = "====Frontend Js Error Log start=== \n\n $error \n\n ====End===";
		Helper::log( $str, 'info' );

		if ( isset( $_POST['order_id'] ) && ! empty( $_POST['order_id'] ) ) {
			$order_id = wc_clean( $_POST['order_id'] );
			$order    = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order ) {
				$error_message = '';
				if ( isset( $_POST['error']['payment_intent']['id'] ) ) {
					$error_message .= __( 'Intent ID', 'funnelkit-stripe-woo-payment-gateway' ) . ":" . wc_clean( $_POST['error']['payment_intent']['id'] );
				}
				$localized_messages = Helper::get_localized_messages();
				$localized_message  = '';
				if ( isset( $_POST['error']['type'] ) && 'card_error' === wc_clean( $_POST['error']['type'] ) ) {
					if ( isset( $_POST['error']['code'] ) && $_POST['error']['code'] === 'card_declined' ) {
						if ( isset( $_POST['error']['decline_code'] ) ) {
							$localized_message = isset( $localized_messages[ $_POST['error']['decline_code'] ] ) ? $localized_messages[ wc_clean( $_POST['error']['decline_code'] ) ] : wc_clean( $_POST['error']['message'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
						}
					} else {
						$localized_message = isset( $localized_messages[ $_POST['error']['code'] ] ) ? $localized_messages[ wc_clean( $_POST['error']['code'] ) ] : wc_clean( $_POST['error']['message'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

					}

				} else {
					$localized_message = isset( $localized_messages[ $_POST['error']['type'] ] ) ? $localized_messages[ wc_clean( $_POST['error']['type'] ) ] : wc_clean( $_POST['error']['message'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

				}


				$error_message .= "\n\n" . $localized_message;


				if ( ! empty( $error_message ) ) {
					$order->update_status( 'failed', $error_message );
				}
			}
		}

		wp_send_json( [ 'status' => 'true' ] );
	}

	/**
	 * Set logged in cookie in global var to have proper checks for the nonce
	 *
	 * @param $cookie
	 *
	 * @return void
	 */
	public function set_cookie_on_request( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;    //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

	}


}

AJAX::get_instance();