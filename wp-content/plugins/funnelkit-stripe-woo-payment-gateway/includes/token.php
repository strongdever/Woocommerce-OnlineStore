<?php

namespace FKWCS\Inc;

use WC_Payment_Token;

/**
 * Stripe Payment Token.
 *
 * Representation of a payment token for SEPA.
 *
 * @class Token
 *
 */
class Token extends WC_Payment_Token {

	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected $type = 'fkwcs_stripe_sepa';

	/**
	 * Stores SEPA payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'last4'               => '',
		'payment_method_type' => 'sepa_debit',
	];


	/**
	 * Hook prefix
	 *
	 *
	 * @return string
	 */
	protected function get_hook_prefix() {
		return 'fkwcs_payment_token_sepa_get_';
	}


	/**
	 * Get type to display to user.
	 *
	 *
	 * @param string $deprecated Deprecated.
	 *
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
		$display = sprintf( /* translators: last 4 digits of IBAN account */
			__( 'SEPA IBAN ending in %s', 'funnelkit-stripe-woo-payment-gateway' ), $this->get_last4() );

		return $display;
	}

	/**
	 * Validate SEPA payment tokens.
	 *
	 * These fields are required by all SEPA payment tokens:
	 * last4  - string Last 4 digits of the iBAN.
	 *
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_last4( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the last four digits.
	 *
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 *
	 * @param string $last4 Last 4 digits card number.
	 *
	 * @return void
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Set Stripe payment method type.
	 *
	 *
	 * @param string $type Payment method type.
	 *
	 * @return void
	 */
	public function set_payment_method_type( $type ) {
		$this->set_prop( 'payment_method_type', $type );
	}

	/**
	 * Returns Stripe payment method type.
	 *
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string $payment_method_type
	 */
	public function get_payment_method_type( $context = 'view' ) {
		return $this->get_prop( 'payment_method_type', $context );
	}
}