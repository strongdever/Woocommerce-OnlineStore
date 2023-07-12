<?php
/**
 * Stripe Gateway
 *
 * @package funnelkit-stripe-woo-payment-gateway
 */

namespace FKWCS\Gateway\Stripe;

use Exception;
use WC_Data_Store;
use WC_Subscriptions_Product;
use WC_Validation;

/**
 * Payment Request Api.
 */
class SmartButtons extends CreditCard {
	private static $instance = null;

	public $local_settings = [];

	public $smart_button_single_product_hook = '';
	public $button_type = '';

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->local_settings   = Helper::get_gateway_settings();
		$this->express_checkout = $this->local_settings['express_checkout_enabled'];

		if ( 'yes' !== $this->express_checkout || 'yes' !== $this->local_settings['enabled'] ) {
			return;
		}

		add_filter( 'fkwcs_localized_data', array( $this, 'add_js_params' ) );
		$this->set_api_keys();
		$this->capture_method = 'automatic';

		$product_page_action   = 'woocommerce_after_add_to_cart_quantity';
		$product_page_priority = 10;

		$settings = $this->local_settings;

		if ( isset( $settings['express_checkout_product_page_position'] ) && ( 'below' === $settings['express_checkout_product_page_position'] || 'inline' === $settings['express_checkout_product_page_position'] ) ) {
			$product_page_action   = 'woocommerce_after_add_to_cart_button';
			$product_page_priority = 1;
		}
		if ( $settings['express_checkout_checkout_page_position'] === 'above-checkout' ) {
			$checkout_page_hook = 'woocommerce_checkout_before_customer_details';
		} else {
			$checkout_page_hook = 'woocommerce_checkout_billing';
		}

		$single_product_hook    = apply_filters( 'fkwcs_express_button_single_product_position', $product_page_action, $settings );
		$cart_page_hook         = apply_filters( 'fkwcs_express_button_cart_position', 'woocommerce_proceed_to_checkout' );
		$checkout_page_hook     = apply_filters( 'fkwcs_express_button_checkout_position', $checkout_page_hook );
		$product_page_priority  = apply_filters( 'fkwcs_express_button_single_product_position_priority', $product_page_priority );
		$checkout_page_priority = apply_filters( 'fkwcs_express_button_checkout_position_priority', 5 );
		$cart_page_priority     = apply_filters( 'fkwcs_express_button_cart_position_priority', 1 );

		/**
		 * hook in correct actions
		 */
		add_action( $single_product_hook, [ $this, 'payment_request_button' ], $product_page_priority );
		add_action( $cart_page_hook, [ $this, 'payment_request_button' ], $cart_page_priority );
		add_action( $checkout_page_hook, [ $this, 'payment_request_button' ], $checkout_page_priority );

