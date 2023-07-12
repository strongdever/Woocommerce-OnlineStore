<?php

/**
 * Plugin Name: Stripe Payment Gateway for WooCommerce
 * Plugin URI: https://www.funnelkit.com/
 * Description: Effortlessly accepts payments via Stripe on your WooCommerce Store.
 * Version: 1.4.0
 * Author: FunnelKit
 * Author URI: https://funnelkit.com/
 * License: GPLv2 or later
 * Text Domain: funnelkit-stripe-woo-payment-gateway
 * WC requires at least: 3.0
 * WC tested up to: 7.8.2
 *
 * Requires at least: 5.4.0
 * Tested up to: 6.2.2
 * Requires PHP: 7.0
 *
 * Stripe Payment Gateway for WooCommerce is free software.
 * You can redistribute it and/or modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Stripe Payment Gateway for WooCommerce is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Funnel Builder. If not, see <http://www.gnu.org/licenses/>.
 */
class FKWCS_Gateway_Stripe {
	private static $instance = null;

	private function __construct() {
		$this->init();
		$this->load_core();
	}

	/**
	 * @return FKWCS_Gateway_Stripe instance
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Core constants
	 *
	 * @return void
	 */
	private function init() {
		define( 'FKWCS_FILE', __FILE__ );
		define( 'FKWCS_DIR', __DIR__ );
		define( 'FKWCS_NAME', 'Stripe Payment Gateway for WooCommerce' );
		define( 'FKWCS_TEXTDOMAIN', 'funnelkit-stripe-woo-payment-gateway' );
		( defined( 'FKWCS_IS_DEV' ) && true === FKWCS_IS_DEV ) ? define( 'FKWCS_VERSION', time() ) : define( 'FKWCS_VERSION', '1.4.0' );

		add_action( 'plugins_loaded', array( $this, 'load_wp_dependent_properties' ), 1 );
	}

	/**
	 * Other dependent constants
	 *
	 * @return void
	 */
	public function load_wp_dependent_properties() {
		define( 'FKWCS_URL', plugins_url( '/', FKWCS_FILE ) );
		define( 'FKWCS_BASE', plugin_basename( FKWCS_FILE ) );
	}

	/**
	 * Include Stripe gateway core class
	 *
	 * @return void
	 */
	public function load_core() {
		require_once __DIR__ . '/plugin.php';
	}
}

FKWCS_Gateway_Stripe::get_instance();
