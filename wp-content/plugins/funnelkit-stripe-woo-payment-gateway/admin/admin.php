<?php

namespace FKWCS\Gateway\Stripe;

use Stripe\OAuth;

class Admin {
	private static $instance = null;

	const APPLEPAY_FILE = 'apple-developer-merchantid-domain-association';
	const APPLEPAY_DIR = '.well-known';

	/**
	 * Navigation links for the payment method pages.
	 *
	 * @var $navigation array
	 */
	public $navigation = [];

	/**
	 * Option keys
	 *
	 * @var string[]
	 */
	private $options_keys = [
		'fkwcs_test_pub_key'         => '',
		'fkwcs_test_secret_key'      => '',
		'fkwcs_pub_key'              => '',
		'fkwcs_secret_key'           => '',
		'fkwcs_con_status'           => '',
		'fkwcs_mode'                 => 'test',
		'fkwcs_live_webhook_secret'  => '',
		'fkwcs_test_webhook_secret'  => '',
		'fkwcs_debug_log'            => '',
		'fkwcs_account_id'           => '',
		'fkwcs_auto_connect'         => '',
		'fkwcs_setup_status'         => '',
		'fkwcs_live_created_webhook' => '',
		'fkwcs_test_created_webhook' => '',
	];
	private $settings = [];
	private $domain;