		add_filter( 'fkwcs_payment_request_localization', [ $this, 'localize_product_data' ] );
		add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'merge_cart_details' ], 1000 );
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'merge_cart_details' ], 1000 );
		add_filter( 'fkcart_fragments', [ $this, 'merge_cart_details' ], 1000 );

		add_action( 'wp_enqueue_scripts', [ $this, 'register_stripe_js' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_stripe_js' ], 11 );
		$this->ajax_endpoints();


	}

	/**
	 * Ajax callbacks declared
	 *
	 * @return void
	 */
	public function ajax_endpoints() {
		add_action( 'wc_ajax_fkwcs_button_payment_request', [ $this, 'process_smart_checkout' ] );
		add_action( 'wc_ajax_wc_stripe_create_order', [ $this, 'process_smart_checkout' ],-1 );
		add_action( 'wc_ajax_fkwcs_get_cart_details', [ $this, 'ajax_get_cart_details' ] );
		add_action( 'wc_ajax_fkwcs_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
		add_action( 'wc_ajax_fkwcs_selected_product_data', [ $this, 'ajax_selected_product_data' ] );
		add_action( 'wc_ajax_fkwcs_update_shipping_address', [ $this, 'update_shipping_address' ] );
		add_action( 'wc_ajax_fkwcs_update_shipping_option', [ $this, 'update_shipping_option' ] );
		add_action( 'wc_ajax_fkwcs_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
	}

	/**
	 * Register Stripe assets
	 *
	 * @return void
	 */
	public function register_stripe_js() {
		parent::register_stripe_js();
		wp_register_script( 'fkwcs-express-checkout-js', FKWCS_URL . 'assets/js/express-checkout' . Helper::is_min_suffix() . '.js', [ 'fkwcs-stripe-external' ], FKWCS_VERSION, true );
		wp_register_style( 'fkwcs-style', FKWCS_URL . 'assets/css/style.css', [], FKWCS_VERSION );
	}

	/**
	 * Enqueue Stripe assets
	 *
	 * @return void
	 */
	public function enqueue_stripe_js() {
		if ( ! $this->is_selected_location() ) {
			return;
		}

		wp_enqueue_style( 'fkwcs-style' );
		wp_enqueue_script( 'fkwcs-stripe-external' );
		wp_enqueue_script( 'fkwcs-express-checkout-js' );

		if ( 0 === did_action( 'fkwcs_core_element_js_enqueued' ) ) {
			wp_localize_script( 'fkwcs-express-checkout-js', 'fkwcs_data', $this->localize_data() );
		}
	}

	/**
	 * Localize important data
	 *
	 * @param $localize_data
	 *
	 * @return array
	 */
	public function add_js_params( $localize_data ) {
		$currency = get_woocommerce_currency();

		$localize_data_added = [
			'is_product'        => $this->is_product() ? 'yes' : 'no',
			'is_cart'           => $this->is_cart() ? 'yes' : 'no',
			'wc_endpoints'      => self::get_public_endpoints(),
			'currency'          => strtolower( $currency ),
			'country_code'      => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			'shipping_required' => WC()->cart->needs_shipping(),
			'icons'             => [
				'applepay_gray'  => FKWCS_URL . 'assets/icons/apple_pay_gray.svg',
				'applepay_light' => FKWCS_URL . 'assets/icons/apple_pay_light.svg',
				'gpay_light'     => FKWCS_URL . 'assets/icons/gpay_light.svg',
				'gpay_gray'      => FKWCS_URL . 'assets/icons/gpay_gray.svg',
			],

			'debug_log' => ! empty( get_option( 'fkwcs_debug_log' ) ) ? get_option( 'fkwcs_debug_log' ) : 'no',
			'debug_msg' => __( 'Stripe enabled Payment Request is not available in this browser', 'funnelkit-stripe-woo-payment-gateway' ),
		];

		if ( $this->is_product() ) {
			$localize_data_added['single_product'] = $this->get_product_data();
		}

		if ( $this->is_cart() ) {
			$localize_data_added['cart_data'] = $this->ajax_get_cart_details();
		};

		$localize_data_added['style'] = [
			'theme'                 => ( ! empty( $this->local_settings['express_checkout_button_theme'] ) ? $this->local_settings['express_checkout_button_theme'] : '' ),
			'button_position'       => ( ! empty( $this->local_settings['express_checkout_product_page_position'] ) ? $this->local_settings['express_checkout_product_page_position'] : '' ),
			'checkout_button_width' => ( ! empty( $this->local_settings['express_checkout_button_width'] ) ? $this->local_settings['express_checkout_button_width'] : '' ),
			'button_length'         => strlen( $this->local_settings['express_checkout_button_text'] ),
		];

		return array_merge( $localize_data, $localize_data_added );
	}

	/**
	 * Checks if current location is chosen to display express checkout button
	 *
	 * @return boolean
	 */
	private function is_selected_location() {
		$location = $this->local_settings['express_checkout_location'];
		if ( is_array( $location ) && ! empty( $location ) ) {
			if ( $this->is_product() && in_array( 'product', $location, true ) ) {
				return true;
			}

			if ( is_cart() && in_array( 'cart', $location, true ) ) {
				return true;
			}

			if ( is_checkout() && in_array( 'checkout', $location, true ) ) {
				return true;
			}
		}


		return apply_filters( 'fkwcs_express_button_selected_location', false, $this );
	}

	/**
	 * Creates container for payment request button
	 *
	 * @return void
	 */
	public function payment_request_button() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $gateways['fkwcs_stripe'] ) ) {
			return;
		}

		if ( ! $this->is_selected_location() ) {
			return;
		}

		if ( 'yes' !== $this->express_checkout ) {
			return;
		}

		$options             = $this->local_settings;
		$separator_below     = true;
		$position_class      = 'above';
		$alignment_class     = '';
		$button_width        = '';
		$container_max_width = '';
		$button_max_width    = '';

		$extra_class = 'fkwcs_smart_checkout_button';

		$sec_classes = [ 'fkwcs_stripe_smart_button_wrapper' ];

		if ( $this->is_product() ) {
			$extra_class   = 'fkwcs_smart_product_button';
			$sec_classes[] = 'fkwcs-product';

			if ( 'below' === $options['express_checkout_product_page_position'] ) {
				$separator_below = false;
				$sec_classes[]   = 'below';
			}

			if ( 'inline' === $options['express_checkout_product_page_position'] ) {
				$separator_below = false;
				$sec_classes[]   = 'inline';
			}
		} elseif ( $this->is_cart() ) {
			$extra_class   = 'fkwcs_smart_cart_button';
			$sec_classes[] = 'cart';
		}

		if ( $this->is_checkout() ) {
			$sec_classes[]   = 'checkout';
			$alignment_class = $options['express_checkout_button_alignment'];
			if ( ! empty( $options['express_checkout_button_width'] && absint( $options['express_checkout_button_width'] ) > 0 ) ) {
				$button_width = 'min-width:' . (int) $options['express_checkout_button_width'] . 'px';

				if ( (int) $options['express_checkout_button_width'] > 500 ) {
					$button_width = 'max-width:' . (int) $options['express_checkout_button_width'] . 'px;';
				}
			} else {
				$button_width = 'width: 100%';
			}
		}

		$button_theme = ! empty( $this->local_settings['express_checkout_button_theme'] ) ? wc_clean( $this->local_settings['express_checkout_button_theme'] ) : 'dark';
		$button_theme = "fkwcs_ec_payment_button-" . $button_theme;
		$only_buttons = apply_filters( 'flwcs_express_buttons_is_only_buttons', false );

		$container_class = '';
		?>
		<div id="fkwcs_stripe_smart_button_wrapper" class="<?php echo esc_attr( implode( ' ', $sec_classes ) ); ?>">
			<div id="fkwcs_stripe_smart_button" class="<?php echo esc_attr( $container_class . ' ' . $position_class ); ?>" style="<?php esc_attr_e( ! empty( $alignment_class ) ? "text-align:" . ( $alignment_class ) . ';' : '' ); ?><?php echo esc_attr( $container_max_width ); ?>">
				<?php
				if ( ! $separator_below && false === $only_buttons ) {
					$this->payment_request_button_separator();
				}

				if ( $this->is_checkout() && false === $only_buttons ) {
					echo "<fieldset id='fkwcs-expresscheckout-fieldset'>";
					if ( ! empty( trim( $options['express_checkout_title'] ) ) ) {
						?>
						<legend><?php echo esc_html( $options['express_checkout_title'] ); ?></legend>
						<?php
					}
				}
				?>
				<div id="fkwcs_custom_express_button">
					<button type="button" class="fkwcs_smart_buttons <?php echo esc_attr( $extra_class . ' ' . $button_theme ) ?>" style="<?php echo esc_attr( $button_width . $button_max_width ); ?>">
                        <span class="fkwcs_express_checkout_button_content">
							<?php echo esc_html( $options['express_checkout_button_text'] ); ?>
                            <img alt="" style="display:none" src="" class="fkwcs_express_checkout_button_icon skip-lazy">
                        </span>
					</button>
				</div>
				<?php
				if ( $this->is_checkout() && false === $only_buttons ) {
					echo "</fieldset>";
				}

				if ( $separator_below && false === $only_buttons ) {
					$this->payment_request_button_separator();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Creates separator for payment request button
	 *
	 * @return void
	 */
	public function payment_request_button_separator() {
		if ( 'yes' !== $this->express_checkout ) {
			return;
		}

		$container_class = '';
		if ( $this->is_product() ) {
			$container_class = 'fkwcs-product';
		} elseif ( is_checkout() ) {
			$container_class = 'checkout';
		} elseif ( is_cart() ) {
			$container_class = 'cart';
		}

		$options           = $this->local_settings;
		$separator_text    = $options['express_checkout_separator_product'];
		$display_separator = true;

		if ( 'checkout' === $container_class ) {
			if ( ! empty( $options['express_checkout_separator_checkout'] ) ) {
				$separator_text = $options['express_checkout_separator_checkout'];
			}
		}

		if ( 'cart' === $container_class && ! empty( $options['express_checkout_separator_cart'] ) ) {
			$separator_text = $options['express_checkout_separator_cart'];
		}

		if ( 'fkwcs-product' === $container_class && 'inline' === $options['express_checkout_product_page_position'] ) {
			$display_separator = false;
		}

		if ( ! empty( $separator_text ) && $display_separator ) {
			?>
			<div id="fkwcs-payment-request-separator" class="<?php echo esc_attr( $container_class ); ?>">
				<label><?php echo esc_html( $separator_text ); ?></label>
			</div>
			<?php
		}
	}

	/**
	 * Process checkout on payment request button click
	 *
	 * @return void
	 * @throws Exception
	 */
	public function process_smart_checkout() {
		check_ajax_referer( 'fkwcs_nonce', 'fkwcs_nonce' );

		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'funnelkit-stripe-woo-payment-gateway' ) );
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		/** Setting the checkout nonce to avoid exception */
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['_wpnonce']    = ! empty( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( $_REQUEST['_wpnonce'] ) : '';

		Helper::log( 'Payment request call received for ' . wc_clean( $_REQUEST['payment_request_type'] ) . ' from page -' . wc_clean( $_REQUEST['page_from'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		$this->button_type = ! empty( $_REQUEST['payment_request_type'] ) ? wc_clean( $_REQUEST['payment_request_type'] ) : '';
		add_filter( 'woocommerce_cart_needs_payment', '__return_true', PHP_INT_MAX );
		WC()->checkout()->process_checkout();
		exit();
	}

	/**
	 * Gets product data either form product page or page where shortcode is used
	 *
	 * @return object|false
	 */
	public function get_product() {
		global $post;
		if ( is_product() ) {
			return wc_get_product( $post->ID );
		} elseif ( wc_post_content_has_shortcode( 'product_page' ) ) {
			/** Get id from product_page shortcode */
			preg_match( '/\[product_page id="(?<id>\d+)"\]/', $post->post_content, $shortcode_match );

			if ( ! isset( $shortcode_match['id'] ) ) {
				return false;
			}

			return wc_get_product( $shortcode_match['id'] );
		}

		return false;
	}

	/**
	 * Get price of selected product
	 *
	 * @param object $product Selected product data.
	 *
	 * @return string
	 */
	public function get_product_price( $product ) {
		$product_price = $product->get_price();
		/** Add subscription sign-up fees to product price */
		if ( 'subscription' === $product->get_type() && class_exists( 'WC_Subscriptions_Product' ) ) {
			$product_price = $product->get_price() + WC_Subscriptions_Product::get_sign_up_fee( $product );
		}

		return $product_price;
	}

	/**
	 * Get data of selected product
	 *
	 * @return false|mixed|null
	 * @throws Exception
	 */
	public function get_product_data() {
		if ( ! $this->is_product() ) {
			return false;
		}

		$product = $this->get_product();
		if ( 'variable' === $product->get_type() ) {
			$variation_attributes = $product->get_variation_attributes();
			$attributes           = [];

			foreach ( $variation_attributes as $attribute_name => $attribute_values ) {
				$attribute_key = 'attribute_' . sanitize_title( $attribute_name );

				$attributes[ $attribute_key ] = isset( $_GET[ $attribute_key ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					? wc_clean( wp_unslash( $_GET[ $attribute_key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					: $product->get_variation_default_attribute( $attribute_name );
			}

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		}

		$data  = [];
		$items = [];

		if ( 'subscription' === $product->get_type() && class_exists( 'WC_Subscriptions_Product' ) ) {
			$items[] = [
				'label'  => $product->get_name(),
				'amount' => $this->get_formatted_amount( $product->get_price() ),
			];

			$items[] = [
				'label'  => __( 'Sign up Fee', 'funnelkit-stripe-woo-payment-gateway' ),
				'amount' => $this->get_formatted_amount( WC_Subscriptions_Product::get_sign_up_fee( $product ) ),
			];
		} else {
			$items[] = [
				'label'  => $product->get_name(),
				'amount' => $this->get_formatted_amount( $this->get_product_price( $product ) ),
			];
		}

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'   => __( 'Tax', 'funnelkit-stripe-woo-payment-gateway' ),
				'amount'  => 0,
				'pending' => true,
			];
		}

		if ( wc_shipping_enabled() && $product->needs_shipping() ) {
			$items[] = [
				'label'   => __( 'Shipping', 'funnelkit-stripe-woo-payment-gateway' ),
				'amount'  => 0,
				'pending' => true,
			];

			$data['shippingOptions'] = [
				'id'     => 'pending',
				'label'  => __( 'Pending', 'funnelkit-stripe-woo-payment-gateway' ),
				'detail' => '',
				'amount' => 0,
			];
		}

		$data['displayItems']    = $items;
		$data['total']           = [
			'label'   => apply_filters( 'fkwcs_payment_request_total_label', $this->clean_statement_descriptor( Helper::get_gateway_descriptor() ) ),
			'amount'  => $this->get_formatted_amount( $this->get_product_price( $product ) ),
			'pending' => true,
		];
		$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() && 0 !== wc_get_shipping_method_count( true ) );

		return apply_filters( 'fkwcs_payment_request_product_data', $data, $product );
	}

	/**
	 * Adds product data to localized data via filter
	 *
	 * @param $localized_data
	 *
	 * @return array|false[]|null[]
	 * @throws Exception
	 */
	public function localize_product_data( $localized_data ) {
		return array_merge( $localized_data, [ 'product' => $this->get_product_data() ] );
	}

	/**
	 * Format data to display in payment request cart form
	 *
	 * @param boolean $display_items show detailed view or not.
	 *
	 * @return array
	 */
	protected function build_display_items( $display_items = true ) {

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

		$items     = [];
		$lines     = [];
		$subtotal  = 0;
		$discounts = 0;

		foreach ( WC()->cart->get_cart() as $item ) {
			$subtotal       += $item['line_subtotal'];
			$amount         = $item['line_subtotal'];
			$quantity_label = 1 < $item['quantity'] ? ' (' . $item['quantity'] . ')' : '';
			$product_name   = $item['data']->get_name();

			$lines[] = [
				'label'  => $product_name . $quantity_label,
				'amount' => $this->get_formatted_amount( $amount ),
			];
		}

		if ( $display_items ) {
			$items = array_merge( $items, $lines );
		} else {
			/** Default show only subtotal instead of itemization */
			$items[] = [
				'label'  => 'Subtotal',
				'amount' => $this->get_formatted_amount( $subtotal ),
			];
		}

		$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );
		foreach ( $applied_coupons as $amount ) {
			$discounts += (float) $amount;
		}

		$discounts   = wc_format_decimal( $discounts, WC()->cart->dp );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, WC()->cart->dp );
		$order_total = WC()->cart->get_total( false );

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'  => esc_html( __( 'Tax', 'funnelkit-stripe-woo-payment-gateway' ) ),
				'amount' => $this->get_formatted_amount( $tax ),
			];
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = [
				'label'  => esc_html( __( 'Shipping', 'funnelkit-stripe-woo-payment-gateway' ) ),
				'amount' => $this->get_formatted_amount( $shipping ),
			];
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = [
				'label'  => esc_html( __( 'Discount', 'funnelkit-stripe-woo-payment-gateway' ) ),
				'amount' => $this->get_formatted_amount( $discounts ),
			];
		}

		$cart_fees = WC()->cart->get_fees();

		/** Include fees and taxes as display items */
		foreach ( $cart_fees as $fee ) {
			$items[] = [
				'label'  => $fee->name,
				'amount' => $this->get_formatted_amount( $fee->amount ),
			];
		}

		return [
			'displayItems' => $items,
			'total'        => [
				'label'   => apply_filters( 'fkwcs_payment_request_total_label', $this->clean_statement_descriptor( Helper::get_gateway_descriptor() ) ),
				'amount'  => max( 0, apply_filters( 'fkwcs_stripe_calculated_total', $this->get_formatted_amount( $order_total ), $order_total, WC()->cart ) ),
				'pending' => false,
			],
		];
	}

	/**
	 * Fetch cart details
	 *
	 * @return array
	 */
	public function ajax_get_cart_details() {
		if ( 'wc_ajax_fkwcs_get_cart_details' === current_action() ) {
			check_ajax_referer( 'fkwcs_nonce', 'fkwcs_nonce' );

		}
		WC()->cart->calculate_totals();
		$currency = get_woocommerce_currency();
		/** Set mandatory payment details */
		$data = [
			'shipping_required' => WC()->cart->needs_shipping(),
			'order_data'        => [
				'currency'     => strtolower( $currency ),
				'country_code' => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			],
		];

		$data['order_data'] += $this->build_display_items( true );

		if ( 'wc_ajax_fkwcs_get_cart_details' === current_action() ) {
			wp_send_json_success( $data );
		}

		return $data;
	}

	/**
	 * Updates cart on product variant change
	 *
	 * @return void
	 * @throws Exception
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'fkwcs_nonce', 'fkwcs_nonce' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id   = isset( $_POST['product_id'] ) ? absint( wc_clean( $_POST['product_id'] ) ) : 0;
		$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( wc_clean( $_POST['qty'] ) );
		$product      = wc_get_product( $product_id );
		$product_type = $product->get_type();

		/** First empty the cart to prevent wrong calculation */
		WC()->cart->empty_cart();

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		}

		if ( 'simple' === $product_type || 'subscription' === $product_type ) {
			WC()->cart->add_to_cart( $product->get_id(), $qty );
		}

		wp_send_json( [ 'result' => 'success', 'fkwcs_cart_details' => $this->ajax_get_cart_details() ] );
	}

	/**
	 * Updates data as per selected product variant
	 *
	 * @return void
	 * @throws Exception Error messages.
	 */
	public function ajax_selected_product_data() {
		check_ajax_referer( 'fkwcs_nonce', 'fkwcs_nonce' );

		try {
			$product_id   = isset( $_POST['product_id'] ) ? absint( wc_clean( $_POST['product_id'] ) ) : 0;
			$qty          = ! isset( $_POST['qty'] ) ? 1 : apply_filters( 'woocommerce_add_to_cart_quantity', absint( wc_clean( $_POST['qty'] ) ), $product_id );
			$addon_value  = isset( $_POST['addon_value'] ) ? max( floatval( wc_clean( $_POST['addon_value'] ) ), 0 ) : 0;
			$product      = wc_get_product( $product_id );
			$variation_id = null;

			if ( ! is_a( $product, 'WC_Product' ) ) {
				/* translators: %d is the product Id */
				throw new Exception( sprintf( __( 'Product with the ID (%d) cannot be found.', 'funnelkit-stripe-woo-payment-gateway' ), $product_id ) );
			}

			$product_type = $product->get_type();
			if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
				$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );
				}
			}

			if ( $product->is_sold_individually() ) {
				$qty = apply_filters( 'fkwcs_payment_request_add_to_cart_sold_individually_quantity', 1, $qty, $product_id, $variation_id );
			}

			if ( ! $product->has_enough_stock( $qty ) ) {
				/* translators: 1: product name 2: quantity in stock */
				throw new Exception( sprintf( __( 'You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'funnelkit-stripe-woo-payment-gateway' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ) );
			}

			$total = $qty * $this->get_product_price( $product ) + $addon_value;

			$quantity_label = 1 < $qty ? ' (' . $qty . ')' : '';

			$data  = [];
			$items = [];

			$items[] = [
				'label'  => $product->get_name() . $quantity_label,
				'amount' => $this->get_formatted_amount( $total ),
			];

			if ( wc_tax_enabled() ) {
				$items[] = [
					'label'   => __( 'Tax', 'funnelkit-stripe-woo-payment-gateway' ),
					'amount'  => 0,
					'pending' => true,
				];
			}

			if ( wc_shipping_enabled() && $product->needs_shipping() ) {
				$items[] = [
					'label'   => __( 'Shipping', 'funnelkit-stripe-woo-payment-gateway' ),
					'amount'  => 0,
					'pending' => true,
				];

				$data['shippingOptions'] = [
					'id'     => 'pending',
					'label'  => __( 'Pending', 'funnelkit-stripe-woo-payment-gateway' ),
					'detail' => '',
					'amount' => 0,
				];
			}

			$data['displayItems'] = $items;
			$data['total']        = [
				'label'   => apply_filters( 'fkwcs_payment_request_total_label', $this->clean_statement_descriptor( Helper::get_gateway_descriptor() ) ),
				'amount'  => $this->get_formatted_amount( $total ),
				'pending' => true,
			];

			$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() );
			$data['currency']        = strtolower( get_woocommerce_currency() );
			$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

			wp_send_json( $data );
		} catch ( Exception $e ) {
			wp_send_json( [ 'error' => wp_strip_all_tags( $e->getMessage() ) ] );
		}
	}

	/**
	 * Updates shipping address
	 *
	 * @return void
	 */
	public function update_shipping_address() {
		check_ajax_referer( 'fkwcs_nonce', 'fkwcs_nonce' );

		$shipping_address = filter_input_array( INPUT_POST, [
			'country'   => FILTER_UNSAFE_RAW,
			'state'     => FILTER_UNSAFE_RAW,
			'postcode'  => FILTER_UNSAFE_RAW,
			'city'      => FILTER_UNSAFE_RAW,
			'address'   => FILTER_UNSAFE_RAW,
			'address_2' => FILTER_UNSAFE_RAW,
		] );

		$request = wc_clean( $_POST );

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

		add_filter( 'woocommerce_cart_ready_to_calc_shipping', function () {
			return true;
		}, 1000 );
		try {
			$this->wc_stripe_update_customer_location( $shipping_address );
			$this->wc_stripe_update_shipping_methods( $this->get_shipping_method_from_request( $request ) );

			/** update the WC cart with the new shipping options */
			WC()->cart->calculate_totals();

			/** if shipping address is not serviceable, throw an error */
			if ( ! $this->wc_stripe_shipping_address_serviceable( $this->get_shipping_packages() ) ) {
				$this->reason_code = 'SHIPPING_ADDRESS_UNSERVICEABLE';
				throw new Exception( __( 'Your shipping address is not serviceable.', 'woo-stripe-payment' ) );
			}

			$data = apply_filters( 'wc_stripe_googlepay_paymentdata_response', array_merge( array(
				'shipping_methods' => $this->get_formatted_shipping_methods(),
				'address'          => $shipping_address,
				'result'           => 'success'
			), $this->build_display_items() ) );
		} catch ( \Exception $e ) {
			$data = array(
				'result' => 'fail'
			);
		}

		wp_send_json( $data );
	}

	/**
	 * Returns a formatted array of items for display in the payment gateway's payment sheet
	 *
	 * @param $page String
	 * @param $order
	 *
	 * @return mixed|null
	 */
	public function get_display_items( $page = 'checkout', $order = null ) {
		global $wp;

		$items = array();
		if ( in_array( $page, array( 'cart', 'checkout' ), true ) ) {
			$items = $this->get_display_items_for_cart( WC()->cart );
		} elseif ( 'order_pay' === $page ) {
			$order = ! is_null( $order ) ? $order : wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			$items = $this->get_display_items_for_order( $order );
		} elseif ( 'product' === $page ) {
			global $product;
			$items = array( $this->get_display_item_for_product( $product ) );
		}

		/**
		 * @param array $items
		 * @param WC_Order|null $order
		 * @param string $page
		 */
		return apply_filters( 'wc_stripe_get_display_items', $items, $order, $page );
	}

	/**
	 * Return whether or not the cart is displaying prices including tax, rather than excluding tax
	 *
	 * @return bool
	 */
	public function display_prices_including_tax() {
		$cart = WC()->cart;
		if ( method_exists( $cart, 'display_prices_including_tax' ) ) {
			return $cart->display_prices_including_tax();
		}
		if ( is_callable( array( $cart, 'get_tax_price_display_mode' ) ) ) {
			return 'incl' === $cart->get_tax_price_display_mode() && ( WC()->customer && ! WC()->customer->is_vat_exempt() );
		}

		return 'incl' === $cart->tax_display_cart && ( WC()->customer && ! WC()->customer->is_vat_exempt() );
	}

	/**
	 * Get all Shipping methods
	 *
	 * @param $methods
	 *
	 * @return array|mixed
	 */
	public function get_formatted_shipping_methods( $methods = array() ) {
		if ( function_exists( 'wcs_is_subscription' ) && \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment ) {
			return $methods;
		}

		$methods        = array();
		$chosen_methods = array();
		$packages       = $this->get_shipping_packages();
		$incl_tax       = $this->display_prices_including_tax();
		foreach ( WC()->session->get( 'chosen_shipping_methods', array() ) as $i => $id ) {
			$chosen_methods[] = $this->get_shipping_method_id( $id );
		}
		foreach ( $packages as $i => $package ) {
			foreach ( $package['rates'] as $rate ) {
				$price     = $incl_tax ? $rate->cost + $rate->get_shipping_tax() : $rate->cost;
				$methods[] = $this->get_formatted_shipping_method( $price, $rate, $i, $package, $incl_tax );
			}
		}

		/**
		 * Sort shipping methods so the selected method is first in the array.
		 */
		usort( $methods, function ( $method ) use ( $chosen_methods ) {
			foreach ( $chosen_methods as $id ) {
				if ( in_array( $id, $method, true ) ) {
					return - 1;
				}
			}

			return 1;
		} );

		/**
		 * @param array $methods
		 */
		$methods = apply_filters( 'wc_stripe_get_formatted_shipping_methods', $methods, $this );
		if ( empty( $methods ) ) {
			/** GPay does not like empty shipping methods. Make a temporary one */
			$methods[] = array(
				'id'          => 'default',
				'label'       => __( 'Waiting...', 'woo-stripe-payment' ),
				'description' => __( 'loading shipping methods...', 'woo-stripe-payment' ),
			);
		}

		return $methods;
	}

	/**
	 * Get all formatted shipping methods
	 *
	 * @param $price
	 * @param $rate
	 * @param $i
	 * @param $package
	 * @param $incl_tax
	 *
	 * @return array
	 */
	public function get_formatted_shipping_method( $price, $rate, $i, $package, $incl_tax ) {
		$method = array(
			'id'     => $this->get_shipping_method_id( $rate->id ),
			'label'  => $this->get_formatted_shipping_label( $price, $rate, $incl_tax ),
			'detail' => '',
			'amount' => $this->get_formatted_amount( $price )
		);

		if ( $incl_tax ) {
			if ( $rate->get_shipping_tax() > 0 && ! wc_prices_include_tax() ) {
				$method['detail'] = WC()->countries->inc_tax_or_vat();
			}
		} else {
			if ( $rate->get_shipping_tax() > 0 && wc_prices_include_tax() ) {
				$method['detail'] = WC()->countries->ex_tax_or_vat();
			}
		}

		return $method;
	}

	/**
	 * Get formatted shipping label
	 *
	 * @param $price
	 * @param \WC_Shipping_Rate $rate
	 * @param $incl_tax
	 *
	 * @return string
	 */
	protected function get_formatted_shipping_label( $price, $rate, $incl_tax ) {
		$label = sprintf( '%s: %s %s', esc_attr( $rate->get_label() ), number_format( $price, 2 ), get_woocommerce_currency() );
		if ( $incl_tax ) {
			if ( $rate->get_shipping_tax() > 0 && ! wc_prices_include_tax() ) {
				$label .= ' ' . WC()->countries->inc_tax_or_vat();
			}
		} else {
			if ( $rate->get_shipping_tax() > 0 && wc_prices_include_tax() ) {
				$label .= ' ' . WC()->countries->ex_tax_or_vat();
			}
		}

		return $label;
	}

	/**
	 * @param string $id
	 * @param string $index
	 *
	 * @return mixed
	 */
	protected function get_shipping_method_id( $id ) {
		return $id;
	}

	/**
	 * Return true if there are shipping packages that contain rates.
	 *
	 * @param array $packages
	 *
	 * @return boolean
	 * @package Stripe/Functions
	 */
	public function wc_stripe_shipping_address_serviceable( $packages = array() ) {
		if ( $packages ) {
			foreach ( $packages as $package ) {
				if ( count( $package['rates'] ) > 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param [] $request
	 *
	 * @return array
	 * @throws Exception
	 */
	private function get_shipping_method_from_request( $request ) {
		if ( isset( $request['shipping_method'] ) ) {
			if ( ! preg_match( '/^(?P<index>[\w]+)\:(?P<id>.+)$/', $request['shipping_method'], $shipping_method ) ) {
				throw new Exception( __( 'Invalid shipping method format. Expected: index:id', 'woo-stripe-payment' ) );
			}

			return array( $shipping_method['index'] => $shipping_method['id'] );
		}

		return array();
	}

	/**
	 * Updates shipping option
	 *
	 * @return void
	 */
	public function update_shipping_option() {
		check_ajax_referer( 'fkwcs_nonce', 'fkwcs_nonce' );

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
		$shipping_methods = ! empty( $_POST['shipping_method'] ) ? wc_clean( $_POST['shipping_method'] ) : '';
		WC()->shipping->reset_shipping();

		$this->update_shipping_method( $shipping_methods );
		WC()->cart->calculate_totals();
		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_UNSAFE_RAW ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );
		$data                      = [];
		$data                      += $this->build_display_items( $should_show_itemized_view );
		$data['result']            = 'success';

		wp_send_json( $data );
	}

	/**
	 *
	 * @param   [] $address
	 *
	 * @throws Exception
	 * @package Stripe/Functions
	 */
	public function wc_stripe_update_customer_location( $address ) {
		// address validation for countries other than US is problematic when using responses from payment sources like
		// Apple Pay.
		if ( $address['postcode'] && $address['country'] === 'US' && ! WC_Validation::is_postcode( $address['postcode'], $address['country'] ) ) {
			throw new Exception( __( 'Please enter a valid postcode / ZIP.', 'woocommerce' ) );
		} elseif ( $address['postcode'] ) {
			$address['postcode'] = wc_format_postcode( $address['postcode'], $address['country'] );
		}

		if ( $address['country'] ) {
			WC()->customer->set_billing_location( $address['country'], $address['state'], $address['postcode'], $address['city'] );
			WC()->customer->set_shipping_location( $address['country'], $address['state'], $address['postcode'], $address['city'] );
			// set the customer's address if it's in the $address array
			if ( ! empty( $address['address_1'] ) ) {
				WC()->customer->set_shipping_address_1( wc_clean( $address['address_1'] ) );
			}
			if ( ! empty( $address['address_2'] ) ) {
				WC()->customer->set_shipping_address_2( wc_clean( $address['address_2'] ) );
			}
			if ( ! empty( $address['first_name'] ) ) {
				WC()->customer->set_shipping_first_name( $address['first_name'] );
			}
			if ( ! empty( $address['last_name'] ) ) {
				WC()->customer->set_shipping_last_name( $address['last_name'] );
			}
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		do_action( 'woocommerce_calculated_shipping' );
	}


	/**
	 * Calculated shipping charges
	 *
	 * @param array $address updated address.
	 *
	 * @return void
	 */
	protected function calculate_shipping( $address = [] ) {
		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address'];
		$address_2 = $address['address_2'];

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = [];

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}

	/**
	 * Updates shipping method
	 *
	 * @param array $shipping_methods available shipping methods array.
	 *
	 * @return void
	 */
	public function update_shipping_method( $shipping_methods ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	public function merge_cart_details( $fragments ) {

		$fragments['fkwcs_cart_details'] = $this->ajax_get_cart_details();

		return $fragments;
	}

	/**
	 *
	 * @param   [] $methods
	 *
	 * @package Stripe/Functions
	 */
	public function wc_stripe_update_shipping_methods( $methods ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', array() );

		foreach ( $methods as $i => $method ) {
			$chosen_shipping_methods[ $i ] = $method;
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * @return array
	 */
	public function get_shipping_packages() {
		$packages = WC()->shipping()->get_packages();
		if ( empty( $packages ) && function_exists( 'wcs_is_subscription' ) && \WC_Subscriptions_Cart::cart_contains_free_trial() ) {
			// there is a subscription with a free trial in the cart. Shipping packages will be in the recurring cart.
			\WC_Subscriptions_Cart::set_calculation_type( 'recurring_total' );
			$count = 0;
			if ( isset( WC()->cart->recurring_carts ) ) {
				foreach ( WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart ) {
					foreach ( $recurring_cart->get_shipping_packages() as $base_package ) {
						$packages[ $recurring_cart_key . '_' . $count ] = \WC_Subscriptions_Cart::get_calculated_shipping_for_package( $base_package );
					}
					$count ++;
				}
			}
			\WC_Subscriptions_Cart::set_calculation_type( 'none' );
		}

		return $packages;
	}


	/**
	 * Checks if a current page is a cart page
	 *
	 * @return bool
	 */
	public function is_cart() {
		return apply_filters( 'fkwcs_express_button_is_cart', parent::is_cart(), $this );
	}

}
