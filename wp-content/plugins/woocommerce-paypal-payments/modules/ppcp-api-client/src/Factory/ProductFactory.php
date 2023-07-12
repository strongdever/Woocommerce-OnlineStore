<?php
/**
 * The Product factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Product;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ProductFactory
 */
class ProductFactory {

	/**
	 * Creates a Product based off a PayPal response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return Product
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( stdClass $data ): Product {
		if ( ! isset( $data->id ) ) {
			throw new RuntimeException(
				__( 'No id for product given', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->name ) ) {
			throw new RuntimeException(
				__( 'No name for product given', 'woocommerce-paypal-payments' )
			);
		}

		return new Product(
			$data->id,
			$data->name,
			$data->description ?? ''
		);
	}
}
