<?php

namespace FKWCS\Gateway;

use FKWCS\Gateway\Stripe\Admin;
use FKWCS\Gateway\Stripe\Onboard;
use FKWCS\Gateway\Stripe\SmartButtons;
use FKWCS\Gateway\Stripe\Webhook;

class Stripe {
	private static $instance = null;

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_core' ) );

		/**
		 * Load text domain from our local folder
		 */
		load_plugin_textdomain( 'funnelkit-stripe-woo-payment-gateway', false, plugin_basename( dirname( FKWCS_FILE ) ) . '/languages/' );
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load core
	 *
	 * @return void
	 */
	public function load_core() {
		if ( ! class_exists( 'woocommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'wc_is_not_active' ] );

			return;
		}

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once plugin_dir_path( FKWCS_FILE ) . 'library/stripe-php/init.php';
		}

		$this->admin();
		include plugin_dir_path( FKWCS_FILE ) . '/includes/helper.php';
		include plugin_dir_path( FKWCS_FILE ) . '/includes/client.php';
		include plugin_dir_path( FKWCS_FILE ) . '/includes/abstract-payment-gateway.php';
		include plugin_dir_path( FKWCS_FILE ) . '/includes/ajax.php';
		include plugin_dir_path( FKWCS_FILE ) . '/includes/class-compatibilities.php';

		$this->hooks();
		$this->webhook();
		$this->include_gateways();
	}

	/**
	 * Registering gateways to WooCommerce
	 *
	 * @param array $methods List of registered gateways.
	 *
	 * @return array
	 */
	public function register_gateway( $methods ) {

		return array_merge( $methods, array_values( $this->get_gateways() ) );
	}

	public function get_gateways() {
		$methods                     = [];
		$methods['fkwcs_stripe']     = 'FKWCS\Gateway\Stripe\CreditCard';
		$methods['fkwcs_ideal']      = 'FKWCS\Gateway\Stripe\Ideal';
		$methods['fkwcs_bancontact'] = 'FKWCS\Gateway\Stripe\Bancontact';
		$methods['fkwcs_p24']        = 'FKWCS\Gateway\Stripe\P24';
		$methods['fkwcs_sepa']       = 'FKWCS\Gateway\Stripe\Sepa';

		return $methods;
	}

	/**
	 * Loading admin classes
	 *
	 * @return void
	 */
	public function admin() {
		include plugin_dir_path( FKWCS_FILE ) . '/admin/admin.php';
		include plugin_dir_path( FKWCS_FILE ) . '/admin/onboard.php';
		Admin::get_instance();
		Onboard::get_instance();
	}

	public function include_gateways() {
		/**
		 * Include all the gateway classes
		 */
		include plugin_dir_path( FKWCS_FILE ) . '/includes/traits/wc-subscriptions-helper-trait.php';
		include plugin_dir_path( FKWCS_FILE ) . '/includes/traits/wc-subscriptions-trait.php';
		include plugin_dir_path( FKWCS_FILE ) . '/includes/wc-stripe-conversions.php';
		include plugin_dir_path( FKWCS_FILE ) . '/gateways/creditcard.php';
		include plugin_dir_path( FKWCS_FILE ) . '/gateways/smart-buttons.php';
		include plugin_dir_path( FKWCS_FILE ) . '/gateways/ideal.php';
		include plugin_dir_path( FKWCS_FILE ) . '/gateways/bancontact.php';
		include plugin_dir_path( FKWCS_FILE ) . '/gateways/p24.php';
		include plugin_dir_path( FKWCS_FILE ) . '/gateways/sepa.php';

		/**
		 * Load Smart buttons class separately as this is not the registered gateway itself, it simply extends the credit card gateway
		 */
		SmartButtons::get_instance();
	}