	/**
	 * Initiator
	 *
	 * @return object \FKWCS\Gateway\Stripe\Admin
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->get_option_settings();
		$this->define_navigation();
		$this->init();
	}

	/**
	 * Initialize
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_filter( 'woocommerce_get_sections_checkout', [ $this, 'sub_links' ], 300 );
		add_filter( 'woocommerce_get_sections_fkwcs_api_settings', [ $this, 'sub_links' ], 300 );
		add_action( 'woocommerce_sections_fkwcs_api_settings', [ $this, 'add_breadcrumb' ] );
		add_filter( 'woocommerce_get_settings_checkout', [ $this, 'stripe_express_checkout_settings' ], 10, 2 );

		add_action( 'admin_head', [ $this, 'add_custom_css' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_js' ] );
		add_action( 'admin_init', [ $this, 'handle_redirect' ] );
		add_action( 'admin_init', [ $this, 'init_notices' ] );

		/**
		 * Register AJAX callbacks
		 */
		add_action( 'wp_ajax_fkwcs_test_stripe_connection', [ $this, 'connection_test' ] );
		add_action( 'wp_ajax_fkwcs_disconnect_account', [ $this, 'disconnect_account' ] );
		add_action( 'wp_ajax_fkwcs_create_webhook', [ $this, 'connect_webhook' ] );
		add_action( 'wp_ajax_fkwcs_delete_webhook', [ $this, 'disconnect_webhook' ] );
		add_action( 'wp_ajax_fkwcs_apple_pay_domain_verification', [ $this, 'apple_pay_domain_verification' ] );
		add_action( 'wp_ajax_fkwcs_dismiss_notice', [ $this, 'dismiss_notice' ] );

		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_fee' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_payout' ], 20 );
		add_filter( "plugin_action_links_" . FKWCS_BASE, [ $this, 'add_plugin_settings_link' ] );
		add_action( 'woocommerce_admin_field_fkwcs_express_checkout_preview', [ $this, 'express_checkout_preview' ] );

		add_filter( 'fkwcs_settings', [ $this, 'filter_settings_fields' ], 1 );

		/**
		 * Admin settings custom fields
		 */
		add_action( 'woocommerce_admin_field_fkwcs_stripe_connect', [ $this, 'stripe_connect' ] );
		add_action( 'woocommerce_admin_field_fkwcs_account_id', [ $this, 'account_id' ] );
		add_action( 'woocommerce_admin_field_fkwcs_webhook_url', [ $this, 'webhook_url' ] );
		add_action( 'woocommerce_admin_field_wc_fkwcs_connection_test', [ $this, 'wc_fkwcs_connection_test' ] );
		add_action( 'woocommerce_admin_field_wc_fkwcs_apple_pay_domain', [ $this, 'wc_fkwcs_apple_pay_domain' ] );

		add_filter( 'admin_footer_text', [ $this, 'add_manual_connect_link' ] );

		$this->admin_page_settings();

		$this->domain_is_verfied = get_option( 'fkwcs_apple_pay_domain_is_verified' );
		$this->verified_domain   = get_option( 'fkwcs_apple_pay_verified_domain' );
		$this->failure_message   = '';
		$this->domain            = isset( $_SERVER['HTTP_HOST'] ) ? wc_clean( $_SERVER['HTTP_HOST'] ) : str_replace( array(
			'https://',
			'http://'
		), '', get_site_url() ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Admin hooks
	 *
	 * @return void
	 */
	private function admin_page_settings() {
		add_action( 'woocommerce_update_options_checkout', [ $this, 'express_checkout_option_updates' ] );
		add_action( 'woocommerce_settings_tabs_fkwcs_api_settings', [ $this, 'api_settings_tab' ] );
		add_action( 'woocommerce_update_options_fkwcs_api_settings', [ $this, 'update_api_settings' ] );
		add_action( 'woocommerce_admin_field_fkwcs_stripe_connect', [ $this, 'stripe_connect' ] );
		add_action( 'woocommerce_admin_field_fkwcs_webhook_url', [ $this, 'webhook_url' ] );
		add_action( 'woocommerce_admin_field_fkwcs_create_webhook_button', [ $this, 'webhook_connect' ] );
		add_action( 'woocommerce_admin_field_fkwcs_delete_webhook_button', [ $this, 'webhook_delete' ] );
	}

	/**
	 * Plugin settings
	 *
	 * @return void
	 */
	public function api_settings_tab() {
		woocommerce_admin_fields( $this->get_api_settings() );
	}

	/**
	 * Update API settings
	 *
	 * @return void
	 */
	public function update_api_settings() {
		woocommerce_update_options( $this->get_api_settings() );
		$this->get_option_settings();
	}

	/**
	 * WC Payment tab settings
	 *
	 * @return void
	 */
	private function define_navigation() {
		$this->navigation = apply_filters( 'fkwcs_settings_navigation', [
			'fkwcs_api_settings'      => __( 'Stripe API Settings', 'funnelkit-stripe-woo-payment-gateway' ),
			'fkwcs_stripe'            => __( 'Credit Cards', 'funnelkit-stripe-woo-payment-gateway' ),
			'fkwcs_express_checkout'  => __( 'Express Checkout', 'funnelkit-stripe-woo-payment-gateway' ),
			'fkwcs_stripe_ideal'      => __( 'iDeal', 'funnelkit-stripe-woo-payment-gateway' ),
			'fkwcs_stripe_bancontact' => __( 'Bancontact', 'funnelkit-stripe-woo-payment-gateway' ),
			'fkwcs_stripe_p24'        => __( 'P24', 'funnelkit-stripe-woo-payment-gateway' ),
			'fkwcs_stripe_sepa'       => __( 'SEPA', 'funnelkit-stripe-woo-payment-gateway' ),
		] );
	}

	/**
	 * Get option settings
	 *
	 * @return void
	 */
	private function get_option_settings() {
		foreach ( $this->options_keys as $key => $value ) {
			$this->options_keys[ $key ] = get_option( $key, $value );
		}
	}

	/**
	 * Get Stripe API settings
	 *
	 * @return mixed|null
	 */
	public function get_api_settings() {
		$settings = [
			'section_title'     => [
				'name' => __( 'Stripe API Settings', 'funnelkit-stripe-woo-payment-gateway' ),
				'type' => 'title',
				'id'   => 'fkwcs_title',
			],
			'connection_status' => [
				'name'  => __( 'Stripe Connect', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'fkwcs_stripe_connect',
				'value' => '--',
				'class' => 'wc_fkwcs_connect_btn',
				'id'    => 'fkwcs_stripe_connect',
			],
			'account_id'        => [
				'name'     => __( 'Connection Status', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'fkwcs_account_id',
				'value'    => '--',
				'class'    => 'account_id',
				'desc_tip' => __( 'This is your Stripe Connect ID and serves as a unique identifier.', 'funnelkit-stripe-woo-payment-gateway' ),
				'desc'     => __( 'This is your Stripe Connect ID and serves as a unique identifier.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'       => 'fkwcs_account_id',
			],
			'account_keys'      => [
				'name'  => __( 'Stripe Account Keys', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'fkwcs_account_keys',
				'class' => 'wc_stripe_acc_keys',
				'desc'  => __( 'This will disable any connection to Stripe.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'fkwcs_account_keys',
			],
			'connect_button'    => [
				'name'  => __( 'Connect Stripe Account', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'fkwcs_connect_btn',
				'class' => 'wc_fkwcs_connect_btn',
				'desc'  => __( 'We make it easy to connect Stripe to your site. Click the Connect button to go through our connect flow.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'fkwcs_connect_btn',
			],
			'test_mode'         => [
				'name'     => __( 'Mode', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'select',
				'options'  => [
					'test' => 'Test',
					'live' => 'Live',
				],
				'desc'     => __( 'No live transactions are processed in test mode. To fully use test mode, you must have a sandbox (test) account for the payment gateway you are testing.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'       => 'fkwcs_mode',
				'desc_tip' => true,
			],
			'live_pub_key'      => [
				'name'     => __( 'Live Publishable Key', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'desc_tip' => __( 'Your publishable key is used to initialize Stripe assets.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'       => 'fkwcs_pub_key',
			],
			'live_secret_key'   => [
				'name'     => __( 'Live Secret Key', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'desc_tip' => __( 'Your secret key is used to authenticate Stripe requests.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'       => 'fkwcs_secret_key',
			],
			'test_pub_key'      => [
				'name'     => __( 'Test Publishable Key', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'desc_tip' => __( 'Your test publishable key is used to initialize Stripe assets.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'       => 'fkwcs_test_pub_key',
			],
			'test_secret_key'   => [
				'name'     => __( 'Test Secret Key', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'desc_tip' => __( 'Your test secret key is used to authenticate Stripe requests for testing purposes.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'       => 'fkwcs_test_secret_key',
			],

			'do_connection'         => [
				'name'  => __( 'Test Connection', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'wc_fkwcs_connection_test',
				'class' => 'wc_fkwcs_connection_test',
				'desc'  => __( 'Click this button to test a connection. If successful, your site is connected to Stripe.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'wc_fkwcs_connection_test',
			],
			'create_webhook_button' => [
				'name'  => __( 'Create Webhook', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'fkwcs_create_webhook_button',
				'class' => 'wc_fkwcs_create_webhook_button',
				'desc'  => __( 'We make it easy to connect Stripe to your site. Click the Connect button to go through our connect flow.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'fkwcs_create_webhook_button',
			],
			'delete_webhook_button' => [
				'name'  => __( 'Delete Webhook', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'fkwcs_delete_webhook_button',
				'class' => 'wc_fkwcs_delete_webhook_button',
				'desc'  => '',
				'id'    => 'fkwcs_delete_webhook_button',
			],
			'webhook_url'           => [
				'name'  => __( 'Webhook URL', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'fkwcs_webhook_url',
				'class' => 'wc_fkwcs_webhook_url',
				/* translators: %1$1s - %2$2s HTML markup */
				'desc'  => sprintf( __( 'Important: the webhook URL is called by Stripe when events occur in your account, like a source becomes chargeable. %1$1sWebhook Guide%2$2s', 'funnelkit-stripe-woo-payment-gateway' ), '<a href="#" target="_blank">', '</a>' ),
				'id'    => 'fkwcs_webhook_url',
			],
			'live_webhook_secret'   => [
				'name' => __( 'Live Webhook Secret', 'funnelkit-stripe-woo-payment-gateway' ),
				'type' => 'password',
				/* translators: %1$1s Webhook Status */
				'desc' => sprintf( __( 'The webhook secret is used to authenticate webhooks sent from Stripe. It ensures nobody else can send you events pretending to be Stripe. %1$1s', 'funnelkit-stripe-woo-payment-gateway' ), '</br>' . Webhook::get_webhook_interaction_message( 'live' ) ),
				'id'   => 'fkwcs_live_webhook_secret',
			],
			'test_webhook_secret'   => [
				'name' => __( 'Test Webhook Secret', 'funnelkit-stripe-woo-payment-gateway' ),
				'type' => 'password',
				/* translators: %1$1s Webhook Status */
				'desc' => sprintf( __( 'The webhook secret is used to authenticate webhooks sent from Stripe. It ensures nobody else can send you events pretending to be Stripe. %1$1s', 'funnelkit-stripe-woo-payment-gateway' ), '</br>' . Webhook::get_webhook_interaction_message( 'test' ) ),
				'id'   => 'fkwcs_test_webhook_secret',
			],
			'debug_log'             => [
				'name'     => __( 'Debug Log', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Log debug messages', 'funnelkit-stripe-woo-payment-gateway' ),
				'desc_tip' => sprintf( __( 'Log Stripe API calls, inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce' ), '<code>' . \WC_Log_Handler_File::get_log_file_path( 'fkwcs-stripe' ) . '</code>' ),
				'id'       => 'fkwcs_debug_log',
			],
			'section_end'           => [
				'type' => 'sectionend',
				'id'   => 'fkwcs_api_settings_section_end',
			],
		];
		$settings = apply_filters( 'fkwcs_settings', $settings );

		return $settings;
	}

	/**
	 * Add Stripe setting
	 *
	 * @param $settings_tabs
	 *
	 * @return mixed
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['fkwcs_api_settings'] = __( 'Stripe', 'funnelkit-stripe-woo-payment-gateway' );

		return $settings_tabs;
	}

	/**
	 * Add sub section links
	 *
	 * @param $settings_tab
	 *
	 * @return mixed|null
	 */
	public function sub_links( $settings_tab ) {
		if ( isset( $_GET['section'] ) && 0 === strpos( wc_clean( $_GET['section'] ), 'fkwcs_' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$settings_tab = [];
			foreach ( $this->navigation as $key => $sub ) {
				$settings_tab[ $key ] = $sub;
			}
		}

		return apply_filters( 'fkwcs_setting_tabs', $settings_tab );
	}

	/**
	 * Display connect view
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function stripe_connect( $value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.Variables.VariableAnalysis.UnusedParameter
		include __DIR__ . '/parts/connect.php';
	}

	/**
	 * Display webhook section view
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function webhook_connect( $value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.Variables.VariableAnalysis.UnusedParameter
		if ( empty( get_option( 'fkwcs_test_created_webhook' ) ) && empty( get_option( 'fkwcs_live_created_webhook' ) ) ) {
			include __DIR__ . '/parts/webhook.php';
		}
	}

	/**
	 * Display webhook delete view
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function webhook_delete( $value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.Variables.VariableAnalysis.UnusedParameter
		if ( ! empty( get_option( 'fkwcs_test_created_webhook' ) ) || ! empty( get_option( 'fkwcs_live_created_webhook' ) ) ) {
			include __DIR__ . '/parts/webhook_delete.php';
		}
	}

	/**
	 * Get connect URL
	 *
	 * @return string
	 */
	public function get_connect_url() {
		return OAuth::authorizeUrl( apply_filters( 'fkwcs_stripe_connect_url_data', [
			'response_type'  => 'code',
			'client_id'      => 'ca_ME1hglU0nsfgQBtX4spILQpPqNH7vAtz',
			'stripe_landing' => 'login',
			'always_prompt'  => 'true',
			'scope'          => 'read_write',
			'state'          => base64_encode( //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				wp_json_encode( [
					'redirect' => admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' ),
				] ) ),
		] ) );
	}

	/**
	 * Add sections UI
	 *
	 * @return void
	 */
	public function add_breadcrumb() {
		if ( empty( $this->navigation ) ) {
			return;
		}
		?>
        <ul class="subsubsub">
			<?php
			foreach ( $this->navigation as $key => $value ) {
				$current_class = '';
				$separator     = '';
				if ( isset( $_GET['tab'] ) && 'fkwcs_api_settings' === $_GET['tab'] && 'fkwcs_api_settings' === $key ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$current_class = 'current';
					echo wp_kses_post( '<li> <a href="' . get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=fkwcs_api_settings" class="' . $current_class . '">' . $value . '</a> | </li>' );
				} else {
					if ( end( $this->navigation ) !== $value ) {
						$separator = ' | ';
					}
					echo wp_kses_post( '<li> <a href="' . get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=' . $key . '" class="' . $current_class . '">' . $value . '</a> ' . $separator . ' </li>' );
				}
			}
			?>
        </ul>
        <br class="clear"/>
		<?php
	}

	/**
	 * Stripe express checkout section settings
	 *
	 * @param $settings
	 * @param $current_section
	 *
	 * @return array|mixed|void
	 */
	public function stripe_express_checkout_settings( $settings, $current_section ) {
		if ( 'fkwcs_api_settings' === $current_section ) {
			wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' ) );
			exit;
		}

		if ( 'fkwcs_express_checkout' !== $current_section ) {
			return $settings;
		}

		$values = Helper::get_gateway_settings();

		// Default values need to be set in Helper class.
		$settings = [
			'section_title'   => [
				'name' => __( 'Express Checkout', 'funnelkit-stripe-woo-payment-gateway' ),
				'type' => 'title',
				/* translators: HTML for the  express checkout section heading */
				'desc' => sprintf( __( 'Accept payment using Apple Pay, Google Pay and Browser Payment using Express Buttons.
The visibility of these payment buttons is browser dependent. Click on "Test Visibility" button to see if your browser supports these button.
Learn more %1$1sabout the requirements%2$2s to show Apple Pay, Google Pay and Browser Payment method.', 'funnelkit-stripe-woo-payment-gateway' ), '<a href="https://stripe.com/docs/stripe-js/elements/payment-request-button#html-js-testing" target="_blank">', '</a>' ),
				'id'   => 'fkwcs_express_checkout',
			],
			'enable'          => [
				'name'  => __( 'Enable Express Checkout', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'fkwcs_express_checkout_enabled',
				'type'  => 'checkbox',
				'value' => $values['express_checkout_enabled'],
			],
			'button_location' => [
				'title'    => __( 'Show button on', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'multiselect',
				'class'    => 'fkwcs_express_checkout_location multiselect',
				'id'       => 'fkwcs_express_checkout_location',
				'desc_tip' => __( 'Choose page to display Express Checkout buttons.', 'funnelkit-stripe-woo-payment-gateway' ),
				'options'  => [
					'product'  => __( 'Product', 'funnelkit-stripe-woo-payment-gateway' ),
					'cart'     => __( 'Cart', 'funnelkit-stripe-woo-payment-gateway' ),
					'checkout' => __( 'Checkout', 'funnelkit-stripe-woo-payment-gateway' ),
				],
				'value'    => $values['express_checkout_location'],
			],
			'button_type'     => [
				'title'    => __( 'Button text', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'id'       => 'fkwcs_express_checkout_button_text',
				'desc'     => __( 'Add label text for the Express Checkout button.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_button_text'],
				'desc_tip' => true,
			],
			'button_theme'    => [
				'title'    => __( 'Button theme', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'select',
				'id'       => 'fkwcs_express_checkout_button_theme',
				'desc'     => __( 'Select theme for Express Checkout button.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_button_theme'],
				'options'  => [
					'dark'          => __( 'Dark', 'funnelkit-stripe-woo-payment-gateway' ),
					'light'         => __( 'Light', 'funnelkit-stripe-woo-payment-gateway' ),
					'light-outline' => __( 'Light Outline', 'funnelkit-stripe-woo-payment-gateway' ),
				],
				'desc_tip' => true,
			],
			'preview'         => [
				'title' => __( 'Button Preview', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'fkwcs_express_checkout_preview',
				'id'    => 'fkwcs_express_checkout_preview',
			],

			'section_end' => [
				'type' => 'sectionend',
				'id'   => 'fkwcs_express_checkout',
			],

			'verify_domain_apple' => [
				'name'  => __( 'Re-verify Domain', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'wc_fkwcs_apple_pay_domain',
				'class' => 'wc_fkwcs_apple_pay_domain',
				'desc'  => __( 'Click the button above to re-verify domain for Apple Pay.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'wc_fkwcs_apple_pay_domain',
			],

			'product_page_section_title' => [
				'name' => __( 'Product page options', 'funnelkit-stripe-woo-payment-gateway' ),
				'type' => 'title',
				'desc' => __( 'Advanced customization options for Product page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'   => 'fkwcs_express_checkout_product_page',
			],
			'product_button_position'    => [
				'title'    => __( 'Button position', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'select',
				'id'       => 'fkwcs_express_checkout_product_page_position',
				'class'    => 'fkwcs_product_options',
				'desc'     => __( 'Select the position of Express Checkout button. This option will work only for Product page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_product_page_position'],
				'options'  => [
					'above'  => __( 'Above Add to Cart', 'funnelkit-stripe-woo-payment-gateway' ),
					'below'  => __( 'Below Add to Cart', 'funnelkit-stripe-woo-payment-gateway' ),
					'inline' => __( 'Inline Button', 'funnelkit-stripe-woo-payment-gateway' ),
				],
				'desc_tip' => true,
			],
			'separator_text'             => [
				'title'    => __( 'Separator text', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'id'       => 'fkwcs_express_checkout_separator_product',
				'desc'     => __( 'Add separator text for the Express Checkout button. This will help to distinguish between Express Checkout and other buttons.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_separator_product'],
				'class'    => 'fkwcs_product_options',
				'desc_tip' => true,
			],


			'product_page_section_end'    => [
				'type' => 'sectionend',
				'id'   => 'fkwcs_express_checkout',
			],
			'cart_page_section_title'     => [
				'name'  => __( 'Cart page options', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'title',
				'desc'  => __( 'Advanced customization options for Cart page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'fkwcs_express_checkout_cart_page',
				'class' => 'fkwcs_cart_options',
			],
			'cart_separator_text'         => [
				'title'    => __( 'Separator text', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'class'    => 'fkwcs_cart_options',
				'id'       => 'fkwcs_express_checkout_separator_cart',
				'desc'     => __( 'Add separator text for Cart page. If empty will show default separator text.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_separator_cart'],
				'desc_tip' => true,
			],
			'cart_page_section_end'       => [
				'type' => 'sectionend',
				'id'   => 'fkwcs_express_checkout',
			],
			'checkout_page_section_title' => [
				'name'  => __( 'Checkout page options', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'  => 'title',
				'desc'  => __( 'Advanced customization options for Checkout page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'id'    => 'fkwcs_express_checkout_checkout_page',
				'class' => 'fkwcs_checkout_options',
			],
			'checkout_button_position'    => [
				'title'    => __( 'Button position', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'select',
				'class'    => 'fkwcs_checkout_options',
				'id'       => 'fkwcs_express_checkout_checkout_page_position',
				'desc'     => __( 'Select the position of Express Checkout button. This option will work only for Checkout page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_checkout_page_position'],
				'options'  => [
					'above-checkout' => __( 'Above checkout form', 'funnelkit-stripe-woo-payment-gateway' ),
					'above-billing'  => __( 'Above billing details', 'funnelkit-stripe-woo-payment-gateway' ),
				],
				'desc_tip' => true,
			],
			'title'                       => [
				'title'    => __( 'Title', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'class'    => 'fkwcs_checkout_options',
				'id'       => 'fkwcs_express_checkout_title',
				'desc'     => __( 'Add a title above Express Checkout button on Checkout page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_title'],
				'desc_tip' => true,
			],

			'checkout_button_width'     => [
				'title'    => __( 'Button width', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'number',
				'class'    => 'fkwcs_checkout_options',
				'id'       => 'fkwcs_express_checkout_button_width',
				'desc'     => __( 'Select width for button (in px). Default width 100%', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_button_width'],
				'desc_tip' => true,
			],
			'checkout_button_alignment' => [
				'title'    => __( 'Alignment', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'select',
				'class'    => 'fkwcs_checkout_options',
				'id'       => 'fkwcs_express_checkout_button_alignment',
				'desc'     => __( 'This setting will align title, tagline and button based on selection on Checkout page.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_button_alignment'],
				'options'  => [
					'left'   => __( 'Left', 'funnelkit-stripe-woo-payment-gateway' ),
					'center' => __( 'Center', 'funnelkit-stripe-woo-payment-gateway' ),
					'right'  => __( 'Right', 'funnelkit-stripe-woo-payment-gateway' ),
				],
				'desc_tip' => true,
			],
			'checkout_separator_text'   => [
				'title'    => __( 'Separator text', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'     => 'text',
				'class'    => 'fkwcs_checkout_options',
				'id'       => 'fkwcs_express_checkout_separator_checkout',
				'desc'     => __( 'Add separator text for Checkout page. If empty will show default separator text.', 'funnelkit-stripe-woo-payment-gateway' ),
				'value'    => $values['express_checkout_separator_checkout'],
				'desc_tip' => true,
			],
			'checkout_page_section_end' => [
				'type' => 'sectionend',
				'id'   => 'fkwcs_express_checkout',
			],
		];

		return $settings;
	}

	/**
	 * Save express checkout settings
	 *
	 * @return void
	 */
	public function express_checkout_option_updates() {
		$current_section = empty( $_REQUEST['section'] ) ? '' : sanitize_title( wp_unslash( $_REQUEST['section'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $_POST['save'] ) || 'fkwcs_express_checkout' !== $current_section ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$express_checkout = [];
		$radio_checkbox   = [
			'express_checkout_enabled' => 'no',
		];
		foreach ( $_POST as $key => $value ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( 0 === strpos( $key, 'fkwcs_express_checkout' ) ) {
				$k = sanitize_text_field( str_replace( 'fkwcs_', '', $key ) );
				if ( ! empty( $radio_checkbox ) && in_array( $k, array_keys( $radio_checkbox ), true ) ) {
					$express_checkout[ $k ] = 'yes';
					unset( $radio_checkbox[ $k ] );
				} else {
					if ( is_array( $value ) ) {
						$express_checkout[ $k ] = array_map( 'sanitize_text_field', $value );
					} else {
						$express_checkout[ $k ] = sanitize_text_field( $value );
					}
				}
				unset( $_POST[ $key ] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		if ( empty( $express_checkout ) ) {
			return;
		}

		$settings_data                              = get_option( 'woocommerce_fkwcs_stripe_settings' );
		$settings_data['express_checkout_location'] = [];
		$settings_data                              = array_merge( $settings_data, $radio_checkbox, $express_checkout );

		update_option( 'woocommerce_fkwcs_stripe_settings', $settings_data );
	}

	/**
	 * Adds custom CSS
	 * Hide navigation menu item
	 *
	 * @return void
	 */
	public function add_custom_css() {
		wp_register_style( 'fkwcs-style', FKWCS_URL . 'admin/assets/css/admin.css', [], FKWCS_VERSION );
		wp_enqueue_style( 'fkwcs-style' );
		?>
        <style type="text/css">
            a[href='<?php echo esc_url( get_site_url() ); ?>/wp-admin/admin.php?page=wc-settings&tab=fkwcs_api_settings'].nav-tab {
                display: none
            }
        </style>
		<?php
	}

	/**
	 * Get active mode API key
	 *
	 * @return false|mixed|null
	 */
	protected function get_api_key() {
		$test_mode    = get_option( 'fkwcs_test_mode', 'test' );
		$test_pub_key = get_option( 'fkwcs_test_pub_key', '' );
		$live_pub_key = get_option( 'fkwcs_pub_key', '' );

		return ( 'test' === $test_mode ) ? $test_pub_key : $live_pub_key;
	}

	/**
	 * Get gateway all mode keys
	 *
	 * @param $key
	 *
	 * @return array|bool|mixed|string|null
	 */
	private function get_gateway_keys( $key = 'all' ) {

		$gateway_keys                        = array();
		$gateway_keys['test_mode']           = get_option( 'fkwcs_mode', '' );
		$gateway_keys['test_pub_key']        = get_option( 'fkwcs_test_pub_key', '' );
		$gateway_keys['test_secret_key']     = get_option( 'fkwcs_test_secret_key', '' );
		$gateway_keys['live_pub_key']        = get_option( 'fkwcs_pub_key', '' );
		$gateway_keys['live_secret_key']     = get_option( 'fkwcs_secret_key', '' );
		$gateway_keys['live_webhook_secret'] = get_option( 'fkwcs_live_webhook_secret', '' );
		$gateway_keys['test_webhook_secret'] = get_option( 'fkwcs_test_webhook_secret', '' );
		$gateway_keys['debug']               = 'yes' === get_option( 'fkwcs_debug_log', 'no' );

		if ( 'live' === $gateway_keys['test_mode'] ) {
			$gateway_keys['webhook_secret'] = $gateway_keys['live_webhook_secret'];
		}

		if ( 'test' === $gateway_keys['test_mode'] ) {
			$gateway_keys['webhook_secret'] = $gateway_keys['test_webhook_secret'];
		}

		unset( $gateway_keys['live_webhook_secret'] );
		unset( $gateway_keys['test_webhook_secret'] );

		if ( 'all' === $key ) {
			return $gateway_keys;
		}

		if ( isset( $gateway_keys[ $key ] ) ) {
			return $gateway_keys[ $key ];
		}

		return null;
	}

	/**
	 * Include JS assets on the gateway settings page
	 *
	 * @return void
	 */
	public function enqueue_js() {
		$allow_scripts_methods = apply_filters( 'fkwcs_allow_admin_scripts_methods', [
			'fkwcsstripe',
			'fkwcs_stripe',
			'fkwcs_express_checkout',
			'fkwcs_stripe_ideal',
			'fkwcs_stripe_bancontact',
			'fkwcs_stripe_p24',
			'fkwcs_stripe_sepa',
		] );

		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && ( 'fkwcs_api_settings' === $_GET['tab'] || isset( $_GET['section'] ) && ( in_array( sanitize_text_field( $_GET['section'] ), $allow_scripts_methods, true ) ) ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			wp_register_script( 'fkwcs-stripe-external', 'https://js.stripe.com/v3/', [], FKWCS_VERSION, true );
			wp_enqueue_script( 'fkwcs-stripe-external' );

			wp_register_script( 'fkwcs-admin-js', plugins_url( 'assets/js/admin.js', __FILE__ ), [ 'jquery' ], FKWCS_VERSION, true );
			wp_enqueue_script( 'fkwcs-admin-js' );

			$settings_data       = array();
			$fkwcs_settings_data = Helper::get_gateway_settings();
			$pub_key             = $this->get_api_key();

			if ( ! empty( $fkwcs_settings_data ) && is_array( $fkwcs_settings_data ) ) {
				$settings_data = array(
					'theme' => $fkwcs_settings_data['express_checkout_button_theme'],
					'title' => $fkwcs_settings_data['express_checkout_title'],
					'text'  => $fkwcs_settings_data['express_checkout_button_text'],
				);
			}

			wp_localize_script( 'fkwcs-admin-js', 'fkwcs_admin_data', apply_filters( 'fkwcs_admin_localize_script_args', [
				'site_url'                 => get_site_url() . '/wp-admin/admin.php?page=wc-settings',
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'icons'                    => [
					'applepay_gray'  => FKWCS_URL . 'assets/icons/apple_pay_gray.svg',
					'applepay_light' => FKWCS_URL . 'assets/icons/apple_pay_light.svg',
					'gpay_light'     => FKWCS_URL . 'assets/icons/gpay_light.svg',
					'gpay_gray'      => FKWCS_URL . 'assets/icons/gpay_gray.svg',
				],
				'settings'                 => $settings_data,
				'messages'                 => [
					'default_text'  => __( 'Pay Now', 'funnelkit-stripe-woo-payment-gateway' ),
					'checkout_note' => __( 'NOTE: Title and Tagline appears only on Checkout page.', 'funnelkit-stripe-woo-payment-gateway' ),
					'no_method'     => sprintf( __( 'No payment method detected. Either your browser is not supported or you do not have save cards. For more details read %1$1sdocument$2$2s.', 'funnelkit-stripe-woo-payment-gateway' ), '<a href="#" target="_blank">', '</a>' )
				],
				'pub_key'                  => $pub_key,
				'is_connected'             => $this->is_stripe_connected(),
				'is_manually_connected'    => $this->is_manually_connected(), //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'fkwcs_admin_settings_tab' => isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '', //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'fkwcs_admin_current_page' => isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '', //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'fkwcs_admin_nonce'        => wp_create_nonce( 'fkwcs_admin_request' ),
				'dashboard_url'            => admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' ),
				'generic_error'            => __( 'Something went wrong! Please reload the page and try again.', 'funnelkit-stripe-woo-payment-gateway' ),
				'test_btn_label'           => __( 'Save changes', 'funnelkit-stripe-woo-payment-gateway' ),
				'stripe_key_notice'        => __( 'Please enter all keys to connect to stripe.', 'funnelkit-stripe-woo-payment-gateway' ),
				'stripe_key_error'         => __( 'You must enter your API keys or connect the plugin before performing a connection test. Mode:', 'funnelkit-stripe-woo-payment-gateway' ),
				'stripe_key_unavailable'   => __( 'Keys Unavailable.', 'funnelkit-stripe-woo-payment-gateway' ),
				'stripe_disconnect'        => __( 'Your Stripe account has been disconnected.', 'funnelkit-stripe-woo-payment-gateway' ),
				'stripe_connect_other_acc' => __( 'You can connect other Stripe account now.', 'funnelkit-stripe-woo-payment-gateway' ),
				'stripe_notice_re_verify'  => __( 'Sorry, we are unable to re-verify domain at the moment. ', 'funnelkit-stripe-woo-payment-gateway' )
			] ) );
		}
	}

	/**
	 * Displays the stripe fee
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function display_order_fee( $order_id ) {
		$order    = wc_get_order( $order_id );
		$currency = Helper::get_stripe_currency( $order );

		$fee = Helper::get_meta( $order, Helper::FKWCS_STRIPE_FEE );
		if ( empty( $fee ) || empty( $currency ) ) {
			return;
		}
		?>
        <tr>
            <td class="label fkwcs-stripe-fee">
				<?php echo wp_kses_post( wc_help_tip( __( 'This fee, Stripe collects for the transaction.', 'funnelkit-stripe-woo-payment-gateway' ) ) ); ?>
				<?php esc_html_e( 'Stripe Fee:', 'funnelkit-stripe-woo-payment-gateway' ); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
                -<?php echo wp_kses_post( wc_price( $fee, [ 'currency' => $currency ] ) ); ?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Displays the net total of the transaction without the stripe charges
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function display_order_payout( $order_id ) {
		$order    = wc_get_order( $order_id );
		$currency = Helper::get_stripe_currency( $order );

		$net = Helper::get_meta( $order,Helper::FKWCS_STRIPE_NET );
		if ( empty( $net ) || empty( $currency ) ) {
			return;
		}
		?>
        <tr>
            <td class="label fkwcs-stripe-payout">
				<?php echo wp_kses_post( wc_help_tip( __( 'This net total that will be credited to your stripe bank account. This may be in the currency that is set in your Stripe account.', 'funnelkit-stripe-woo-payment-gateway' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Stripe Payout:', 'funnelkit-stripe-woo-payment-gateway' ); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
				<?php echo wp_kses_post( wc_price( $net, [ 'currency' => $currency ] ) ); ?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Add plugin action links on plugins listing page
	 *
	 * @param $links
	 *
	 * @return array|string[]
	 */
	public function add_plugin_settings_link( $links ) {
		$plugin_links = array(
			'fkwcs_settings_link'      => '<a href="admin.php?page=wc-settings&tab=fkwcs_api_settings">' . __( 'Settings', 'funnelkit-stripe-woo-payment-gateway' ) . '</a>',
			'fkwcs_documentation_link' => '<a href="#">' . __( 'Documentation', 'funnelkit-stripe-woo-payment-gateway' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Displays the express checkout preview in admin
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function express_checkout_preview( $value ) {
		do_action( 'fkwcs_before_express_checkout_preview' );

		$learn_more = sprintf( "<a href='%s'>%s</a>", 'https://funnelkit.com/docs/stripe-gateway-for-woocommerce/troubleshooting/express-payment-buttons-not-showing/', __( 'Learn more', 'funnelkit-stripe-woo-payment-gateway' ) );
		?>
        <tr valign="top" class="fkwcs-smart-container">
            <th scope="row">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> </label>
            </th>
            <td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
                <fieldset>
                    <div class="fkwcs_express_checkout_preview_wrapper">
                        <div class="fkwcs_express_checkout_preview"></div>
                        <div id="fkwcs-payment-request-custom-button" class="fkwcs-payment-request-custom-button-admin">
                            <button lang="auto" class="fkwcs-payment-request-custom-button-render fkwcs_express_checkout_button fkwcs-express-checkout-button large" role="button" type="submit" style="height: 40px;">
                                <div class="fkwcs-express-checkout-button-inner" tabindex="-1">
                                    <div class="fkwcs-express-checkout-button-shines">
                                        <div class="fkwcs-express-checkout-button-shine fkwcs-express-checkout-button-shine--scroll"></div>
                                        <div class="fkwcs-express-checkout-button-shine fkwcs-express-checkout-button-shine--hover"></div>
                                    </div>
                                    <div class="fkwcs-express-checkout-button-content">
                                        <span class="fkwcs-express-checkout-button-label"></span>
                                        <img src="" class="fkwcs-express-checkout-button-icon">
                                    </div>
                                    <div class="fkwcs-express-checkout-button-overlay"></div>
                                    <div class="fkwcs-express-checkout-button-border"></div>
                                </div>
                            </button>
                            <button lang="auto" class="fkwcs-payment-request-custom-button-render fkwcs_express_checkout_button fkwcs-express-checkout-button large" role="button" type="submit" style="height: 40px;">
                                <div class="fkwcs-express-checkout-button-inner" tabindex="-1">
                                    <div class="fkwcs-express-checkout-button-shines">
                                        <div class="fkwcs-express-checkout-button-shine fkwcs-express-checkout-button-shine--scroll"></div>
                                        <div class="fkwcs-express-checkout-button-shine fkwcs-express-checkout-button-shine--hover"></div>
                                    </div>
                                    <div class="fkwcs-express-checkout-button-content">
                                        <span class="fkwcs-express-checkout-button-label"></span>
                                        <img src="" class="fkwcs-express-checkout-button-icon">
                                    </div>
                                    <div class="fkwcs-express-checkout-button-overlay"></div>
                                    <div class="fkwcs-express-checkout-button-border"></div>
                                </div>
                            </button>
                        </div>
                    </div>
                </fieldset>
                <fieldset>
                    <div class="fkwcs_test_visibility">
                        <button type="button" class="fkwcs_test_visibility button-primary"><?php esc_html_e( 'Test Visibility' ) ?></button>
                    </div>
                    <div class="fkwcs_express_checkout_connection_div">
                        <div class="fkwcs-btn-type-info-wrapper" id="is_apple_pay_available">
                            <span class="fkwcs_btn_connection">&#10060;</span>
                            <p class="fkwcs_not_supported"><?php esc_html_e( 'Apple Pay is not supported on this browser. ', 'funnelkit-stripe-woo-payment-gateway' ) ?><?php echo wp_kses_post( $learn_more ); ?></p>
                            <p class="fkwcs_is_supported"><?php esc_html_e( 'Apple Pay is supported on this browser. ', 'funnelkit-stripe-woo-payment-gateway' ) ?><?php echo wp_kses_post( $learn_more ); ?></p>
                        </div>
                        <div class="fkwcs-btn-type-info-wrapper" id="is_google_pay_available">
                            <span class="fkwcs_btn_connection">&#10060;</span>
                            <p class="fkwcs_not_supported"><?php esc_html_e( 'Google Pay is not  supported on this browser. ', 'funnelkit-stripe-woo-payment-gateway' ) ?><?php echo wp_kses_post( $learn_more ); ?></p>
                            <p class="fkwcs_is_supported"><?php esc_html_e( 'Google Pay is supported on this browser. ', 'funnelkit-stripe-woo-payment-gateway' ) ?><?php echo wp_kses_post( $learn_more ); ?></p>
                        </div>
                        <span class="spinner"></span>
                    </div>
                </fieldset>
            </td>
        </tr>
		<?php
		do_action( 'fkwcs_after_express_checkout_preview' );
	}

	/**
	 * Handle redirect and save some options
	 *
	 * @return void
	 */
	public function handle_redirect() {
		$redirect_url = apply_filters( 'fkwcs_stripe_connect_redirect_url', admin_url( '/admin.php?page=wc-settings&tab=fkwcs_api_settings' ) );

		if ( isset( $_GET['tab'] ) && wc_clean( $_GET['tab'] ) === 'fkwcs_api_settings' && isset( $_GET['error'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->settings['fkwcs_con_status'] = 'failed';
			$this->update_options( $this->settings );
			$redirect_url = add_query_arg( 'path', '/failed', $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( isset( $_GET['tab'] ) && $_GET['tab'] === 'fkwcs_api_settings' && isset( $_GET['response'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$response = $_GET; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( ! empty( $response['response'] ) ) {
				$live_details = [];
				$test_details = [];
				wp_parse_str( $response['response']['live'], $live_details );
				wp_parse_str( $response['response']['test'], $test_details );
				$this->settings['fkwcs_pub_key']    = ! empty( $live_details['stripe_publishable_key'] ) ? wc_clean( $live_details['stripe_publishable_key'] ) : '';
				$this->settings['fkwcs_secret_key'] = $live_details['access_token'];

				$this->settings['fkwcs_test_pub_key']    = ! empty( $test_details['stripe_publishable_key'] ) ? wc_clean( $test_details['stripe_publishable_key'] ) : '';
				$this->settings['fkwcs_test_secret_key'] = $test_details['access_token'];
				$this->settings['fkwcs_account_id']      = $live_details['stripe_user_id'];
				$this->settings['fkwcs_con_status']      = 'success';
				$redirect_url                            = add_query_arg( 'path', '/gateways', $redirect_url );
			} else {
				$this->settings['fkwcs_pub_key']         = '';
				$this->settings['fkwcs_secret_key']      = '';
				$this->settings['fkwcs_test_pub_key']    = '';
				$this->settings['fkwcs_test_secret_key'] = '';
				$this->settings['fkwcs_account_id']      = '';
				$this->settings['fkwcs_con_status']      = 'failed';
				$redirect_url                            = add_query_arg( 'path', '/failed', $redirect_url );
			}

			$this->settings['fkwcs_auto_connect'] = 'yes';
			$this->settings['fkwcs_debug_log']    = 'yes';
			$this->update_options( $this->settings );
			do_action( 'fkwcs_after_connect_with_stripe', $this->settings['fkwcs_con_status'] );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Check if stripe is connected
	 *
	 * @return bool
	 */
	public function is_stripe_connected() {
		if ( 'success' === $this->options_keys['fkwcs_con_status'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Filter gateway settings
	 *
	 * @param $array
	 *
	 * @return array|mixed
	 */
	public function filter_settings_fields( $array = [] ) {
		if ( 'success' === $this->options_keys['fkwcs_con_status'] ) {
			unset( $array['test_conn_button'] );
		}

		return $array;
	}

	/**
	 * Update options
	 *
	 * @param $options
	 *
	 * @return void
	 */
	public function update_options( $options ) {
		if ( ! is_array( $options ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}
	}

	/**
	 * Display stripe connected or disconnect UI
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function account_id( $value ) {
		if ( false === $this->is_stripe_connected() ) {
			return;
		}

		$option_value = $this->options_keys['fkwcs_account_id'];

		/**
		 * Action before connected with stripe.
		 *
		 */
		do_action( 'fkwcs_before_connected_with_stripe' );

		?>
        <tr valign="top">
            <th scope="row">
                <label><?php echo esc_html( $value['title'] ); ?></label>
            </th>
            <td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
                <fieldset>
                    <div class="account_status" data-account-connect="<?php echo 'no' === $this->options_keys['fkwcs_auto_connect'] ? 'no' : 'yes' ?>">
                        <div>
							<?php
							if ( 'no' === $this->options_keys['fkwcs_auto_connect'] ) {
								/* translators: %1$1s %2$2s %3$3s: HTML Markup */
								esc_html_e( 'Your manually managed API keys are valid.', 'funnelkit-stripe-woo-payment-gateway' );
								echo '<span style="color:green;font-weight:bold;font-size:20px;margin-left:5px;">&#10004;</span>';
								echo '<div class="notice inline notice-success">';
								echo '<p>' . esc_html__( 'It is highly recommended to Connect with Stripe for easier setup and improved security.', 'funnelkit-stripe-woo-payment-gateway' ) . '</p>';
								echo wp_kses_post( '<a class="fkwcs_connect_btn" href="' . $this->get_connect_url() . '"><span>' . __( 'Connect with Stripe', 'funnelkit-stripe-woo-payment-gateway' ) . '</span></a>' );
								echo '</div>';
							} else {
								/* translators: $1s Account name, $2s html markup, $3s account id, $4s html markup */
								echo wp_kses_post( sprintf( __( 'Account %2$1s is connected.', 'funnelkit-stripe-woo-payment-gateway' ), '<strong>', $option_value, '</strong>' ) );
								echo '<span style="color:green;font-weight:bold;font-size:20px;margin-left:5px;">&#10004;</span>';
								echo '';
							}
							?>
                        </div>
                        <div>
							<?php
							echo '<a href="javascript:void(0);" id="fkwcs_disconnect_acc">';
							esc_html_e( 'Disconnect &amp; connect other account?', 'funnelkit-stripe-woo-payment-gateway' );
							echo '</a>';

							if ( 'no' === $this->options_keys['fkwcs_auto_connect'] ) {
								if ( ! isset( $_GET['connect'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
									echo ' | ' . '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=fkwcs_api_settings&connect=manually" class="fkwcs_connect_mn_btn">' . __( 'Manage API keys manually', 'funnelkit-stripe-woo-payment-gateway' ) . '</a>'; //phpcs:ignore WordPress.Security.EscapeOutput
								}
							}
							?>
                        </div>
                    </div>
                </fieldset>
            </td>
        </tr>
		<?php

		/**
		 * Action after connected with stripe.
		 */
		do_action( 'fkwcs_after_connected_with_stripe' );
	}

	/**
	 * Display notice related to keys
	 *
	 * @return void
	 */
	public function if_keys_set_check() {
		$settings = $this->get_gateway_keys();

		if ( isset( $_GET['fkwcs_nonce'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: %1$1s HTML Markup */
			echo wp_kses_post( sprintf( '<div class="notice notice-error"><p>' . __( 'You Stripe Publishable and Secret Keys are not set correctly. You can connect to Stripe and correct them from <a href="%1$1s">here.</a>', 'funnelkit-stripe-woo-payment-gateway' ) . '</p></div>', admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' ) ) );

			return;
		}

		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'fkwcs_api_settings' === $_GET['tab'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mode = '';
			if ( 'live' === $settings['test_mode'] && ( empty( $settings['live_pub_key'] ) || empty( $settings['live_secret_key'] ) ) ) {
				$mode = 'live';
			} elseif ( 'test' === $settings['test_mode'] && ( empty( $settings['test_pub_key'] ) || empty( $settings['test_secret_key'] ) ) ) {
				$mode = 'test';
			}

			if ( empty( $mode ) ) {
				return;
			}
			$stripe_connect_link = '<a href=' . $this->get_connect_url() . '>' . __( 'Stripe Connect', 'funnelkit-stripe-woo-payment-gateway' ) . '</a>';

			$manual_api_link = '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=fkwcs_api_settings&connect=manually" class="fkwcs_connect_mn_btn">' . __( 'Manage API keys manually', 'funnelkit-stripe-woo-payment-gateway' ) . '</a>';

			/* translators: %1$1s: mode, %2$2s, %3$3s: HTML Markup */
			echo wp_kses_post( sprintf( '<div class="notice notice-error"><p>' . __( 'Stripe Keys for %1$1s mode are not set correctly. Reconnect via %2$2s or %3$3s', 'funnelkit-stripe-woo-payment-gateway' ) . '</p></div>', $mode, $stripe_connect_link, $manual_api_link ) );
		}
	}

	/**
	 * Check if keys are set
	 *
	 * @return bool
	 */
	public function if_keys_set() {
		$settings = $this->get_gateway_keys();
		if ( ( 'live' === $settings['test_mode'] && empty( $settings['live_pub_key'] ) && empty( $settings['live_secret_key'] ) ) || ( 'test' === $settings['test_mode'] && empty( $settings['test_pub_key'] ) && empty( $settings['test_secret_key'] ) ) || ( empty( $settings['test_mode'] ) && empty( $settings['live_secret_key'] ) && empty( $settings['test_secret_key'] ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Method to check and attempt domain verification process
	 *
	 * @return bool
	 */
	public function maybe_verify_domain() {
		/**
		 * Check if we have already setup for the current domain
		 */
		if ( ! empty( $this->verified_domain ) && $this->domain === $this->verified_domain && $this->domain_is_verfied ) {
			return true;
		}

		/**
		 * Check if we have SSL installed
		 */
		if ( ! is_ssl() ) {
			Helper::log( 'Apple Pay domain verification failed due to SSL certificate is not installed.' );

			return false;
		}

		$is_already_configured = $this->already_verified_domain();
		if ( $is_already_configured ) {
			update_option( 'fkwcs_apple_pay_verified_domain', $this->domain );
			update_option( 'fkwcs_apple_pay_domain_is_verified', true );
			Helper::log( 'Apple Pay domain verification already setup correctly.' );

			return true;
		}

		$response = $this->try_to_move_file_to_apple_dir();
		if ( ! $response['success'] ) {
			Helper::log( 'Unable to copy apple pay domain verification file, reason:' . $response['message'] );

		}

		return $this->verify_domain_for_apple_pay();
	}

	/**
	 * Automatic verification for apple pay using stripe api
	 *
	 * @return bool
	 */
	public function verify_domain_for_apple_pay() {
		if ( empty( $this->options_keys ) || ! isset( $this->options_keys['fkwcs_secret_key'] ) || empty( $this->options_keys['fkwcs_secret_key'] ) ) {
			return false;
		}

		$client = new Client( $this->options_keys['fkwcs_secret_key'] );

		$response = $client->apple_pay_domains( 'create', [
			[
				'domain_name' => $this->domain,
			],
		] );

		$verification_response = $response['success'] ? $response['data'] : false;
		if ( $verification_response ) {
			update_option( 'fkwcs_apple_pay_verified_domain', $this->domain );
			update_option( 'fkwcs_apple_pay_domain_is_verified', true );

			return true;
		}

		delete_option( 'fkwcs_apple_pay_domain_is_verified' );
		delete_option( 'fkwcs_apple_pay_verified_domain' );
		Helper::log( 'Unable to verify apple domain, reason:' . $response['message'] );

		return false;
	}

	/**
	 * Checks if domain is verified for Apple Pay
	 *
	 * @return bool
	 */
	public function already_verified_domain() {
		$well_known_dir = untrailingslashit( ABSPATH ) . '/' . self::APPLEPAY_DIR;
		$full_path      = $well_known_dir . '/' . self::APPLEPAY_FILE;
		if ( ! file_exists( $full_path ) ) {
			return false;
		}

		if ( empty( $this->options_keys['fkwcs_secret_key'] ) ) {
			return false;
		}
		$client             = new Client( $this->options_keys['fkwcs_secret_key'] );
		$found_domain       = false;
		$registered_domains = $client->apple_pay_domains( 'all', [] );

		if ( ! is_wp_error( $registered_domains ) && $registered_domains ) {
			// loop through domains and delete if they match domain of site.
			foreach ( $registered_domains['data']->data as $domain ) {
				if ( $domain->domain_name === $this->domain ) {
					$found_domain = true;
					break;
				}
			}
		}

		return $found_domain;
	}

	/**
	 * Try copying domain verification file in the root
	 *
	 * @return array|true[]
	 */
	public function try_to_move_file_to_apple_dir() {
		$well_known_dir = untrailingslashit( ABSPATH ) . '/' . self::APPLEPAY_DIR;
		$full_path      = $well_known_dir . '/' . self::APPLEPAY_FILE;

		if ( ! file_exists( $well_known_dir ) ) {
			if ( ! @mkdir( $well_known_dir, 0755 ) ) { // @codingStandardsIgnoreLine
				return [
					'success'                                        => false,
					/* translators: 1 - 4 html entities */
					'message'                                        => sprintf( __( 'Unable to create domain association folder to domain root due to file permissions. Please create %1$1s.well-known%2$2s directory under domain root and place %3$3sdomain verification file%4$4s under it and refresh.', 'funnelkit-stripe-woo-payment-gateway' ), '<code>', '</code>', '<a href="https://stripe.com/files/apple-pay/apple-developer-merchantid-domain-association" target="_blank">', '</a>' ),
				];
			}
		}

		if ( ! @copy( FKWCS_DIR . '/compatibilities/' . self::APPLEPAY_FILE, $full_path ) ) { // @codingStandardsIgnoreLine
			return [
				'success' => false,
				'message' => __( 'Unable to copy domain association file to domain root.', 'funnelkit-stripe-woo-payment-gateway' ),
			];
		}

		return [
			'success' => true,
		];
	}

	/**
	 * Admin notices for multiple conditions
	 *
	 * @return void
	 */
	public function init_notices() {

		// If user has permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			add_action( 'admin_notices', [ $this, 'insufficient_permission' ] );

			return;
		}

		// If keys are not set bail.
		if ( ! $this->if_keys_set() ) {
			add_action( 'admin_notices', [ $this, 'if_keys_set_check' ] );
		}

		// If no SSL bail.
		if ( 'live' === $this->get_gateway_keys( 'test_mode' ) && ! is_ssl() ) {
			add_action( 'admin_notices', [ $this, 'ssl_not_connected' ] );
		}

		// IF stripe connection established successfully . ( fkwcs_call needs to be replaced with actual call parameter )
		if ( isset( $_GET['fkwcs_call'] ) && ! empty( $_GET['fkwcs_call'] ) && 'success' === $_GET['fkwcs_call'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'admin_notices', [ $this, 'connect_success_notice' ] );
		}

		// IF stripe connection not established successfully.
		if ( isset( $_GET['fkwcs_call'] ) && ! empty( $_GET['fkwcs_call'] ) && 'failed' === $_GET['fkwcs_call'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'admin_notices', [ $this, 'connect_failure_notice' ] );
		}

		// Add notice if missing webhook secret key.
		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && 'live' === $this->get_gateway_keys( 'test_mode' ) && ! $this->get_gateway_keys( 'webhook_secret' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'admin_notices', [ $this, 'webhook_missing_notice' ] );
		}

		// PHP version check.
		if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
			add_action( 'admin_notices', [ $this, 'stripe_php_version_notice' ] );
		}

		$gateway_settings = Helper::get_gateway_settings();
		if ( $this->is_page( 'wc-settings', 'checkout', 'fkwcs_express_checkout' ) && ! empty( $this->options_keys['fkwcs_secret_key'] ) && 'yes' === $gateway_settings['express_checkout_enabled'] && is_ssl() && null === get_option( 'fkwcs_apple_pay_domain_is_verified', null ) && ! $this->is_notice_dismissed( 'fkwcs_apple_pay' ) ) {
			add_action( 'admin_notices', [ $this, 'apple_pay_verification_failed' ] );
		}
	}

	/**
	 * Checks if correct admin page
	 *
	 * @param $page
	 * @param $tab
	 * @param $section
	 *
	 * @return bool
	 */
	public function is_page( $page = 'wc-settings', $tab = 'fkwcs_api_settings', $section = '' ) {
		if ( ! is_admin() ) {
			return false;
		}

		if ( isset( $_GET['page'] ) && $page === $_GET['page'] && isset( $_GET['tab'] ) && $tab === $_GET['tab'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $section ) ) {
				if ( isset( $_GET['section'] ) && $section === $_GET['section'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return true;
				} else {
					return false;
				}
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * In-sufficient permissions notice text
	 *
	 * @return void
	 */
	public function insufficient_permission() {
		echo wp_kses_post( '<div class="notice notice-error is-dismissible"><p>' . __( 'Error: The current user does not have sufficient permissions to perform this action. Please reload the page and try again.', 'funnelkit-stripe-woo-payment-gateway' ) . '</p></div>' );
	}

	/**
	 * SSL not found notice text
	 *
	 * @return void
	 */
	public function ssl_not_connected() {
		echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'No SSL was detected, Stripe live mode requires SSL.', 'funnelkit-stripe-woo-payment-gateway' ) . '</p></div>' );
	}

	/**
	 * Connect success notice text
	 *
	 * @return void
	 */
	public function connect_success_notice() {
		echo wp_kses_post( '<div class="notice notice-success is-dismissible"><p>' . __( 'Your Stripe account has been connected to your WooCommerce store. You may now accept payments in live and test mode.', 'funnelkit-stripe-woo-payment-gateway' ) . '</p></div>' );
	}

	/**
	 * Connect failure notice text
	 *
	 * @return void
	 */
	public function connect_failure_notice() {
		echo wp_kses_post( '<div class="notice notice-error is-dismissible"><p>' . __( 'We were not able to connect your Stripe account. Please try again. ', 'funnelkit-stripe-woo-payment-gateway' ) . '</p></div>' );
	}

	/**
	 * Webhook missing notice text
	 *
	 * @return void
	 */
	public function webhook_missing_notice() {
		/* translators: %1$s Webhook secret page link, %2$s Webhook guide page link  */
		echo wp_kses_post( '<div class="notice notice-error"><p>' . sprintf( __( 'Stripe requires using the %1$swebhook%2$s. %3$sWebhook Guide%4$s ', 'funnelkit-stripe-woo-payment-gateway' ), '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' ) . '">', '</a>', '<a href="https://funnelkit.com/docs/stripe-gateway-for-woocommerce/webhooks/" target="_blank">', '</a>' ) . '</p></div>' );
	}

	/**
	 * Live secret missing notice text
	 *
	 * @return void
	 */
	public function no_live_secret_key() {
		echo wp_kses_post( '<div class="notice notice-error is-dismissible"><p>' . __( 'We cannot find live secret key in database, Live secret key is required for Apple Pay domain verification. ', 'funnelkit-stripe-woo-payment-gateway' ) . '</p></div>' );
	}

	/**
	 * Apple Pay verification failed notice text
	 *
	 * @return void
	 */
	public function apple_pay_verification_failed() {
		/* translators: %1s - %3s HTML Entities, %4s Error Message */
		echo wp_kses_post( '<div class="notice fkwcs_dismiss_notice_wrap_fkwcs_apple_pay notice-error"><p>' . sprintf( __( 'Unable to verify domain. The path <b>%1$s</b> does not have write permissions. <br/> <strong>Solution:</strong> To verify domain, please contact your host to provide write permission and re-verify again.  Alternatively, you can also manually verify the domain by following this guide. For further help, <a target="_blank" href="%2$s"> contact support.</a>
<br/>
<br/>
<a href="javascript:void(0)" class="button button-primary fkwcs_apple_pay_domain_verification" > I have given file writing permission, re-verify </a>  <a href="javascript:void(0)" class="button fkwcs_dismiss_notice" data-notice="fkwcs_apple_pay"> I will verify manually </a> <a href="javascript:void(0)" class="fkwcs_dismiss_notice" data-notice="fkwcs_apple_pay"> Dismiss </a> ', 'funnelkit-stripe-woo-payment-gateway' ), ABSPATH, esc_url( 'https://funnelkit.com/docs/stripe-gateway-for-woocommerce/troubleshooting/manual-domain-registration-for-apple-pay/' ) ) . '</p></div>' );
	}

	/**
	 * PHP min version notice text
	 *
	 * @return void
	 */
	function stripe_php_version_notice() {
		$message = __( 'Stripe Payment Gateway for WooCommerce requires PHP version 7.0+. Please reach out to your host to upgrade the PHP version.', 'funnelkit-stripe-woo-payment-gateway' );
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Display webhook URL UI
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function webhook_url( $value ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return;
		}
		$data         = $value;
		$tooltip_html = $data['desc_tip'];
		if ( $tooltip_html && 'checkbox' === $value['type'] ) {
			$tooltip_html = '<p class="description">' . $tooltip_html . '</p>';
		} elseif ( $tooltip_html ) {
			$tooltip_html = wc_help_tip( $tooltip_html );
		}
		?>
        <tr valign="top">
            <th scope="row">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?><?php echo wp_kses_post( $tooltip_html ); ?></label>
            </th>
            <td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
                <fieldset>
                    <strong><?php echo esc_url( Helper::get_webhook_url() ); //phpcs:ignore WordPress.Security.EscapeOutput?></strong>
                </fieldset>
                <p class="description">
					<?php echo wp_kses_post( $value['desc'] ); ?>
                </p>
            </td>
        </tr>
		<?php
	}

	/**
	 * Displays stripe test connection UI
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function wc_fkwcs_connection_test( $value ) {
		$tooltip_html = '';
		?>
        <tr valign="top">
            <th scope="row">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?><?php echo wp_kses_post( $tooltip_html ); ?></label>
            </th>
            <td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
                <fieldset>
                    <a id="fkwcs_test_connection" class="button wc_fkwcs_create_webhook_button" href="javascript:void(0)">
						<?php esc_html_e( 'Test Connection', 'funnelkit-stripe-woo-payment-gateway' ) ?>
                    </a>
                </fieldset>
                <p class="description">
					<?php echo wp_kses_post( $value['desc'] ); ?>
                </p>
            </td>
        </tr>
		<?php
	}

	/**
	 * Displays apple pay re-verify domain UI
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public function wc_fkwcs_apple_pay_domain( $value ) {
		$tooltip_html = '';
		?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?><?php echo wp_kses_post( $tooltip_html ); ?></label>
                </th>
                <td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
                    <fieldset>
                        <a class="button fkwcs_apple_pay_domain_verification" href="javascript:void(0)">
                            <span><?php esc_html_e( 'Re-verify Domain', 'funnelkit-stripe-woo-payment-gateway' ); ?></span>
                        </a>
                    </fieldset>
                    <p class="description">
						<?php echo wp_kses_post( $value['desc'] ); ?>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	/**
	 * Disconnect account ajax CB
	 *
	 * @return void
	 */
	public function disconnect_account() {
		check_ajax_referer( 'fkwcs_admin_request', '_security' );
		$this->check_wc_admin();

		foreach ( $this->options_keys as $key => $value ) {
			delete_option( $key );
		}

		wp_send_json_success( [ 'message' => __( 'Stripe keys are reset successfully.', 'funnelkit-stripe-woo-payment-gateway' ) ] );
	}

	/**
	 * Connect webhook ajax CB
	 *
	 * @return void
	 * @throws \Stripe\Exception\ApiErrorException
	 */
	public function connect_webhook() {
		check_ajax_referer( 'fkwcs_admin_request', '_security' );
		$this->check_wc_admin();

		$mode = ! empty( $_GET['mode'] ) ? sanitize_text_field( wc_clean( $_GET['mode'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $mode ) ) {
			wp_send_json_error( [ 'msg' => __( 'Webhook could not be created', 'funnelkit-stripe-woo-payment-gateway' ) ] );
		}

		if ( 'live' === $mode ) {
			$secret_key = get_option( 'fkwcs_secret_key' ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} else {
			$secret_key = get_option( 'fkwcs_test_secret_key' ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		$params   = [ 'secret_key' => $secret_key ];
		$response = $this->create_webhook( $params );
		if ( is_array( $response ) && isset( $response['status'] ) && $response['status'] === false ) {
			wp_send_json_error( $response );
		}

		if ( ! empty( $response['livemode'] ) ) {
			update_option( 'fkwcs_live_webhook_secret', $response['secret'] );
			update_option( 'fkwcs_live_created_webhook', array( 'secret' => $response['secret'], 'id' => $response['id'] ) );
		} else {
			update_option( 'fkwcs_test_webhook_secret', $response['secret'] );
			update_option( 'fkwcs_test_created_webhook', array( 'secret' => $response['secret'], 'id' => $response['id'] ) );
		}
		wp_send_json_error( [ 'msg' => __( 'Webhook created Successfully', 'funnelkit-stripe-woo-payment-gateway' ) ] );
	}

	/**
	 * Apple pay domain verification ajax CB
	 *
	 * @return void
	 */
	public function apple_pay_domain_verification() {
		check_ajax_referer( 'fkwcs_admin_request', '_security' );
		$this->check_wc_admin();

		$this->domain_is_verfied = null;
		$this->verified_domain   = null;

		$response = $this->maybe_verify_domain();
		if ( $response ) {
			wp_send_json_success( [ 'msg' => __( 'Domain verification successful', 'funnelkit-stripe-woo-payment-gateway' ) ] );
		}

		wp_send_json_error( [ 'msg' => __( 'Domain verification failed. Please check logs for more info.', 'funnelkit-stripe-woo-payment-gateway' ) ] );
	}

	/**
	 * Dismiss notice ajax CB
	 *
	 * @return void
	 */
	public function dismiss_notice() {
		check_ajax_referer( 'fkwcs_admin_request', '_security' );
		$this->check_wc_admin();

		$notice_id = isset( $_GET['notice_identifier'] ) ? wc_clean( $_GET['notice_identifier'] ) : '';
		if ( empty( $notice_id ) ) {
			wp_send_json_error( [] );
		}

		$user = wp_get_current_user();
		$meta = get_user_meta( $user->ID, '_fkwcs_notices', true );
		if ( empty( $meta ) ) {
			$meta = [ $notice_id ];
		} else {
			$meta = array_push( $meta, $notice_id );
		}

		$result = update_user_meta( $user->ID, '_fkwcs_notices', $meta ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_update_user_meta
		if ( $result ) {
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Disconnect webhook ajax CB
	 *
	 * @return void
	 */
	public function disconnect_webhook() {
		check_ajax_referer( 'fkwcs_admin_request', '_security' );
		$this->check_wc_admin();

		if ( isset( $_GET['fkwcs_mode'] ) && ! empty( trim( $_GET['fkwcs_mode'] ) ) ) {//phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			$mode = ! empty( $_GET['fkwcs_mode'] ) ? sanitize_text_field( trim( $_GET['fkwcs_mode'] ) ) : '';//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( $mode === 'test' ) {
				$secret_key = $this->options_keys['fkwcs_test_secret_key'];//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			} else {
				$secret_key = $this->options_keys['fkwcs_secret_key'];//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}

			$webhook_secret = $this->options_keys[ 'fkwcs_' . $mode . '_webhook_secret' ];//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$option_name    = 'fkwcs_' . $mode . '_created_webhook';

			$webhook_data = get_option( $option_name );

			$params = [ 'secret_key' => $secret_key, 'webhook_secret' => $webhook_secret, 'webhook_id' => $webhook_data['id'] ];

			$response = $this->delete_webhook( $params );
			if ( ! empty( $response ) && true === $response['deleted'] ) {
				delete_option( $option_name );
				delete_option( 'fkwcs_' . $mode . '_webhook_secret' );
				wp_send_json_success( [ 'msg' => __( 'Webhook Deleted successfully', 'funnelkit-stripe-woo-payment-gateway' ) ] );
			}
		}

		wp_send_json_error( [ 'msg' => __( 'Webhook could not be deleted', 'funnelkit-stripe-woo-payment-gateway' ) ] );
	}

	/**
	 * Test stripe connection ajax CB
	 *
	 * @return void
	 */
	public function connection_test() {
		check_ajax_referer( 'fkwcs_admin_request', '_security' );
		$this->check_wc_admin();

		$results = [];
		$keys    = [];

		if ( isset( $_GET['fkwcs_test_sec_key'] ) && ! empty( trim( $_GET['fkwcs_test_sec_key'] ) ) ) {//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$keys['test'] = sanitize_text_field( trim( $_GET['fkwcs_test_sec_key'] ) );//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} else {
			$results['test']['mode']    = __( 'Test Mode:', 'funnelkit-stripe-woo-payment-gateway' );
			$results['test']['status']  = 'invalid';
			$results['test']['message'] = __( 'Please enter secret key to test.', 'funnelkit-stripe-woo-payment-gateway' );
		}

		if ( isset( $_GET['fkwcs_secret_key'] ) && ! empty( trim( $_GET['fkwcs_secret_key'] ) ) ) {//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$keys['live'] = sanitize_text_field( trim( $_GET['fkwcs_secret_key'] ) );//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} else {
			$results['live']['mode']    = __( 'Live Mode:', 'funnelkit-stripe-woo-payment-gateway' );
			$results['live']['status']  = 'invalid';
			$results['live']['message'] = __( 'Please enter secret key to live.', 'funnelkit-stripe-woo-payment-gateway' );
		}

		if ( empty( $keys ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: Empty String provided for keys', 'funnelkit-stripe-woo-payment-gateway' ) ] );
		}

		foreach ( $keys as $mode => $key ) {
			$stripe = new \Stripe\StripeClient( $key );

			try {
				$response = $stripe->customers->create( [
					/* translators: %1$1s mode */ 'description' => sprintf( __( 'My first %1s customer (created for API docs)', 'funnelkit-stripe-woo-payment-gateway' ), $mode ),
				] );
				if ( ! is_wp_error( $response ) ) {
					$results[ $mode ]['status']  = 'success';
					$results[ $mode ]['message'] = __( 'Connected to Stripe successfully', 'funnelkit-stripe-woo-payment-gateway' );
				}
			} catch ( \Stripe\Exception\CardException $e ) {
				$results[ $mode ]['status']  = 'failed';
				$results[ $mode ]['message'] = $e->getError()->message;
			} catch ( \Stripe\Exception\RateLimitException $e ) {
				// Too many requests made to the API too quickly.
				$results[ $mode ]['status']  = 'failed';
				$results[ $mode ]['message'] = $e->getError()->message;
			} catch ( \Stripe\Exception\InvalidRequestException $e ) {
				// Invalid parameters were supplied to Stripe's API.
				$results[ $mode ]['status']  = 'failed';
				$results[ $mode ]['message'] = $e->getError()->message;
			} catch ( \Stripe\Exception\AuthenticationException $e ) {
				// Authentication with Stripe's API failed.
				// (maybe you changed API keys recently).
				$results[ $mode ]['status']  = 'failed';
				$results[ $mode ]['message'] = $e->getError()->message;
			} catch ( \Stripe\Exception\ApiConnectionException $e ) {
				// Network communication with Stripe failed.
				$results[ $mode ]['status']  = 'failed';
				$results[ $mode ]['message'] = $e->getError()->message;
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				$results[ $mode ]['status']  = 'failed';
				$results[ $mode ]['message'] = $e->getError()->message;
				// Display a very generic error to the user, and maybe send.
				// yourself an email.
			} catch ( Exception $e ) {
				// Something else happened, completely unrelated to Stripe.
				$results[ $mode ]['status']  = 'failed';
				$results[ $mode ]['message'] = $e->getError()->message;
			}

			switch ( $mode ) {
				case 'test':
					$results[ $mode ]['mode'] = __( 'Test Mode:', 'funnelkit-stripe-woo-payment-gateway' );
					break;
				case 'live':
					$results[ $mode ]['mode'] = __( 'Live Mode:', 'funnelkit-stripe-woo-payment-gateway' );
					break;
				default:
					break;
			}
		}

		wp_send_json_success( [ 'data' => apply_filters( 'fkwcs_connection_test_results', $results ) ] );
	}

	/**
	 * Add manual connect link
	 *
	 * @param $links
	 *
	 * @return mixed|string
	 */
	public function add_manual_connect_link( $links ) {
		if ( ! isset( $_GET['page'] ) || ! isset( $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'fkwcs_api_settings' !== $_GET['tab'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $links;
		}

		if ( 'yes' === $this->options_keys['fkwcs_auto_connect'] || 'no' === $this->options_keys['fkwcs_auto_connect'] ) {
			return $links;
		}

		if ( ! isset( $_GET['connect'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=fkwcs_api_settings&connect=manually" class="fkwcs_connect_mn_btn">' . __( 'Manage API keys manually', 'funnelkit-stripe-woo-payment-gateway' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Create webhook ajax CB
	 *
	 * @param $params
	 *
	 * @return array|\Stripe\WebhookEndpoint
	 * @throws \Stripe\Exception\ApiErrorException
	 */
	public function create_webhook( $params ) {
		$client_secret = $params['secret_key'];
		if ( empty( $client_secret ) ) {
			return [];
		}

		$response = array();
		$client   = new \Stripe\StripeClient( array(
			'api_key'        => $client_secret,
			'stripe_version' => '2022-08-01'

		) );

		/**
		 * Loop over the existing webhooks and if match the existing one then delete and then create a new
		 */
		$webhooks = $client->webhookEndpoints->all( array( 'limit' => 100 ) );
		if ( ! is_wp_error( $webhooks ) ) {
			// validate that the webhook hasn't already been created.
			foreach ( $webhooks->data as $webhook ) {
				/**
				 * @var \Stripe\WebhookEndpoint $webhook
				 */
				if ( $webhook->url === Helper::get_webhook_url() ) {
					$client->webhookEndpoints->delete( $webhook->id );
				}
			}
		}

		$enabled_events = Helper::get_enabled_webhook_events();

		try {
			$response = $client->webhookEndpoints->create( [
				'url'            => Helper::get_webhook_url(),
				'enabled_events' => $enabled_events,
				'api_version'    => '2020-03-02',
			] );
		} catch ( \Exception $e ) {
			$response['status'] = false;
			$response['msg']    = __( 'An Error occurred while creating webhook. Error: ', 'funnelkit-stripe-woo-payment-gateway' ) . $e->getMessage();
			// Log Stripe Error Message.
			Helper::log( 'StripeException: ' . $e->getMessage() );

			return $response;
		}

		return $response;
	}

	/**
	 * Delete webhook ajax CB
	 *
	 * @param $params
	 *
	 * @return array|\Stripe\WebhookEndpoint
	 */
	public function delete_webhook( $params ) {
		$client_secret = $params['secret_key'];
		if ( empty( $client_secret ) ) {
			return [];
		}

		$response = array();
		$resp     = [];
		$client   = new \Stripe\StripeClient( $client_secret );

		try {
			$response = $client->webhookEndpoints->delete( $params['webhook_id'] );
		} catch ( \Exception $e ) {
			$resp['status'] = false;
			$resp['msg']    = __( 'Error connecting webhook. Please try again', 'funnelkit-stripe-woo-payment-gateway' );
			// Log Stripe Error Message.
			Helper::log( 'StripeException: ' . $e->getMessage() );

			wp_send_json( $resp );
		}

		return $response;
	}

	/**
	 * Check if manually connected
	 *
	 * @return false|string
	 */
	public function is_manually_connected() {
		if ( isset( $_GET['connect'] ) && 'manually' === $_GET['connect'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '1';
		}

		if ( $this->options_keys['fkwcs_auto_connect'] === 'no' ) {
			return '1';
		}

		return false;
	}

	/**
	 * Checks whether the given notice is dismissed prev by the user or not
	 *
	 * @param string $notice_id Unique identifier of the notice
	 *
	 * @return bool true when dismissed false otherwise
	 */
	public function is_notice_dismissed( $notice_id ) {
		$user = wp_get_current_user();
		$meta = get_user_meta( $user->ID, '_fkwcs_notices', true );

		if ( ! is_array( $meta ) ) {
			return false;
		}
		if ( ! in_array( $notice_id, $meta, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether or not the current logged in user can manage WooCommerce
	 *
	 * @return true on success, wp_die() otherwise
	 */
	public function check_wc_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die();
		}

		return true;
	}

}
