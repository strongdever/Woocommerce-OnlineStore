<?php

namespace FKWCS\Gateway\Stripe\Traits;

use FKWCS\Gateway\Stripe\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Subscriptions compatibility.
 */
trait WC_Subscriptions_Trait {

	use WC_Subscriptions_Helper_Trait;

	/**
	 * Initialize subscription support and hooks.
	 *
	 */
	public function maybe_init_subscriptions() {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$this->supports = array_merge( $this->supports, [
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		] );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, [ $this, 'update_failing_payment_method' ], 10, 2 );
		add_action( 'wcs_resubscribe_order_created', [ $this, 'delete_resubscribe_meta' ], 10 );
		add_action( 'wcs_renewal_order_created', [ $this, 'delete_renewal_meta' ], 10 );

		add_action( 'wc_stripe_payment_fields_' . $this->id, [ $this, 'display_update_subs_payment_checkout' ] );
		add_action( 'wc_stripe_add_payment_method_' . $this->id . '_success', [ $this, 'handle_add_payment_method_success' ], 10 );


		// Display the payment method used for a subscription in the "My Subscriptions" table.
		add_filter( 'woocommerce_my_subscriptions_payment_method', [ $this, 'maybe_render_subscription_payment_method' ], 10, 2 );

		// Allow store managers to manually set Stripe as the payment method on a subscription.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );
		add_action( 'woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 3 );
		add_filter( 'fkwcs_stripe_display_save_payment_method_checkbox', [ $this, 'display_save_payment_method_checkbox' ] );

		add_filter( 'fkwcs_payment_intent_data', [ $this, 'maybe_add_emandate_data_to_request' ], 10, 3 );


	}

	/**
	 * Displays a checkbox to allow users to update all subs payments with new
	 * payment.
	 *
	 */
	public function display_update_subs_payment_checkout() {
		$subs_statuses = apply_filters( 'fkwcs_stripe_update_subs_payment_method_card_statuses', [ 'active' ] );
		if ( apply_filters( 'fkwcs_stripe_display_update_subs_payment_method_card_checkbox', true ) && wcs_user_has_subscription( get_current_user_id(), '', $subs_statuses ) && is_add_payment_method_page() ) {
			$label = esc_html( apply_filters( 'wc_stripe_save_to_subs_text', __( 'Update the Payment Method used for all of my active subscriptions.', 'funnelkit-stripe-woo-payment-gateway' ) ) );
			$id    = sprintf( 'wc-%1$s-update-subs-payment-method-card', $this->id );
			woocommerce_form_field( $id, [
				'type'    => 'checkbox',
				'label'   => $label,
				'default' => apply_filters( 'fkwcs_stripe_save_to_subs_checked', false ),
			] );
		}
	}