	/**
	 * Loads classes on plugins_loaded hook.
	 *
	 * @return void
	 */
	public function wc_is_not_active() {
		?>
		<div class="error">
			<p>
				<?php
				echo __( '<strong> Attention: </strong>WooCommerce is not installed or activated. Funnelkit Stripe Plugin is a WooCommerce Payment Gateway and would only work if WooCommerce is activated. Please install the WooCommerce Plugin first.', 'funnelkit-stripe-woo-payment-gateway' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Attach payment methods of FK Upsells
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function add_supported_gateways( $gateways ) {
		$gateways['fkwcs_stripe']      = 'WFOCU_Plugin_Integration_Fkwcs_Stripe';
		$gateways['fkwcs_stripe_sepa'] = 'WFOCU_Plugin_Integration_Fkwcs_Sepa';

		return $gateways;
	}

	/**
	 * Attach payment methods of FK Upsells for subscription
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function enable_subscription_upsell_support( $gateways ) {
		$gateways[] = 'fkwcs_stripe';
		$gateways[] = 'fkwcs_stripe_sepa';

		return $gateways;
	}

	/**
	 * enable default support during setup
	 *
	 * @param $resp
	 *
	 * @return void WHEN no upsell functionality exists
	 */
	public function enable_upsell_default_gateway_on_setup( $resp ) {
		if ( ! class_exists( 'WFOCU_Core' ) ) {
			return;
		}

		$all_options = WFOCU_Core()->data->get_option();

		if ( isset( $resp['fkwcs_stripe'] ) && true === $resp['fkwcs_stripe'] ) {
			array_push( $all_options['gateways'], 'fkwcs_stripe' );
		}
		if ( isset( $resp['fkwcs_stripe_sepa'] ) && true === $resp['fkwcs_stripe_sepa'] ) {
			array_push( $all_options['gateways'], 'fkwcs_stripe_sepa' );
		}
		WFOCU_Core()->data->update_options( $all_options );
	}

	/**
	 * Load hooks
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateway' ], 999 );

		/**
		 * Upsell compatible hooks
		 */
		add_filter( 'wfocu_wc_get_supported_gateways', [ $this, 'add_supported_gateways' ] );
		add_filter( 'wfocu_subscriptions_get_supported_gateways', array( $this, 'enable_subscription_upsell_support' ) );
		add_action( 'fkwcs_wizard_gateways_save', array( $this, 'enable_upsell_default_gateway_on_setup' ) );

		add_filter( 'option_woocommerce_gateway_order', [ $this, 'move_gateway_to_first_in_the_list' ], 9999 );
		add_filter( 'default_option_woocommerce_gateway_order', [ $this, 'move_gateway_to_first_in_the_list' ], 9999 );

		add_action( 'fkwcs_wizard_gateways_save', [ $this, 'disable_other_gateways' ] );
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );

	}

	/**
	 * Include Webhook class and initialize instance
	 *
	 * @return void
	 */
	public function webhook() {
		include plugin_dir_path( FKWCS_FILE ) . '/includes/webhook.php';
		Webhook::get_instance();
	}


	/**
	 * By default, new payment gateways are put at the bottom of the list on the admin "Payments" settings screen.
	 * Here we are simply moving our gateway on top.
	 *
	 * @param array $ordering Existing ordering of the payment gateways.
	 *
	 * @return array Modified ordering.
	 */
	public function move_gateway_to_first_in_the_list( $ordering ) {
		$ordering = (array) $ordering;


		$key = 'fkwcs_stripe';
		if ( ! isset( $ordering[ $key ] ) || ! is_numeric( $ordering[ $key ] ) ) {
			$is_empty         = empty( $ordering ) || ( count( $ordering ) === 1 && $ordering[0] === false );
			$ordering[ $key ] = $is_empty ? 0 : ( min( $ordering ) - 1 );
		}


		return $ordering;
	}


	/**
	 * Take an attempt to disable gateways which could cause multiple CC fields in the checkout,
	 *
	 * @param array $response
	 *
	 * @return void
	 */
	public function disable_other_gateways( $response ) {

		/**
		 * skip if our cc not enabled
		 */
		if ( ! isset( $response['fkwcs_stripe'] ) || false === $response['fkwcs_stripe'] ) {
			return;
		}

		$gateways = WC()->payment_gateways->payment_gateways();
		foreach ( array( 'stripe', 'stripe_cc', 'stripe_applepay', 'stripe_googlepay' ) as $id ) {
			if ( isset( $gateways[ $id ] ) && 'yes' === $gateways[ $id ]->enabled ) {
				$gateways[ $id ]->update_option( 'enabled', 'no' );
			}
		}
	}


	/**
	 * This method declared ours compat with the HPOS mechanism
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FKWCS_FILE, true );
		}
	}


}

Stripe::get_instance();
