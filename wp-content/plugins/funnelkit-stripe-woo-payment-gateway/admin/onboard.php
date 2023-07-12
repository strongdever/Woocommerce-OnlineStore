<?php

namespace FKWCS\Gateway\Stripe;

use FKWCS\Gateway\Stripe\Helper;
use WP_REST_Server;
use WP_REST_Request;

class Onboard {
	private static $instance = null;
	public $woocommerce_slug = 'woocommerce/woocommerce.php';
	protected $namespace = 'fkwcs-onboard';

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Register REST API Calls
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->admin_controller = Admin::get_instance();

		add_action( 'admin_menu', [ $this, 'admin_menus' ] );
		add_action( 'admin_init', [ $this, 'setup_wizard' ] );
		add_action( 'admin_notices', [ $this, 'show_onboarding_notice' ] );

		add_filter( 'fkwcs_stripe_connect_redirect_url', [ $this, 'redirect_to_onboarding' ], 5 );
		add_action( 'fkwcs_after_connect_with_stripe', [ $this, 'update_connect_with_stripe_status' ] );
		add_action( 'wp_ajax_fkwcs_onboarding_enable_webhooks', [ $this, 'fkwcs_onboarding_enable_webhooks' ] );

		add_action( "admin_print_styles", array( $this, 'load_react_app' ) );
		add_action( 'admin_init', [ $this, 'hide_notices' ] );
	}

	public function admin_menus() {
		if ( empty( $_GET['page'] ) || 'fkwcs-onboarding' !== $_GET['page'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		add_dashboard_page( '', '', 'manage_options', 'fkwcs-onboarding', '' );
	}

	public function setup_wizard() {
		if ( empty( $_GET['page'] ) || 'fkwcs-onboarding' !== $_GET['page'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		// Call enqueue styles and css
		delete_transient( '_wc_activation_redirect' );

		ob_start();
		$this->setup_wizard_html();
		exit;
	}

	public function setup_wizard_html() {
		set_current_screen();
		?>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width"/>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<title><?php esc_html_e( 'Stripe Payment Gateway for WooCommerce - Onboarding', 'funnelkit-stripe-woo-payment-gateway' ); ?></title>
			<?php
			// Print admin scripts
			do_action( 'admin_print_styles' );
			?>
			<?php // Print Admin Head
			do_action( 'admin_head' );
			?>
		</head>
		<body class="fkwcs-setup wp-core-ui">
		<div id="bwfsg-page" class="bwfsg-page"></div>
		</body>
		<?php
		// Print JS Scripts in enqueue styles
		wp_print_scripts( [ 'bwfsg-app' ] );
		?>
		</html>
		<?php
	}

	public function show_onboarding_notice() {
		$screen          = get_current_screen();
		$screen_id       = $screen ? $screen->id : '';
		$allowed_screens = [
			'woocommerce_page_wc-settings',
			'dashboard',
			'plugins',
		];

		if ( ! in_array( $screen_id, $allowed_screens, true ) || $this->admin_controller->is_stripe_connected() ) {
			return;
		}

		$status = get_option( 'fkwcs_setup_status', false );
		if ( false === $status ) {
			$onboarding_url = admin_url( 'index.php?page=fkwcs-onboarding' );
			?>
			<div class="notice notice-info wcf-notice">
				<p><b><?php esc_html_e( 'Thanks for installing Stripe Payment Gateway for WooCommerce!', 'funnelkit-stripe-woo-payment-gateway' ); ?></b></p>
				<p><?php esc_html_e( 'Go through a quick set up to configure Stripe', 'funnelkit-stripe-woo-payment-gateway' ); ?></p>
				<p>
					<a href="<?php echo esc_url( $onboarding_url ); ?>" class="button button-primary"> <?php esc_html_e( 'Start Onboarding Wizard', 'funnelkit-stripe-woo-payment-gateway' ); ?></a>
					<a class="button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'fkwcs-hide-notice', 'install' ), 'fkwcs_hide_notices_nonce', '_fkwcs_notice_nonce' ) ); ?>"><?php esc_html_e( 'Skip Setup', 'funnelkit-stripe-woo-payment-gateway' ); ?></a>
				</p>
			</div>
			<?php
		}
	}

	public function hide_notices() {
		if ( ! isset( $_GET['fkwcs-hide-notice'] ) ) {
			return;
		}

		$fkwcs_hide_notice   = filter_input( INPUT_GET, 'fkwcs-hide-notice', FILTER_SANITIZE_STRING );
		$_fkwcs_notice_nonce = filter_input( INPUT_GET, '_fkwcs_notice_nonce', FILTER_SANITIZE_STRING );

		if ( $fkwcs_hide_notice && $_fkwcs_notice_nonce && wp_verify_nonce( sanitize_text_field( wp_unslash( $_fkwcs_notice_nonce ) ), 'fkwcs_hide_notices_nonce' ) ) {
			$this->update_connect_with_stripe_status( 'skipped' );
		}
	}

	public function update_connect_with_stripe_status( $status = 'success' ) {
		update_option( 'fkwcs_setup_status', $status );
	}

	public function localize_vars() {
		$redirect_url       = admin_url( 'index.php?page=fkwcs-onboarding' );
		$available_gateways = $this->available_gateways();

		return [
			'ajax_url'                         => admin_url( 'admin-ajax.php' ),
			'base_url'                         => $redirect_url,
			'assets_url'                       => FKWCS_URL . 'wizard/',
			'authorization_url'                => $this->admin_controller->get_connect_url(),
			'settings_url'                     => admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' ),
			'gateways_url'                     => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fkwcs_stripe' ),
			'manual_connect_url'               => admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings&connect=manually' ),
			'available_gateways'               => $available_gateways,
			'woocommerce_setup_url'            => admin_url( 'plugin-install.php?s=woocommerce&tab=search' ),
			'fkwcs_onboarding_enable_webhooks' => wp_create_nonce( 'fkwcs_onboarding_enable_webhooks' ),
			'woocommerce_installed'            => $this->is_woocommerce_installed(),
			'woocommerce_activated'            => class_exists( 'woocommerce' ),
			'navigator_base'                   => '/wp-admin/index.php?page=fkwcs-onboarding',
			'onboarding_base'                  => admin_url( 'index.php?page=fkwcs-onboarding' ),
			'get_payment_mode'                 => $this->get_payment_mode(),
			'get_webhook_secret'               => $this->get_webhook_secret(),
			'webhook_url'                      => Helper::get_webhook_url(),
		];

	}

	public function redirect_to_onboarding() {
		// Return to Onboarding
		return admin_url( 'index.php?page=fkwcs-onboarding' );
	}

	public function available_gateways() {
		if ( empty( $_GET['fkwcs_call'] ) || 'success' !== $_GET['fkwcs_call'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$gateways = WC()->payment_gateways->payment_gateways();
		if ( empty( $gateways ) ) {
			return false;
		}

		$available_gateways = [
			[
				'id'          => 'fkwcs_stripe',
				'name'        => 'Stripe Card Processing',
				'icon'        => FKWCS_URL . 'assets/icon/credit-card.svg',
				'recommended' => true,
				'currencies'  => 'all',
				'enabled'     => true,
			],
		];

		$currency = get_woocommerce_currency();
		foreach ( $gateways as $id => $class ) {
			if ( 0 === strpos( $id, 'fkwcs_' ) && method_exists( $class, 'get_supported_currency' ) && in_array( $currency, $class->get_supported_currency(), true ) ) {
				$temp                 = [];
				$icon                 = str_replace( 'fkwcs_', '', $id );
				$temp['id']           = $id;
				$temp['name']         = $class->method_title;
				$temp['icon']         = FKWCS_URL . 'assets/icon/' . $icon . '.svg';
				$temp['recommended']  = false;
				$temp['currencies']   = implode( ', ', $class->get_supported_currency() );
				$temp['enabled']      = false;
				$available_gateways[] = $temp;
			}
		}

		return $available_gateways;
	}

	public function is_woocommerce_installed() {
		$plugins = get_plugins();
		if ( isset( $plugins[ $this->woocommerce_slug ] ) ) {
			return true;
		}

		return false;
	}

	public function get_payment_mode() {
		return apply_filters( 'fkwcs_payment_mode', get_option( 'fkwcs_mode' ) );
	}

	public function get_webhook_secret() {
		$fkwcs_mode = $this->get_payment_mode();

		if ( 'live' === $fkwcs_mode ) {
			return apply_filters( 'fkwcs_webhook_secret', get_option( 'fkwcs_live_webhook_secret' ) );
		} else {
			return apply_filters( 'fkwcs_webhook_secret', get_option( 'fkwcs_test_webhook_secret' ) );
		}

	}

	public function register_routes() {

		// REST Route to list Payment gateways.
		register_rest_route( $this->namespace, '/list-gateways', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_payment_gateways' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_enabled_gateways' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			)
		) );

		// REST Route for Webhook modal.
		register_rest_route( $this->namespace, '/webhook-modal', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_webhook_page' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_webhook_page' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			)
		) );

		// REST Route for Enable Webhook Auto Connect.
		register_rest_route( $this->namespace, '/enable-webhook', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'enable_webhook' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			)
		) );

		// REST Route for Enable Express Checkout Auto Connect.
		register_rest_route( $this->namespace, '/enable-checkout', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_enable_checkout' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			)
		) );

		// REST Route for Stripe Failure Page.
		register_rest_route( $this->namespace, '/error-stripe', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'stripe_failure' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			)
		) );


		// REST Route to save manual connect.
		register_rest_route( $this->namespace, '/connect-manual', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_connect_manual' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			)
		) );

		// REST Route save API mode.
		register_rest_route( $this->namespace, '/save-mode', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_pay_mode' ),
				'permission_callback' => array( $this, 'get_api_permission_check' ),
			)
		) );

	}

	// API Callback permission check
	public function get_api_permission_check() {
		return current_user_can( 'manage_options' );
	}

	// Get List of fkwcs Gateways.
	public function get_payment_gateways() {
		$resp                     = [];
		$resp['status']           = false;
		$resp['msg']              = __( 'Error fetching gateways', 'funnelkit-stripe-woo-payment-gateway' );
		$resp['data']['gateways'] = new \stdClass();

		$installed_payment_methods = WC()->payment_gateways()->payment_gateways();
		$fkwcs_gateways            = array();

		$recommendations      = array( 'fkwcs_stripe' );
		$recommended_gateways = apply_filters( 'fkwcs_recommended_payment_gateways', $recommendations );
		$currency             = get_woocommerce_currency();

		foreach ( $installed_payment_methods as $method ) {
			$gateway_id = $method->id;
			if ( 0 === strpos( $gateway_id, 'fkwcs_' ) && ( 'fkwcs_stripe' === $gateway_id || ( method_exists( $method, 'get_supported_currency' ) && in_array( $currency, $method->get_supported_currency(), true ) ) ) ) {
				$icon                      = str_replace( 'fkwcs_stripe_', '', $gateway_id );
				$gateway                   = [];
				$gateway['id']             = $gateway_id;
				$gateway['enabled']        = wc_string_to_bool( $method->enabled );
				$gateway['title']          = $method->title;
				$gateway['description']    = ! empty( $method->subtitle ) ? $method->subtitle : $method->description;
				$gateway['is_recommended'] = in_array( $gateway_id, $recommended_gateways, true ) ? true : false;
				$gateway['icon']           = FKWCS_URL . "assets/icons/$icon.svg";

				$fkwcs_gateways[] = $gateway;
			}
		}

		if ( count( $fkwcs_gateways ) ) {
			$resp['status']           = true;
			$resp['msg']              = __( 'Gateways rendered', 'funnelkit-stripe-woo-payment-gateway' );
			$resp['data']['gateways'] = $fkwcs_gateways;
		}

		return rest_ensure_response( $resp );
	}

	// Function to Save Payment Gateways
	public function save_enabled_gateways( WP_REST_Request $request ) {
		$resp                     = [];
		$resp['status']           = false;
		$resp['msg']              = __( 'Error enabling gateways', 'funnelkit-stripe-woo-payment-gateway' );
		$resp['data']['gateways'] = new \stdClass();
		$response                 = array();

		$options = $request->get_body();
		if ( ! empty( $options ) ) {
			$posted_data = $this->sanitize_custom( $options );

			if ( is_array( $posted_data ) && ! empty( $posted_data['gateways'] ) ) {
				$gateway_status = $posted_data['gateways'];
				$gateways       = WC()->payment_gateways->payment_gateways();
				if ( count( $gateway_status ) ) {
					foreach ( $gateway_status as $id => $gateway ) {
						$id     = $gateway['id'];
						$status = wc_string_to_bool( $gateway['enabled'] );
						$id     = sanitize_text_field( $id );

						if ( true === $status && isset( $gateways[ $id ] ) ) {

							if ( ( 'yes' !== $gateways[ $id ]->enabled && $gateways[ $id ]->update_option( 'enabled', 'yes' ) ) || 'yes' === $gateways[ $id ]->enabled ) {
								$response[ $id ] = true;
							} else {
								$response[ $id ] = false;
							}


						}
					}
				}
			}
		}


		if ( count( $response ) ) {
			$resp['status']           = true;
			$resp['msg']              = __( 'Gateways saved', 'funnelkit-stripe-woo-payment-gateway' );
			$resp['data']['gateways'] = $response;
		}
		do_action( 'fkwcs_wizard_gateways_save', $response );

		return rest_ensure_response( $resp );
	}

	// Function to Get Webhook Page.
	public function get_webhook_page() {
		$resp           = [];
		$resp['status'] = true;
		$resp['msg']    = __( 'Webhook URL rendered', 'funnelkit-stripe-woo-payment-gateway' );

		$localize_vars               = $this->localize_vars();
		$resp['data']['webhook_url'] = $localize_vars['webhook_url'];

		return rest_ensure_response( $resp );
	}

	// Function to Save Create Webhook Dynamically.
	public function enable_webhook( WP_REST_Request $request ) {
		$resp           = [];
		$resp['status'] = false;
		$resp['msg']    = __( 'Error saving', 'funnelkit-stripe-woo-payment-gateway' );
		$response       = [];
		$options        = $request->get_body();

		if ( ! empty( $options ) ) {
			$posted_data = $this->sanitize_custom( $options );

			if ( is_array( $posted_data ) && ! empty( $posted_data['webhook'] ) ) {
				foreach ( array( 'test', 'live' ) as $k ) {
					if ( 'test' === $k ) {
						$secret_key = get_option( 'fkwcs_' . $k . '_secret_key' ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					} else {
						$secret_key = get_option( 'fkwcs_secret_key' ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					}

					$params         = [ 'secret_key' => $secret_key ];
					$response[ $k ] = $this->admin_controller->create_webhook( $params );

					if ( is_wp_error( $response[ $k ] ) ) {
						$resp['msg'] = $response[ $k ]->get_error_message();
						Helper::log( sprintf( 'Error creating Stripe webhook. Mode: %1$s. Reason: %2$s', 'test', $response->get_error_message() ) );
					} else {
						if ( is_array( $response[ $k ] ) && $response[ $k ]['status'] === false ) {
							$resp['status'] = false;
							$resp['msg']    = $response[ $k ]['msg'];

							return rest_ensure_response( $resp );
						} else {
							update_option( 'fkwcs_' . $k . '_webhook_secret', $response[ $k ]['secret'] );
							update_option( 'fkwcs_' . $k . '_created_webhook', array( 'secret' => $response[ $k ]['secret'], 'id' => $response[ $k ]['id'] ) );
							$resp['status'] = true;
							$resp['msg']    = __( 'Webhook Created Successfully', 'funnelkit-stripe-woo-payment-gateway' );
						}
					}
				}
			}
		}

		return rest_ensure_response( $resp );
	}

	// Function to Save Webhook details manually.
	public function save_webhook_page( WP_REST_Request $request ) {
		$resp           = [];
		$resp['status'] = false;
		$resp['msg']    = __( 'Error saving', 'funnelkit-stripe-woo-payment-gateway' );

		$options = $request->get_body();

		if ( ! empty( $options ) ) {
			$posted_data = $this->sanitize_custom( $options );

			if ( is_array( $posted_data ) && ! empty( $posted_data['webhook'] ) ) {
				$fkwcs_mode = $posted_data['webhook']['mode'];
				$secret     = $posted_data['webhook']['secret'];

				if ( ! empty( $fkwcs_mode ) && in_array( $fkwcs_mode, [ 'test', 'live' ], true ) ) {

					if ( 'live' === $fkwcs_mode ) {
						update_option( 'fkwcs_live_webhook_secret', $secret );
					}
					if ( 'test' === $fkwcs_mode ) {
						update_option( 'fkwcs_test_webhook_secret', $secret );
					}

					$resp['status'] = true;
					$resp['msg']    = __( 'Webhook saved', 'funnelkit-stripe-woo-payment-gateway' );
				}
			}
		}

		return rest_ensure_response( $resp );
	}

	// Function to Save Express Checkout Settings.
	public function save_enable_checkout( WP_REST_Request $request ) {
		$resp           = [];
		$resp['status'] = false;
		$resp['msg']    = __( 'Error saving', 'funnelkit-stripe-woo-payment-gateway' );

		$options = $request->get_body();
		if ( ! empty( $options ) ) {
			$posted_data = $this->sanitize_custom( $options );

			if ( is_array( $posted_data ) && ! empty( $posted_data['webhook'] ) ) {
				$settings_data                                           = get_option( 'woocommerce_fkwcs_stripe_settings' );
				$settings_data['express_checkout_enabled']               = 'yes';
				$settings_data['express_checkout_product_page_position'] = 'below';

				update_option( 'woocommerce_fkwcs_stripe_settings', $settings_data );
				$this->admin_controller->maybe_verify_domain();
				$resp['status'] = true;
				$resp['msg']    = __( 'Checkout settings saved', 'funnelkit-stripe-woo-payment-gateway' );
			}
		}

		return rest_ensure_response( $resp );
	}

	// Function to return Stripe Connect URL
	public function stripe_failure() {
		$resp                            = [];
		$resp['status']                  = true;
		$resp['msg']                     = __( 'Stripe Connect URL', 'funnelkit-stripe-woo-payment-gateway' );
		$resp['data']['connect_url']     = Admin::get_instance()->get_connect_url();
		$resp['data']['admin_dashboard'] = admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' );

		return rest_ensure_response( $resp );
	}


	// Function to Save Payment Gateways.
	public function save_connect_manual( WP_REST_Request $request ) {
		$resp           = [];
		$resp['status'] = false;
		$resp['msg']    = __( 'Error saving', 'funnelkit-stripe-woo-payment-gateway' );

		$options = $request->get_body();
		if ( ! empty( $options ) ) {
			$posted_data = $this->sanitize_custom( $options );
			if ( is_array( $posted_data ) && ! empty( $posted_data['webhook'] ) ) {
				$fkwcs_mode = sanitize_text_field( $posted_data['webhook']['mode'] );
				$secret_key = sanitize_text_field( $posted_data['webhook']['secret_key'] );
				$pub_key    = sanitize_text_field( $posted_data['webhook']['pub_key'] );

				update_option( 'fkwcs_mode', $fkwcs_mode );

				if ( 'live' === $fkwcs_mode ) {
					update_option( 'fkwcs_secret_key', $secret_key );
					update_option( 'fkwcs_pub_key', $pub_key );
				} else {
					update_option( 'fkwcs_test_secret_key', $secret_key );
					update_option( 'fkwcs_test_pub_key', $pub_key );
				}

				$resp['status'] = true;
				$resp['msg']    = __( 'Stripe Details Saved', 'funnelkit-stripe-woo-payment-gateway' );
			}
		}

		return rest_ensure_response( $resp );
	}

	// Function to Save Payment Gateways.
	public function save_pay_mode( WP_REST_Request $request ) {
		$resp           = [];
		$resp['status'] = false;
		$resp['msg']    = __( 'Error saving', 'funnelkit-stripe-woo-payment-gateway' );

		$options = $request->get_body();
		if ( ! empty( $options ) ) {
			$posted_data = $this->sanitize_custom( $options );
			if ( is_array( $posted_data ) && ! empty( $posted_data['webhook'] ) ) {
				$fkwcs_mode = sanitize_text_field( $posted_data['webhook']['mode'] );
				update_option( 'fkwcs_mode', $fkwcs_mode );

				$resp['status'] = true;
				$resp['msg']    = __( 'Plugin mode saved', 'funnelkit-stripe-woo-payment-gateway' );
			}
		}

		return rest_ensure_response( $resp );
	}

	// Function to extract json to array and sanitize data.
	public function sanitize_custom( $data, $skip_clean = 0 ) {
		$data = json_decode( $data, true );
		if ( 0 === $skip_clean ) {
			return wc_clean( $data );
		}

		return $data;
	}

	// Load React APP
	public function load_react_app() {
		if ( ! empty( $_GET['page'] ) && 'fkwcs-onboarding' === $_GET['page'] ) { // phpcs:ignore
			$app_name    = 'bwfsg-app';
			$assets_dir  = defined( 'FKWCS_REACT_DEV_URL' ) ? FKWCS_REACT_DEV_URL : plugin_dir_url( __FILE__ ) . 'app/dist';
			$assets_path = plugin_dir_path( __FILE__ ) . "app/dist/$app_name.asset.php";
			$assets      = file_exists( $assets_path ) ? include $assets_path : array(   //phpcs:ignore
				'dependencies' => array(
					'wp-element',
					'wp-i18n',
					'wp-api-request',
					'wp-components',
					'wp-blocks',
					'wp-editor',
					'wp-compose'
				),
				'version'      => FKWCS_VERSION,
			); //phpcs:ignore

			$js_path    = "/$app_name.js";
			$style_path = "/$app_name.css";
			$deps       = ( isset( $assets['dependencies'] ) ? array_merge( $assets['dependencies'], array( 'jquery' ) ) : array( 'jquery' ) );
			$version    = $assets['version'];

			$script_deps = array_filter( $deps, function ( $dep ) {
				return false === strpos( $dep, 'css' );
			} );

			wp_enqueue_script( $app_name, $assets_dir . $js_path, $script_deps, $version, true );

			$localized_data = [
				'app_data'      => plugin_dir_url( __FILE__ ) . 'app/dist/',
				'images_dir'    => plugin_dir_url( __FILE__ ) . 'assets/images/',
				'settings_link' => admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' ),
				'connect_link'  => $this->admin_controller->get_connect_url(),
				'webhook_url'   => Helper::get_webhook_url(),
			];

			wp_localize_script( $app_name, 'bwfsg', $localized_data );

			wp_enqueue_style( $app_name, $assets_dir . $style_path, array( 'wp-components' ), $version );

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( "$app_name-i18n", 'funnelkit-stripe-woo-payment-gateway' );
			}
		}
	}
}