	/**
	 * Updates all active subscriptions payment method.
	 *
	 * @param string $source_id
	 *
	 */
	public function handle_add_payment_method_success( $source_id ) {
		if ( ! isset( $_POST[ 'wc-' . $this->id . '-update-subs-payment-method-card' ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$all_subs      = wcs_get_users_subscriptions();
		$subs_statuses = apply_filters( 'wc_stripe_update_subs_payment_method_card_statuses', [ 'active' ] );
		if ( empty( $all_subs ) ) {
			return;
		}
		$fkwcs_customer_id = Helper::get_customer_key();
		$stripe_id         = $this->get_customer_id();
		foreach ( $all_subs as $sub ) {
			if ( ! $sub->has_status( $subs_statuses ) ) {
				continue;
			}
			\WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $sub, $this->id, [
				'post_meta' => [
					'_fkwcs_source_id' => [ 'value' => $source_id ],
					$fkwcs_customer_id => [ 'value' => $stripe_id ],
				],
			] );
		}
	}


	/**
	 * Maybe process payment method change for subscriptions.
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 *
	 */
	public function maybe_change_subscription_payment_method( $order_id ) {
		return ( $this->is_subscriptions_enabled() && $this->has_subscription( $order_id ) && $this->is_changing_payment_method_for_subscription() );
	}

	/**
	 * Process the payment method change for subscriptions.
	 *
	 * @param int $order_id
	 *
	 * @return array|null
	 *
	 */
	public function process_change_subscription_payment_method( $order_id, $is_change_subs = false ) {
		try {
			$subscription    = wc_get_order( $order_id );
			$prepared_source = $this->prepare_source( $subscription, true );

			/**
			 * For the change subscription payment process we do not need to create setup intent and get the return url, its for checkout only
			 */
			if ( false === $is_change_subs ) {
				$intent_secret = $this->create_setup_intent( $prepared_source->source, $prepared_source->customer, $subscription );

				if ( ! empty( $intent_secret ) ) {
					$intent_data = [
						'id'            => $intent_secret['id'],
						'client_secret' => $intent_secret['client_secret'],
					];

					$subscription->update_meta_data( '_fkwcs_setup_intent', $intent_data );
					$subscription->save_meta_data();

					// `get_return_url()` must be called immediately before returning a value.
					return [
						'result'                    => 'success',
						'fkwcs_redirect'            => $this->get_return_url( $subscription ),
						'fkwcs_setup_intent_secret' => $intent_secret['client_secret'],
					];
				}
			}

			$this->save_payment_method_to_order( $subscription, $prepared_source );

			do_action( 'fkwcs_change_subs_payment_method_success', $prepared_source->source, $prepared_source );

			if ( 'automatic' === $this->capture_method ) {
				$subscription->payment_complete();
			} else {
				$subscription->update_status( apply_filters( 'fkwcs_stripe_authorized_order_status', 'on-hold' ) );
			}

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $subscription ),
			];
		} catch ( \Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			Helper::log( 'Payment Failed. Reason: ' . $e->getMessage() );
		}
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order \WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {


		$this->process_subscription_payment( $amount_to_charge, $renewal_order, true, false );
	}


	/**
	 * Process_subscription_payment function.
	 *
	 * @param float $amount
	 * @param mixed|\WC_order $renewal_order
	 * @param bool $retry Should we retry the process?
	 * @param object $previous_error
	 *
	 * @return void|null
	 */
	public function process_subscription_payment( $amount, $renewal_order, $retry = true, $previous_error = false ) {

		if ( false === $this->is_configured() ) {
			return;
		}
		try {
			$order_id = $renewal_order->get_id();

			// Unlike regular off-session subscription payments, early renewals are treated as on-session payments, involving the customer.
			// This makes the SCA authorization popup show up for the "Renew early" modal (Subscriptions settings > Accept Early Renewal Payments via a Modal).
			// Note: Currently available for non-UPE credit card only.
			if ( isset( $_REQUEST['process_early_renewal'] ) && 'fkwcs_stripe' === $this->id ) { // phpcs:ignore WordPress.Security.NonceVerification
				$response = $this->process_payment( $order_id, true, false, $previous_error, true );

				if ( ! is_null( $response ) && 'success' === $response['result'] && isset( $response['fkwcs_intent_secret'] ) ) {
					$verification_url = add_query_arg( [
						'order'                       => $order_id,
						'early_renewal_payment_nonce' => wp_create_nonce( 'fkwcs_stripe_confirm_payment_intent' ),
						'redirect_to'                 => remove_query_arg( [ 'process_early_renewal', 'subscription_id', 'wcs_nonce' ] ),
						'early_renewal'               => true,
					], \WC_AJAX::get_endpoint( 'fkwcs_stripe_verify_payment_intent' ) );

					echo wp_json_encode( [
						'fkwcs_stripe_sca_required' => true,
						'intent_secret'             => $response['fkwcs_intent_secret'],
						'redirect_url'              => $verification_url,
					] );

					exit;
				}

				// Hijack all other redirects in order to do the redirection in JavaScript.
				add_action( 'wp_redirect', [ $this, 'redirect_after_early_renewal' ], 100 );

				return;
			}

			// Check for an existing intent, which is associated with the order.
			if ( $this->has_authentication_already_failed( $renewal_order ) ) {
				return;
			}
			Helper::log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );


			// Get source from order
			$prepared_source = $this->prepare_subscription_order_source( $renewal_order );

			if ( is_null( $prepared_source ) || ! $prepared_source->customer ) {
				throw new \Exception( 'Failed to process renewal for order ' . $renewal_order->get_id() . '. Stripe customer id is missing in the order', 200 );
			}


			if ( ( $this->is_no_such_source_error( $prepared_source->source_object ) || $this->is_no_linked_source_error( $prepared_source->source_object ) ) && apply_filters( 'fkwcs_stripe_use_default_customer_source', true ) ) {

				// Passing empty source will charge customer default.
				$prepared_source->source = '';
			}

			if ( ( $this->is_no_such_source_error( $previous_error ) || $this->is_no_linked_source_error( $previous_error ) ) && apply_filters( 'fkwcs_stripe_use_default_customer_source', true ) ) {

				// Passing empty source will charge customer default.
				$prepared_source->source = '';
			}

			$this->lock_order_payment( $renewal_order );
			$response                   = $this->create_and_confirm_intent_for_off_session( $renewal_order, $prepared_source );
			$is_authentication_required = $this->is_authentication_required_for_payment( $response );

			// It's only a failed payment if it's an error and it's not of the type 'authentication_required'.
			// If it's 'authentication_required', then we should email the user and ask them to authenticate.
			if ( ! empty( $response->error ) && ! $is_authentication_required ) {

				// We want to retry.
				if ( $this->is_retryable_error( $response->error ) ) {
					if ( $retry ) {
						// Don't do anymore retries after this.
						if ( 5 <= $this->retry_interval ) {
							return $this->process_subscription_payment( $amount, $renewal_order, false, $response->error );
						}

						sleep( $this->retry_interval );

						$this->retry_interval ++;

						return $this->process_subscription_payment( $amount, $renewal_order, true, $response->error );
					} else {
						$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'funnelkit-stripe-woo-payment-gateway' );
						throw new \Exception( $localized_message ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					}
				}

				$localized_messages = Helper::get_localized_messages();

				if ( 'card_error' === $response->error->type ) {
					$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
				} elseif ( 'payment_intent_mandate_invalid' === $response->error->type ) {
					$localized_message = __( 'The mandate used for this renewal payment is invalid. You may need to bring the customer back to your store and ask them to resubmit their payment information.', 'funnelkit-stripe-woo-payment-gateway' );
				} else {
					$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
				}


				throw new \Exception( $localized_message ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			// Either the charge was successfully captured, or it requires further authentication.
			if ( $is_authentication_required ) {
				do_action( 'fkwcs_gateway_stripe_process_payment_authentication_required', $renewal_order, $response );

				$error_message = __( 'This transaction requires authentication.', 'funnelkit-stripe-woo-payment-gateway' );
				$renewal_order->add_order_note( $error_message );

				$charge = end( $response->error->payment_intent->charges->data );
				$id     = $charge->id;
				$this->save_intent_to_order( $renewal_order, $response->error->payment_intent );

				$renewal_order->set_transaction_id( $id );
				/* translators: %s is the charge Id */
				$renewal_order->update_status( 'failed', sprintf( __( 'Stripe charge awaiting authentication by user: %s.', 'funnelkit-stripe-woo-payment-gateway' ), $id ) );
				if ( is_callable( [ $renewal_order, 'save' ] ) ) {
					$renewal_order->save();
				}
			} elseif ( $this->maybe_check_for_auth( $response->data ) ) {
				$charge_attempt_at = $response->data->processing->card->customer_notification->completes_at;
				$attempt_date      = wp_date( get_option( 'date_format', 'F j, Y' ), $charge_attempt_at, wp_timezone() );
				$attempt_time      = wp_date( get_option( 'time_format', 'g:i a' ), $charge_attempt_at, wp_timezone() );

				$message = sprintf( /* translators: 1) a date in the format yyyy-mm-dd, e.g. 2021-09-21; 2) time in the 24-hour format HH:mm, e.g. 23:04 */ __( 'The customer must authorize this payment via the pre-debit notification sent to them by their card issuing bank, before %1$s at %2$s, when the charge will be attempted.', 'woocommerce-gateway-stripe' ), $attempt_date, $attempt_time );
				$renewal_order->add_order_note( $message );
				$renewal_order->update_status( 'pending' );
				$renewal_order->update_meta_data( '_fkwcs_maybe_check_for_auth', 'yes' );
				$this->save_intent_to_order( $renewal_order, $response->data );
				if ( is_callable( [ $renewal_order, 'save' ] ) ) {
					$renewal_order->save();
				}
			} else {


				$response = $response->data;
				$this->save_intent_to_order( $renewal_order, $response );
				// The charge was successfully captured
				do_action( 'fkwcs_gateway_stripe_process_payment', $response, $renewal_order );

				// Use the last charge within the intent or the full response body in case of SEPA.
				$this->process_final_order( isset( $response->charges ) ? end( $response->charges->data ) : $response, $renewal_order );

			}
		} catch ( \Exception $e ) {
			Helper::log( $e->getMessage(), 'warning' );

			do_action( 'fkwcs_gateway_stripe_process_payment_error', $e, $renewal_order );
			/* translators: error message */
			$renewal_order->update_status( 'failed', 'Reason: ' . $e->getMessage() );
		}
	}

	public function prepare_subscription_order_source( $order = null ) {
		$stripe_source      = false;
		$token_id           = false;
		$source_object      = false;
		$customer_key       = Helper::get_customer_key();
		$stripe_customer_id = '';
		if ( $order ) {
			$client = $this->get_client();

			if ( is_null( $client ) ) {

				Helper::log( __FUNCTION__ . ' Stripe Client not setup' );

				return null;
			}

			$stripe_customer_id = $this->get_order_stripe_data( $customer_key, $order );
			$source_id          = $this->get_order_stripe_data( '_fkwcs_source_id', $order );

			if ( $source_id ) {
				$stripe_source = $source_id;
				$response      = $client->payment_methods( 'retrieve', [ $source_id ] );
				$source_object = $this->handle_client_response( $response, false );
			} elseif ( apply_filters( 'fkwcs_stripe_use_default_customer_source', true ) ) {
				/*
				 * We can attempt to charge the customer's default source
				 * by sending empty source id.
				 */
				$stripe_source = '';
			}
		}

		return (object) [
			'token_id'       => $token_id,
			'customer'       => $stripe_customer_id,
			'source'         => $stripe_source,
			'source_object'  => (object) $source_object,
			'payment_method' => null,
		];
	}

	/**
	 * Updates other subscription sources.
	 */
	public function maybe_update_source_on_subscription_order( $order, $source ) {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$order_id = $order->get_id();

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = [];
		}
		$customer_key = Helper::get_customer_key();
		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( $customer_key, $source->customer );
			if ( ! empty( $source->payment_method ) ) {
				$subscription->update_meta_data( '_fkwcs_source_id', $source->payment_method );
			} else {
				$subscription->update_meta_data( '_fkwcs_source_id', $source->source );

			}
			$subscription->save_meta_data();

		}
	}


