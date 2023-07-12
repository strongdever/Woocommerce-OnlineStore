<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use FKWCS\Gateway\Stripe\SmartButtons;

if ( ! class_exists( 'FKWCS_Compat_FK_Checkout' ) ) {

	class FKWCS_Compat_FK_Checkout {

		public function __construct() {
			add_filter( 'wfacp_smart_buttons', [ $this, 'add_buttons' ] );
			add_action( 'wfacp_smart_button_container_fkwcs_gpay_apay', [ $this, 'add_fkwcs_gpay_apay_buttons' ] );
			add_action( 'wfacp_internal_css', [ $this, 'add_internal_css' ] );
		}

		public function add_buttons( $buttons ) {
			$settings = get_option( 'woocommerce_fkwcs_stripe_settings', array() );
			// Checks if Payment Request is enabled.
			if ( ! isset( $settings['express_checkout_enabled'] ) || 'yes' !== $settings['express_checkout_enabled'] ) {
				return $buttons;
			}





			$instance = SmartButtons::get_instance();
			remove_action( 'woocommerce_checkout_before_customer_details', [ $instance, 'payment_request_button' ], 5 );

			$buttons['fkwcs_gpay_apay'] = [
				'iframe' => true,
				'name'   => __( 'Stripe Payment Request', 'funnelkit-stripe-woo-payment-gateway' ),
			];

			return $buttons;

		}


		public function add_fkwcs_gpay_apay_buttons() {
			add_filter( 'flwcs_express_buttons_is_only_buttons', '__return_true' );
			$instance = SmartButtons::get_instance();
			$instance->payment_request_button();
		}


		public function add_internal_css() {
			if ( ! defined( 'WC_STRIPE_VERSION' ) ) {
				return;
			}


			$instance = wfacp_template();
			if ( ! $instance instanceof \WFACP_Template_Common ) {
				return;
			}
			$bodyClass = "body";
			if ( 'pre_built' !== $instance->get_template_type() ) {
				$bodyClass = "body #fkwcs-e-form";
			}
			if ( version_compare( WC_STRIPE_VERSION, '5.6.0', '<' ) ) {
				return;
			}


			echo "<style>";

			echo esc_html( $bodyClass ) . " #payment ul.payment_methods li .card-brand-icons img{position: absolute;}";

			echo "</style>";

		}
	}

	FKWCS_Plugin_Compatibilities::register( new FKWCS_Compat_FK_Checkout(), 'fkwcs_checkout' );


}


