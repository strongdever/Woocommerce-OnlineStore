<?php
/**
 * Stripe Api Wrapper
 *
 * @package funnelkit-stripe-woo-payment-gateway
 */

namespace FKWCS\Gateway\Stripe;

use Stripe\StripeClient;

/**
 * Stripe Api Class
 */
class Client {

	/**
	 * Instance of Stripe
	 *
	 * @var \Stripe\StripeClient
	 */
	private $stripe;

	/**
	 * Constructor
	 *
	 */
	public function __construct( $secret_key, $mode = 'test', $version = '1.0' ) {

		$this->stripe = new StripeClient( array(
			'api_key'        => $secret_key,
			'stripe_version' => '2022-08-01',
		) );


		\Stripe\Stripe::setAppInfo( 'FunnelKit Stripe Gateway', $version, 'https://wordpress.org/plugins/funnelkit-stripe-woo-payment-gateway', '995584862' );

	}

	/**
	 * Executes all stripe calls
	 *
	 * @param string $api Api.
	 * @param string $method name of method.
	 * @param array $args arguments.
	 *
	 * @return array
	 */
	private function execute( $api, $method, $args ) {

		if ( is_null( $this->stripe ) ) {
			$error_message = __( 'Stripe not initialized', 'funnelkit-stripe-woo-payment-gateway' );

			return [
				'success' => false,
				'message' => $error_message,
			];
		}
		Helper::log( 'REQUEST ' . strtolower( $api ) . '::' . $method . '--' . wp_json_encode($args) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		$error_message = false;
		$response      = false;
		$error_type    = '';
		try {
			$response = $this->stripe->{$api}->{$method}( ...$args );
			Helper::log( 'RESPONSE ' . strtolower( $api ) . '::' . $method . '--' .  wp_json_encode($response) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		} catch ( \Stripe\Exception\CardException $e ) {
			$error_message = $e->getError()->message;
			$error_type    = $e->getError()->param;
		} catch ( \Stripe\Exception\RateLimitException $e ) {
			$error_message = $e->getError()->message;
			$error_type    = $e->getError()->param;
		} catch ( \Stripe\Exception\InvalidRequestException $e ) {
			$error_message = $e->getError()->message;
			$error_type    = $e->getError()->param;
		} catch ( \Stripe\Exception\AuthenticationException $e ) {
			$error_message = $e->getError()->message;
			$error_type    = $e->getError()->param;
		} catch ( \Stripe\Exception\ApiConnectionException $e ) {
			$error_message = is_null( $e->getError() ) ? $e->getMessage() : $e->getError()->message;
			$error_type    = is_null( $e->getError() ) ? '' : $e->getError()->param;
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			$error_message = $e->getError()->message;
			$error_type    = $e->getError()->param;
		} catch ( \Stripe\Exception\InvalidArgumentException $e ) {
			$error_message = $e->getMessage();
			$error_type    = $e->getCode();
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			$error_type    = $e->getCode();
		}
		if ( ! $error_message ) {
			return [
				'success' => true,
				'data'    => $response,
				'message' => '',
			];
		} else {
			Helper::log( 'Error during API call. ' . $error_message );

			return [
				'success' => false,
				'message' => $error_message,
				'type'    => $error_type,
				'error'   => ( isset( $e ) && $e instanceof \Stripe\Exception\ApiErrorException ) ? $e->getError() : new \stdClass(),
			];
		}
	}

	/**
	 * Stripe wrapper for paymentIntents Api
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function payment_intents( $method, $args ) {

		return $this->execute( 'paymentIntents', $method, $args );
	}


	/**
	 * Stripe wrapper for paymentIntents Api
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function sources( $method, $args ) {

		return $this->execute( 'sources', $method, $args );
	}

	/**
	 * Stripe wrapper for paymentMethods Api
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function payment_methods( $method, $args ) {
		return $this->execute( 'paymentMethods', $method, $args );
	}

	/**
	 * Executes stripe customers query
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function customers( $method, $args ) {

		return $this->execute( 'customers', $method, $args );

	}


	/**
	 * Executes Stripe refunds query
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function refunds( $method, $args ) {
		return $this->execute( 'refunds', $method, $args );
	}

	/**
	 * Executes Stripe setupIntents query
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function setup_intents( $method, $args ) {
		return $this->execute( 'setupIntents', $method, $args );
	}

	/**
	 * Executes Stripe accounts query
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function accounts( $method, $args ) {
		return $this->execute( 'accounts', $method, $args );
	}

	/**
	 * Executes Stripe apple pay domains query
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function apple_pay_domains( $method, $args ) {
		return $this->execute( 'applePayDomains', $method, $args );
	}

	/**
	 * Executes Stripe balance transactions query
	 *
	 * @param string $method method to be used.
	 * @param array $args parameter.
	 *
	 * @return array
	 */
	public function balance_transactions( $method, $args ) {
		return $this->execute( 'balanceTransactions', $method, $args );
	}


	public function products( $method, $args ) {
		return $this->execute( 'products', $method, $args );
	}

	public function plans( $method, $args ) {
		return $this->execute( 'plans', $method, $args );
	}

	public function subscriptions( $method, $args ) {
		return $this->execute( 'subscriptions', $method, $args );
	}

	public function invoices( $method, $args ) {
		return $this->execute( 'invoices', $method, $args );
	}

	public function charges( $method, $args ) {
		return $this->execute( 'charges', $method, $args );
	}

	/**
	 * Basic details of logged in user
	 *
	 * @return array current user data.
	 *
	 */
	public function get_clients_details() {
		return [
			'ip'      => \WC_Geolocation::get_ip_address(),
			'agent'   => wc_get_user_agent(),
			'referer' => wc_get_raw_referer(),
		];

	}


}