	public function delete_resubscribe_meta( $resubscribe_order ) {
		/**
		 * @var $resubscribe_order \WC_Order
		 */
		$resubscribe_order->delete_meta_data( Helper::get_customer_key() );
		$resubscribe_order->delete_meta_data( '_fkwcs_source_id' );
		$resubscribe_order->delete_meta_data( '_fkwcs_card_id' );
		$resubscribe_order->delete_meta_data( '_fkwcs_intent_id' );

		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return false|mixed
	 */
	public function delete_renewal_meta( $order ) {
		if ( is_null( $order ) ) {
			return false;
		}
		$order->delete_meta_data( '_fkwcs_intent_id' );
		$order->save_meta_data();

		return $order;
	}

	/**
	 * @param \WC_Subscription $subscription
	 * @param $renewal_order
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$subscription->update_meta_data( Helper::get_customer_key(), Helper::get_meta( $renewal_order, Helper::get_customer_key() ) );
		$subscription->update_meta_data( '_fkwcs_source_id', Helper::get_meta( $renewal_order, '_fkwcs_source_id' ) );
		$subscription->save_meta_data();
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param \WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 *
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$subscription_id = $subscription->get_id();
		$source_id       = Helper::get_meta( $subscription, '_fkwcs_source_id' );

		// For BW compat will remove in future.
		if ( empty( $source_id ) ) {
			$source_id = Helper::get_meta( $subscription, '_fkwcs_card_id' );

			// Take this opportunity to update the key name.
			$subscription->update_meta_data( $subscription_id, '_fkwcs_source_id', $source_id );
			$subscription->delete_meta_data( '_fkwcs_card_id' );
			$subscription->save_meta_data();
		}
		$customer_key              = Helper::get_customer_key();
		$payment_meta[ $this->id ] = [
			'post_meta' => [
				$customer_key      => [
					'value' => $this->get_order_stripe_data( $customer_key, $subscription ),
					'label' => 'Stripe Customer ID',
				],
				'_fkwcs_source_id' => [
					'value' => $this->get_order_stripe_data( '_fkwcs_source_id', $subscription ),
					'label' => 'Stripe Source ID',
				],

			],
		];

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param \WC_Subscription $subscription associative array of meta data required for automatic payments
	 *
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( $this->id === $payment_method_id ) {
			$fkwcs_customer_id = Helper::get_customer_key();

			/**
			 * Try to find out customer ID from all the sources available
			 */
			$customer_id        = false;
			$other_customer_IDs = Helper::get_compatibility_keys( $fkwcs_customer_id );


			if ( isset( $payment_meta['post_meta'][ $fkwcs_customer_id ]['value'] ) && ! empty( $payment_meta['post_meta'][ $fkwcs_customer_id ]['value'] ) ) {
				$customer_id = $payment_meta['post_meta'][ $fkwcs_customer_id ]['value'];
			} elseif ( Helper::get_meta( $subscription, $other_customer_IDs[0] ) ) {
				$customer_id = Helper::get_meta( $subscription, $other_customer_IDs[0] );

			} elseif ( Helper::get_meta( $subscription, $other_customer_IDs[1] ) ) {
				$customer_id = Helper::get_meta( $subscription, $other_customer_IDs[1] );

			}


			if ( empty( $customer_id ) ) {
				// Allow empty stripe customer id during subscription renewal. It will be added when processing payment if required.
				if ( ! isset( $_POST['wc_order_action'] ) || 'wcs_process_renewal' !== $_POST['wc_order_action'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
					throw new \Exception( __( 'A "Stripe Customer ID" value is required.', 'funnelkit-stripe-woo-payment-gateway' ) );
				}
			} elseif ( 0 !== strpos( $customer_id, 'cus_' ) ) {
				throw new \Exception( __( 'Invalid customer ID. A valid "Stripe Customer ID" must begin with "cus_".', 'funnelkit-stripe-woo-payment-gateway' ) );
			}


			/**
			 * try and find our source ID from the all possible meta
			 */
			$source           = false;
			$other_source_IDs = Helper::get_compatibility_keys( '_fkwcs_source_id' );
			if ( isset( $payment_meta['post_meta']['_fkwcs_source_id']['value'] ) && ! empty( $payment_meta['post_meta']['_fkwcs_source_id']['value'] ) ) {
				$source = $payment_meta['post_meta']['_fkwcs_source_id']['value'];
			} elseif ( Helper::get_meta( $subscription, $other_source_IDs[0] ) ) {
				$source = Helper::get_meta( $subscription, $other_source_IDs[0] );

			} elseif ( Helper::get_meta( $subscription, $other_source_IDs[1] ) ) {
				$source = Helper::get_meta( $subscription, $other_source_IDs[1] );

			}

			if ( ! empty( $source ) && ( 0 !== strpos( $source, 'card_' ) && 0 !== strpos( $source, 'src_' ) && 0 !== strpos( $source, 'pm_' ) ) ) {
				throw new \Exception( __( 'Invalid source ID. A valid source "Stripe Source ID" must begin with "src_", "pm_", or "card_".', 'funnelkit-stripe-woo-payment-gateway' ) );
			}
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param \WC_Subscription $subscription the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		$customer_user     = $subscription->get_customer_id();
		$fkwcs_customer_id = Helper::get_customer_key();
		// bail for other payment methods
		if ( $subscription->get_payment_method() !== $this->id || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$stripe_source_id   = Helper::get_meta( $subscription, '_fkwcs_source_id' );
		$stripe_customer_id = Helper::get_meta( $subscription, $fkwcs_customer_id );

		// If we couldn't find a Stripe customer linked to the subscription, fallback to the user meta data.
		if ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) {
			$user_id            = $customer_user;
			$stripe_customer_id = get_user_option( $fkwcs_customer_id, $user_id );
			$stripe_source_id   = get_user_option( '_fkwcs_source_id', $user_id );
		}

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) && false !== $subscription->get_parent() ) {
			$stripe_customer_id = Helper::get_meta( $subscription->get_parent(), $fkwcs_customer_id );
			$stripe_source_id   = Helper::get_meta( $subscription->get_parent(), '_fkwcs_source_id' );
		}


