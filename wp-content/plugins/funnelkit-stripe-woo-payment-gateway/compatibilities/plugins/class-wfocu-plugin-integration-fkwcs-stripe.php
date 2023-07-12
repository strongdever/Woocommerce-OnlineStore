<?php

use FKWCS\Gateway\Stripe\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Plugin_Integration_Fkwcs_Stripe' ) && class_exists( 'WFOCU_Gateway' ) ) {

	class WFOCU_Plugin_Integration_Fkwcs_Stripe extends WFOCU_Gateway {
		protected static $instance = null;
		public $key = 'fkwcs_stripe';
		public $token = false;
		public $has_intent_secret = [];
		public $current_intent;
		public $refund_supported = true;

		public function __construct() {
			parent::__construct();

			/**
			 * tell the core gateway to tokenize the user and handle display of checkbox
			 */
			add_filter( 'fkwcs_stripe_display_save_payment_method_checkbox', array( $this, 'control_checkbox_visibility' ), 999 );


			/**
			 * Add payouts to new order
			 */
			add_action( 'wfocu_offer_new_order_created_stripe', array( $this, 'add_stripe_payouts_to_new_order' ), 10, 1 );


			/**
			 * Render JS script to handle client flow
			 */
			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );


			/**
			 * Add allowed actions
			 */
			add_filter( 'wfocu_allow_ajax_actions_for_charge_setup', array( $this, 'allow_check_action' ) );

			/**
			 * Save intent in the new order
			 */
			add_action( 'wfocu_offer_new_order_created_before_complete', array( $this, 'maybe_save_intent' ), 10 );

			/**
			 *
			 */
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'enable_gateway_for_the_zero_amount' ) );
			add_filter( 'woocommerce_cart_needs_payment', array( $this, 'cart_needs_payment' ), 10, 2 );

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_stripe_source_to_subscription' ), 10, 3 );
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_stripe_keys_to_copy' ), 10, 1 );


		}

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function control_checkbox_visibility( $is_show ) {
			if ( $this->should_tokenize() && $this->is_enabled() ) {
				return false;
			}

			return $is_show;
		}


		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return boolean on success false otherwise
		 */
		public function has_token( $order ) {
			$this->token = Helper::get_meta( $order,'_fkwcs_source_id' );
			if ( ! empty( $this->token ) ) {
				return true;
			}

			return false;

		}


		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return boolean on success false otherwise
		 */
		public function get_token( $order ) {
			$this->token = Helper::get_meta( $order,'_fkwcs_source_id' );
			if ( ! empty( $this->token ) ) {
				return $this->token;
			}

			return false;

		}


		/**
		 * Generate the request for the payment.
		 *
		 * @param WC_Order $order
		 * @param object $source
		 *
		 * @return array()
		 */
		protected function generate_payment_request( $order, $source ) {
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );

			$gateway               = $this->get_wc_gateway();
			$post_data             = array();
			$post_data['currency'] = strtolower( $order->get_currency() );
			$total                 = Helper::get_stripe_amount( $get_package['total'], $post_data['currency'] );

			if ( $get_package['total'] * 100 < Helper::get_minimum_amount() ) {
				/* translators: 1) dollar amount */
				throw new \Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'funnelkit-stripe-woo-payment-gateway' ), wc_price( Helper::get_minimum_amount() / 100 ) ), 101 );
			}
			$post_data['amount']               = $total;
			$post_data['description']          = sprintf( __( '%1$s - Order %2$s - 1 click upsell: %3$s', 'funnelkit-stripe-woo-payment-gateway' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number(), WFOCU_Core()->data->get( 'current_offer' ));
			$post_data['capture']              = $gateway->capture_method ? 'true' : 'false';
			$billing_first_name                = $order->get_billing_first_name();
			$billing_last_name                 = $order->get_billing_last_name();
			$billing_email                     = $order->get_billing_email();
			$post_data['statement_descriptor'] = $gateway->clean_statement_descriptor( Helper::get_gateway_descriptor() );

			if ( ! empty( $billing_email ) ) {
				$post_data['receipt_email'] = $billing_email;
			}
			$metadata              = array(
				__( 'customer_name', 'funnelkit-stripe-woo-payment-gateway' )  => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
				__( 'customer_email', 'funnelkit-stripe-woo-payment-gateway' ) => sanitize_email( $billing_email ),
				'order_id'                                                     => $this->get_order_number( $order ),
			);
			$post_data['expand[]'] = 'balance_transaction';
			$post_data['metadata'] = apply_filters( 'wc_fkwcs_stripe_payment_metadata', $metadata, $order, $source );

			if ( $source->customer ) {
				$post_data['customer'] = $source->customer;
			}

			if ( $source->source ) {

				$post_data['source']  =  $source->source;
			}

			return apply_filters( 'fkwcs_upsell_stripe_generate_payment_request', $post_data,$get_package, $order, $source );
		}

		protected function create_intent( $order, $prepared_source ) {
			// The request for a charge contains metadata for the intent.
			$full_request = $this->generate_payment_request( $order, $prepared_source );

			$request = array(
				'payment_method'       => $prepared_source->source,
				'amount'               => $full_request['amount'],
				'currency'             => $full_request['currency'],
				'description'          => $full_request['description'],
				'metadata'             => $full_request['metadata'],
				'capture_method'       => ( 'true' === $full_request['capture'] ) ? 'automatic' : 'manual',
				'payment_method_types' => array(
					'card',
				),
			);
			if ( isset( $full_request['statement_descriptor'] ) ) {
				$request['statement_descriptor'] = $full_request['statement_descriptor'];
			}
			if ( $prepared_source->customer ) {
				$request['customer'] = $prepared_source->customer;
			}

			// Create an intent that awaits an action.
			$gateway    = $this->get_wc_gateway();
			$stripe_api = $gateway->get_client();
			$intent     = (object) $stripe_api->payment_intents( 'create', [ $request ] );

			if ( ! empty( $intent->error ) ) {
				WFOCU_Core()->log->log( 'Order #' . $order->get_id() . " - Offer payment intent create failed, Reason: " . print_r( $intent->error, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $intent;
			}

			$order_id = $order->get_id();

			WFOCU_Core()->log->log( '#Order: ' . $order_id . ' Stripe payment intent created. ' . print_r( $intent, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$this->current_intent = $intent;

			return $intent;
		}

		protected function confirm_intent( $intent, $order, $prepared_source ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			if ( 'requires_confirmation' !== $intent->data->status ) {
				return $intent;
			}

			$gateway    = $this->get_wc_gateway();
			$stripe_api = $gateway->get_client();
			$c_intent   = (object) $stripe_api->payment_intents( 'confirm', [ $intent->data->id ] );

			if ( false === $c_intent->success ) {
				return $c_intent;
			}
			$confirmed_intent = $c_intent->data;


			// Save a note about the status of the intent.
			$order_id = $order->get_id();
			if ( 'succeeded' === $confirmed_intent->status ) {

				WFOCU_Core()->log->log( '#Order: ' . $order_id . 'Stripe PaymentIntent ' . $intent->data->id . ' succeeded for order' );

			} elseif ( 'requires_action' === $confirmed_intent->status ) {

				WFOCU_Core()->log->log( '#Order: ' . $order_id . " Stripe PaymentIntent" . $intent->data->id . " requires authentication for order" );
			} else {
				WFOCU_Core()->log->log( '#Order: ' . $order_id . " Stripe PaymentIntent" . $intent->data->id . " confirmIntent Response: " . print_r( $confirmed_intent, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}
			$this->current_intent = $confirmed_intent;

			return $confirmed_intent;
		}

		/**
		 * @param WC_Order $order
		 * @param $balance_transaction_id
		 *
		 * @return void
		 */
		public function update_stripe_fees( $order, $balance_transaction_id ) {
			$stripe              = $this->get_wc_gateway();
			$stripe_api          = $stripe->get_client();
			$response            = $stripe_api->balance_transactions( 'retrieve', [ $balance_transaction_id ] );
			$balance_transaction = $response['success'] ? $response['data'] : false;

			if ( $balance_transaction === false ) {
				return;
			}

			if ( isset( $balance_transaction ) && isset( $balance_transaction->fee ) ) {


				$fee      = ! empty( $balance_transaction->fee ) ? Helper::format_amount( $order->get_currency(), $balance_transaction->fee ) : 0;
				$net      = ! empty( $balance_transaction->net ) ? Helper::format_amount( $order->get_currency(), $balance_transaction->net ) : 0;
				$currency = ! empty( $balance_transaction->currency ) ? strtoupper( $balance_transaction->currency ) : null;

				/**
				 * Handling for Stripe Fees
				 */
				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
				if ( true === $is_batching_on ) {
					$fee  = $fee + Helper::get_stripe_fee( $order );
					$net  = $net + Helper::get_stripe_net( $order );
					$data = [
						'fee'      => $fee,
						'net'      => $net,
						'currency' => $currency,
					];
					Helper::update_stripe_transaction_data( $order, $data );
				}
				WFOCU_Core()->data->set( 'wfocu_stripe_fee', $fee );
				WFOCU_Core()->data->set( 'wfocu_stripe_net', $net );
				WFOCU_Core()->data->set( 'wfocu_stripe_currency', $currency );
			}
		}

		public function maybe_render_in_offer_transaction_scripts() {
			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order ) {
				return;
			}
			remove_filter( 'woocommerce_order_get_payment_method', array( 'FKWCS\Gateway\WC_Stripe_Conversions', 'change_payment_method' ), 99 );

			if ( $this->key !== $order->get_payment_method() ) {
				return;
			}
			add_filter( 'woocommerce_order_get_payment_method', array( 'FKWCS\Gateway\WC_Stripe_Conversions', 'change_payment_method' ), 99, 2 );

			?>
            <script src="https://js.stripe.com/v3/?ver=3.0"></script> <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>

            <script>

                (
                    function ($) {
                        "use strict";
                        var wfocuStripe = Stripe('<?php echo esc_js( $this->get_wc_gateway()->get_client_key() ); ?>');

                        var wfocuStripeJS = {
                            bucket: null,
                            initCharge: function () {
                                var getBucketData = this.bucket.getBucketSendData();

                                var postData = $.extend(getBucketData, {action: 'wfocu_front_handle_fkwcs_stripe_payments'});

                                var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_handle_fkwcs_stripe_payments'), postData);

                                action.done(function (data) {

                                    /**
                                     * Process the response for the call to handle client stripe payments
                                     * first handle error state to show failure notice and redirect to thank you
                                     * */
                                    if (data.result !== "success") {

                                        wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                        if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                            setTimeout(function () {
                                                window.location = data.response.redirect_url;
                                            }, 1500);
                                        } else {
                                            /** move to order received page */
                                            if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                                window.location = wfocu_vars.order_received_url + '&ec=stripe_error';

                                            }
                                        }
                                    } else {

                                        /**
                                         * There could be two states --
                                         * 1. intent confirmed
                                         * 2. requires action
                                         * */

                                        /**
                                         * handle scenario when authentication requires for the payment intent
                                         * In this case we need to trigger stripe payment intent popups
                                         * */
                                        if (typeof data.intent_secret !== "undefined" && '' !== data.intent_secret) {

                                            wfocuStripe.handleCardPayment(data.intent_secret)
                                                .then(function (response) {
                                                    if (response.error) {
                                                        throw response.error;
                                                    }

                                                    if ('requires_capture' !== response.paymentIntent.status && 'succeeded' !== response.paymentIntent.status) {
                                                        return;
                                                    }
                                                    $(document).trigger('wfocuStripeOnAuthentication', [response, true]);
                                                    return;

                                                })
                                                .catch(function (error) {
                                                    $(document).trigger('wfocuStripeOnAuthentication', [false, false]);
                                                    return;

                                                });
                                            return;
                                        }
                                        /**
                                         * If code reaches here means it no longer require any authentication from the client and we process success
                                         * */

                                        wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_success_message_pop, 'type': 'success'});
                                        if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                            setTimeout(function () {
                                                window.location = data.response.redirect_url;
                                            }, 1500);
                                        } else {
                                            /** move to order received page */
                                            if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                                window.location = wfocu_vars.order_received_url + '&ec=stripe_error2';

                                            }
                                        }
                                    }
                                });
                                action.fail(function (data) {
                                    console.log(JSON.stringify(data));

                                    /**
                                     * In case of failure of ajax, process failure
                                     * */
                                    wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                    if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                        setTimeout(function () {
                                            window.location = data.response.redirect_url;
                                        }, 1500);
                                    } else {
                                        /** move to order received page */
                                        if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                            window.location = wfocu_vars.order_received_url + '&ec=stripe_error3';

                                        }
                                    }
                                });
                            }
                        }

                        /**
                         * Handle popup authentication results
                         */
                        $(document).on('wfocuStripeOnAuthentication', function (e, response, is_success) {

                            if (is_success) {
                                var postData = $.extend(wfocuStripeJS.bucket.getBucketSendData(), {
                                    action: 'wfocu_front_handle_fkwcs_stripe_payments',
                                    intent: 1,
                                    intent_secret: response.paymentIntent.client_secret
                                });

                            } else {
                                var postData = $.extend(wfocuStripeJS.bucket.getBucketSendData(), {action: 'wfocu_front_handle_fkwcs_stripe_payments', intent: 1, intent_secret: ''});

                            }
                            var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_handle_fkwcs_stripe_payments'), postData);
                            action.done(function (data) {
                                if (data.result !== "success") {
                                    wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                } else {
                                    wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_success_message_pop, 'type': 'success'});
                                }
                                if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                    setTimeout(function () {
                                        window.location = data.response.redirect_url;
                                    }, 1500);
                                } else {
                                    /** move to order received page */
                                    if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                        window.location = wfocu_vars.order_received_url + '&ec=stripe_error4';

                                    }
                                }
                            });
                        });

                        /**
                         * Save the bucket instance at several
                         */
                        $(document).on('wfocuBucketCreated', function (e, Bucket) {
                            wfocuStripeJS.bucket = Bucket;

                        });
                        $(document).on('wfocu_external', function (e, Bucket) {
                            /**
                             * Check if we need to mark inoffer transaction to prevent default behavior of page
                             */
                            if (0 !== Bucket.getTotal()) {
                                Bucket.inOfferTransaction = true;
                                wfocuStripeJS.initCharge();
                            }
                        });

                        $(document).on('wfocuBucketConfirmationRendered', function (e, Bucket) {
                            wfocuStripeJS.bucket = Bucket;

                        });
                        $(document).on('wfocuBucketLinksConverted', function (e, Bucket) {
                            wfocuStripeJS.bucket = Bucket;

                        });
                    })(jQuery);
            </script>
			<?php
		}

		public function allow_check_action( $actions ) {
			array_push( $actions, 'wfocu_front_handle_fkwcs_stripe_payments' );

			return $actions;
		}

		public function process_client_payment() {

			/**
			 * Prepare and populate client collected data to process further.
			 */
			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );
			WFOCU_Core()->data->set( '_offer_result', true );
			$posted_data = WFOCU_Core()->process_offer->parse_posted_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			/**
			 * return if found error in the charge request
			 */
			if ( false === WFOCU_AJAX_Controller::validate_charge_request( $posted_data ) ) {
				wp_send_json( array(
					'result' => 'error',
				) );
			}


			/**
			 * Setup the upsell to initiate the charge process
			 */
			WFOCU_Core()->process_offer->execute( $get_current_offer_meta );

			$get_order = WFOCU_Core()->data->get_parent_order();

			$stripe = $this->get_wc_gateway();
			$source = $stripe->prepare_order_source( $get_order );

			$intent_from_posted = filter_input( INPUT_POST, 'intent', FILTER_SANITIZE_NUMBER_INT );

			/**
			 * If intent flag set found in the posted data from the client then it means we just need to verify the intent status
			 *
			 */
			if ( ! empty( $intent_from_posted ) ) {


				/**
				 * process response when user either failed or approve the auth.
				 */
				$intent_secret_from_posted = filter_input( INPUT_POST, 'intent_secret' );

				/**
				 * If not found the intent secret with the flag then fail, there could be few security issues
				 */
				if ( empty( $intent_secret_from_posted ) ) {
					$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: Intent secret missing from auth', 'funnelkit-stripe-woo-payment-gateway' ), 'Intent secret missing from auth', $get_order, true );
				}

				/**
				 * get intent ID from the data session
				 */
				$get_intent_id_from_posted_secret = WFOCU_Core()->data->get( 'c_intent_secret_' . $intent_secret_from_posted, '', 'gateway' );
				if ( empty( $get_intent_id_from_posted_secret ) ) {
					$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: Unable to find matching ID for the secret', 'funnelkit-stripe-woo-payment-gateway' ), 'Unable to find matching ID for the secret', $get_order, true );
				}

				/**
				 * Verify the intent from stripe API resource to check if its paid or not
				 */

				$intent = $this->verify_intent( $get_intent_id_from_posted_secret );
				if ( false !== $intent ) {
					$response = end( $intent->data->charges->data );
					WFOCU_Core()->data->set( '_transaction_id', $response->id );
					$this->update_stripe_fees( $get_order, is_string( $response->balance_transaction ) ? $response->balance_transaction : $response->balance_transaction->id );
					wp_send_json( array(
						'result'   => 'success',
						'response' => WFOCU_Core()->process_offer->_handle_upsell_charge( true ),
					) );
				}
				$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: Intent was not authenticated properly.', 'funnelkit-stripe-woo-payment-gateway' ), 'Intent was not authenticated properly.', $get_order, true );

			} else {

				try {

					$intent = $this->create_intent( $get_order, $source );

				} catch ( Exception $e ) {
					/**
					 * If error captured during charge process, then handle as failure
					 */
					$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: ' . $e->getMessage() . '', 'funnelkit-stripe-woo-payment-gateway' ), 'Error Captured: ' . print_r( $e->getMessage() . " <-- Generated on" . $e->getFile() . ":" . $e->getLine(), true ), $get_order, true ); // @codingStandardsIgnoreLine

				}

				/**
				 * Save the is in the session
				 */
				if ( isset( $intent->data->client_secret ) ) {
					WFOCU_Core()->data->set( 'c_intent_secret_' . $intent->data->client_secret, $intent->data->id, 'gateway' );
				}

				WFOCU_Core()->data->save( 'gateway' );

				/**
				 * If all good, go ahead and confirm the intent
				 */
				if ( empty( $intent->error ) ) {
					$intent = $this->confirm_intent( $intent, $get_order, $source );
				}
				if ( isset( $intent->success ) && false === $intent->success ) {
					$note = 'Offer payment failed. Reason: ';
					if ( isset( $intent->message ) && ! empty( $intent->message ) ) {
						$note .= $intent->message;
					}

					$this->handle_api_error( $note, $intent->message, $get_order, true );
				}


				/**
				 * Proceed and check intent status
				 */
				if ( ! empty( $intent ) ) {

					// If the intent requires a 3DS flow, redirect to it.
					if ( 'requires_action' === $intent->status ) {

						/**
						 * return intent_secret as the data to the client so that necesary next operations could taken care.
						 */
						wp_send_json( array(
							'result'        => 'success',
							'intent_secret' => $intent->client_secret,
						) );

					}

					// Use the last charge within the intent to proceed.
					$response = end( $intent->charges->data );

					WFOCU_Core()->data->set( '_transaction_id', $response->id );

					$this->update_stripe_fees( $get_order, is_string( $response->balance_transaction ) ? $response->balance_transaction : $response->balance_transaction->id );

				}
			}


			$data = WFOCU_Core()->process_offer->_handle_upsell_charge( true );

			wp_send_json( array(
				'result'   => 'success',
				'response' => $data,
			) );
		}

		public function verify_intent( $intent_id ) {
			$stripe     = $this->get_wc_gateway();
			$stripe_api = $stripe->get_client();
			$intent     = (object) $stripe_api->payment_intents( 'retrieve', [ $intent_id ] );

			if ( empty( $intent ) ) {
				return false;
			}
			if ( 'succeeded' === $intent->data->status || 'requires_capture' === $intent->data->status ) {
				$this->current_intent = $intent->data;

				return $intent;
			}

			return false;
		}

		/**
		 * Add payout inf to the order created by upsell accept
		 *
		 * @param WC_Order $order
		 * @param Integer $transaction
		 */
		public function add_stripe_payouts_to_new_order( $order ) {

			$data        = [];
			$data['fee'] = WFOCU_Core()->data->get( 'wfocu_stripe_fee' );
			$data['net'] = WFOCU_Core()->data->get( 'wfocu_stripe_net' );

			$data['currency'] = WFOCU_Core()->data->get( 'wfocu_stripe_currency' );
			Helper::update_stripe_transaction_data( $order, $data );
			$order->save_meta_data();
		}


		public function maybe_save_intent( $order ) {

			if ( empty( $this->current_intent ) ) {
				return;
			}
			$this->get_wc_gateway()->save_intent_to_order( $order, $this->current_intent );
			$order->update_meta_data( '_fkwcs_stripe_charge_captured', 'yes' );

		}


		/**
		 * Filter gateways for the zero doller cart, allow our gateway to come
		 *
		 * @param $gateways
		 *
		 * @return array
		 */
		public function enable_gateway_for_the_zero_amount( $gateways ) {
			if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
				return $gateways;
			}

			if ( $this->should_tokenize() && $this->is_enabled() && ! is_null( WC()->cart ) && '0.00' === WC()->cart->get_total( 'edit' ) && isset( $gateways['fkwcs_stripe'] ) ) {
				$gateway_to_filter                 = [];
				$gateway_to_filter['fkwcs_stripe'] = $gateways['fkwcs_stripe'];

				return $gateway_to_filter;
			}

			return $gateways;
		}

		public function cart_needs_payment( $bool ) {

			if ( $this->should_tokenize() && $this->is_enabled() ) {
				return true;
			}

			return $bool;
		}


		/**
		 * Save Subscription details
		 *
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_stripe_source_to_subscription( $subscription, $key, $order ) {

			$source_id = Helper::get_meta( $order,'_fkwcs_source_id' );

			// For BW compat will remove in future.
			if ( empty( $source_id ) ) {
				$source_id = Helper::get_meta( $order,'_fkwcs_card_id' );

				// Take this opportunity to update the key name.
				$subscription->update_meta_data( '_fkwcs_source_id', $source_id );
				$subscription->delete_meta_data( '_fkwcs_card_id' );
			}
			$customer_key = Helper::get_customer_key();

			$subscription->update_meta_data( $customer_key, Helper::get_meta( $order, $customer_key ) );
			$subscription->update_meta_data( '_fkwcs_source_id', $source_id );
			$subscription->save_meta_data();

		}

		public function set_stripe_keys_to_copy( $meta_keys ) {
			array_push( $meta_keys, '_fkwcs_customer_id', '_fkwcs_source_id' );

			return $meta_keys;
		}

		/**
		 * Handling refund offer request from admin
		 *
		 * @throws WC_Stripe_Exception
		 */
		public function process_refund_offer( $order ) {
			$refund_data = wc_clean( $_POST );  // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$txn_id        = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
			$amnt          = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
			$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';

			$order_currency = WFOCU_WC_Compatibility::get_order_currency( $order );

			$client_details = $this->get_wc_gateway()->get_client()->get_clients_details();


			$refund_data = [
				'amount'   => $this->get_wc_gateway()->get_formatted_amount( $amnt, $order_currency ),
				'reason'   => 'requested_by_customer',
				'metadata' => [
					'customer_ip'       => $client_details['ip'],
					'agent'             => $client_details['agent'],
					'referer'           => $client_details['referer'],
					'reason_for_refund' => $refund_reason,
				],
			];
			if ( 0 === strpos( $txn_id, 'pi_' ) ) {

				$refund_data['payment_intent'] = $txn_id;

			} else {

				$refund_data['charge'] = $txn_id;

			}
			$refund_params = apply_filters( 'fkwcs_refund_request_args', $refund_data );

			$response = $this->get_wc_gateway()->execute_refunds( $refund_params );


			$refund_response = $response['success'] ? $response['data'] : false;
			if ( $refund_response ) {
				if ( isset( $refund_response->balance_transaction ) ) {
					Helper::update_balance( $order, $refund_response->balance_transaction, true );

					return isset( $refund_response->id ) ? $refund_response->id : true;
				}

				return true;

			} else {
				$order->add_order_note( __( 'Reason : ', 'funnelkit-stripe-woo-payment-gateway' ) . $refund_reason . '.<br>' . __( 'Amount : ', 'funnelkit-stripe-woo-payment-gateway' ) . get_woocommerce_currency_symbol() . $amnt . '.<br>' . __( ' Status : Failed ', 'funnelkit-stripe-woo-payment-gateway' ) );
				Helper::log( $response['message'] );

				return false;
			}


		}


	}

	WFOCU_Plugin_Integration_Fkwcs_Stripe::get_instance();
}