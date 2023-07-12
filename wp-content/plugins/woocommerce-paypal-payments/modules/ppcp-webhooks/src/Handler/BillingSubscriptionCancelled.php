<?php
/**
 * Handles the Webhook BILLING.SUBSCRIPTION.CANCELLED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class BillingSubscriptionCancelled
 */
class BillingSubscriptionCancelled implements RequestHandler {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * BillingSubscriptionCancelled constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array(
			'BILLING.SUBSCRIPTION.CANCELLED',
		);
	}

	/**
	 * Whether a handler is responsible for a given request or not.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function responsible_for_request( WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * Responsible for handling the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$response = array( 'success' => false );
		if ( is_null( $request['resource'] ) ) {
			return new WP_REST_Response( $response );
		}

		$subscription_id = wc_clean( wp_unslash( $request['resource']['id'] ?? '' ) );
		if ( $subscription_id ) {
			$args          = array(
				// phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_query' => array(
					array(
						'key'     => 'ppcp_subscription',
						'value'   => $subscription_id,
						'compare' => '=',
					),
				),
			);
			$subscriptions = wcs_get_subscriptions( $args );
			foreach ( $subscriptions as $subscription ) {
				$subscription->update_status( 'cancelled' );
			}
		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