		// Retrieve all possible payment methods for subscriptions.
		$sources                   = array_merge( $this->get_payment_methods( $stripe_customer_id, 'card' ) );
		$payment_method_to_display = __( 'N/A', 'funnelkit-stripe-woo-payment-gateway' );

		if ( $sources ) {

			foreach ( $sources as $source ) {
				if ( $source->id === $stripe_source_id ) {
					$card = false;
					if ( isset( $source->type ) && 'card' === $source->type ) {
						$card = $source->card;
					} elseif ( isset( $source->object ) && 'card' === $source->object ) {
						$card = $source;
					}
					if ( $card ) {
						/* translators: 1) card brand 2) last 4 digits */
						$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'funnelkit-stripe-woo-payment-gateway' ), ( isset( $card->brand ) ? $card->brand : __( 'N/A', 'funnelkit-stripe-woo-payment-gateway' ) ), $card->last4 );
					} elseif ( $source->sepa_debit ) {
						/* translators: 1) last 4 digits of SEPA Direct Debit */
						$payment_method_to_display = sprintf( __( 'Via SEPA Direct Debit ending in %1$s', 'funnelkit-stripe-woo-payment-gateway' ), $source->sepa_debit->last4 );
					}
					break;
				}
			}
		}

		return $payment_method_to_display;
	}


	/**
	 * Checks if a renewal already failed because a manual authentication is required.
	 *
	 * @param \WC_Order $renewal_order The renewal order.
	 *
	 * @return boolean
	 */
	public function has_authentication_already_failed( $renewal_order ) {
		$existing_intent = $this->get_intent_from_order( $renewal_order );

		if ( ! $existing_intent || 'requires_payment_method' !== $existing_intent->status || empty( $existing_intent->last_payment_error ) || 'authentication_required' !== $existing_intent->last_payment_error->code ) {
			return false;
		}

		// Make sure all emails are instantiated.
		\WC_Emails::instance();

		/**
		 * A payment attempt failed because SCA authentication is required.
		 *
		 * @param \WC_Order $renewal_order The order that is being renewed.
		 */
		do_action( 'wc_gateway_stripe_process_payment_authentication_required', $renewal_order );

		// Fail the payment attempt (order would be currently pending because of retry rules).
		$charge    = end( $existing_intent->charges->data );
		$charge_id = $charge->id;
		/* translators: %s is the stripe charge Id */
		$renewal_order->update_status( 'failed', sprintf( __( 'Stripe charge awaiting authentication by user: %s.', 'funnelkit-stripe-woo-payment-gateway' ), $charge_id ) );

		return true;
	}

	/**
	 * Hijacks `wp_redirect` in order to generate a JS-friendly object with the URL.
	 *
	 * @param string $url The URL that Subscriptions attempts a redirect to.
	 *
	 * @return void
	 */
	public function redirect_after_early_renewal( $url ) {
		echo wp_json_encode( [
			'fkwcs_stripe_sca_required' => false,
			'redirect_url'              => $url,
		] );

		exit;
	}


	public function get_payment_methods( $customer_id, $payment_method_type ) {
		if ( ! $customer_id ) {
			return [];
		}
		$stripe_api  = $this->get_client();
		$list_params = [
			'customer' => $customer_id,
			'type'     => $payment_method_type,
			'limit'    => 100, // Maximum allowed value.
		];

		$response        = $stripe_api->payment_methods( 'all', [ $list_params ] );
		$payment_methods = $response['success'] ? $response['data'] : false;

		if ( ! empty( $payment_methods->error ) ) {
			return [];
		}

		if ( is_array( $payment_methods->data ) ) {
			$payment_methods = $payment_methods->data;
		}


		return empty( $payment_methods ) ? [] : $payment_methods;
	}


	public function maybe_add_emandate_data_to_request( $data, $order, $is_setup_intent = false ) {

		/**
		 * Do not proceed further if there we do not have subscriptions
		 */
		if ( false === $order || ! $this->has_subscription( $order->get_id() ) ) {
			return $data;
		}


		/**
		 * Handle automatic subscription renewal request here
		 */
		if ( 0 < did_action( 'woocommerce_scheduled_subscription_payment_' . $this->id ) ) {
			$mandate = $order->get_meta( '_stripe_mandate_id', true );
			if ( ! empty( $mandate ) ) {
				$data['mandate'] = $mandate;

				return $data;
			}

			$renewals = wcs_get_subscriptions_for_renewal_order( $order );
			if ( 1 === count( $renewals ) ) {
				$renewal_order   = reset( $renewals );
				$parent_order_id = $renewal_order->get_parent_id();
				$parent_order    = wc_get_order( $parent_order_id );

				if ( $parent_order ) {
					$mandate = $parent_order->get_meta( '_stripe_mandate_id', true );
					if ( ! empty( $mandate ) ) {
						$data['mandate'] = $mandate;

						return $data;
					}
				}
			}
		}


		$subscriptions = wcs_get_subscriptions_for_order( $order );

		/**
		 * return from here because creating mandate is not required at all
		 */
		if ( 0 === count( $subscriptions ) ) {
			return $data;
		}

		$sub_amount = 0;
		foreach ( $subscriptions as $sub ) {
			$sub_amount += Helper::get_stripe_amount( $sub->get_total() );
		}


		$sub = reset( $subscriptions );

		if ( 1 === count( $subscriptions ) ) {
			$data['payment_method_options']['card']['mandate_options']['amount_type']    = 'fixed';
			$data['payment_method_options']['card']['mandate_options']['interval']       = $sub->get_billing_period();
			$data['payment_method_options']['card']['mandate_options']['interval_count'] = $sub->get_billing_interval();
		} else {
			// If there are multiple subscriptions the amount_type becomes 'maximum' so we can charge anything
			// less than the order total, and the interval is sporadic so we don't have to follow a set interval.
			$data['payment_method_options']['card']['mandate_options']['amount_type'] = 'maximum';
			$data['payment_method_options']['card']['mandate_options']['interval']    = 'sporadic';
		}


		/**
		 * Set other common params
		 */
		$data['payment_method_options']['card']['mandate_options']['amount']          = $sub_amount;
		$data['payment_method_options']['card']['mandate_options']['reference']       = $order->get_id();
		$data['payment_method_options']['card']['mandate_options']['start_date']      = $sub->get_time( 'start' );
		$data['payment_method_options']['card']['mandate_options']['supported_types'] = [ 'india' ];

		if ( true === $is_setup_intent ) {
			$data['payment_method_options']['card']['mandate_options']['currency'] = strtolower( $order->get_currency() );
		}

		return $data;
	}

	/**
	 * Check for processing card reason
	 *
	 * Only valid for mandates for Indian 3DS regulations.
	 *
	 * @param StdClass $payment_intent the Payment Intent to be evaluated.
	 *
	 * @return bool true if payment intent must be authorized off session, false otherwise.
	 */
	protected function maybe_check_for_auth( $payment_intent ) {
		return ! empty( $payment_intent->status ) && 'processing' === $payment_intent->status && ! empty( $payment_intent->processing->card->customer_notification->completes_at );
	}


}
