(function ($) {

        class FKWCS_Smart_Buttons {
            constructor() {
                this.css_selector = {};
                this.button_id = 'fkwcs_stripe_smart_button';
                this.payment_request = null;
                this.express_request_type = null;
                this.express_button_wrapper = null;
                this.is_product_page = false;
                this.style_value = fkwcs_data.style;
                this.request_data = {};
                this.fkCartRequestData = {};
                this.fkCartExistingRequestData = {};
                /**
                 * Setup data for the product page
                 */
                this.dataCommon = {
                    currency: fkwcs_data.currency, country: fkwcs_data.country_code, requestPayerName: true, requestPayerEmail: true, requestPayerPhone: true
                };
                /**
                 * bail out if stripe public key not configured
                 */
                if ('' === fkwcs_data.pub_key) {
                    return;
                }
                try {
                    this.stripe = Stripe(fkwcs_data.pub_key);
                    this.init();
                } catch (e) {
                    if ('yes' === fkwcs_data.debug_log) {
                        console.log('Stripe Error', e);
                    }
                }
                this.wcEvents();

            }

            init() {

                if ('yes' === fkwcs_data.is_product) {
                    this.request_data = Object.assign(this.dataCommon, {
                        total: fkwcs_data.single_product.total, requestShipping: ('1' === fkwcs_data.shipping_required), displayItems: fkwcs_data.single_product.displayItems,
                    });

                } else if ('yes' === fkwcs_data.is_cart) {
                    this.request_data = Object.assign(this.dataCommon, {
                        total: fkwcs_data.cart_data.order_data.total, requestShipping: ('1' === fkwcs_data.shipping_required), displayItems: fkwcs_data.cart_data.displayItems,

                    });

                }
                this.setupPaymentRequest();

            }


            ajaxEndpoint(action) {
                let url = '';
                if (fkwcs_data.hasOwnProperty('wc_endpoints') && fkwcs_data.wc_endpoints.hasOwnProperty(action)) {
                    url = fkwcs_data.wc_endpoints[action];
                }

                return url;
            }

            setRequestData(data) {
                if (!data.hasOwnProperty('order_data')) {
                    return;
                }
                this.request_data = Object.assign(this.dataCommon, {
                    total: data.order_data.total,
                    currency: data.order_data.currency,
                    country: data.order_data.country_code,
                    requestShipping: data.shipping_required,
                    displayItems: data.order_data.displayItems,
                });

            }

            getRequestData() {
                return this.request_data;
            }

            setupPaymentRequest() {
                let reqData = this.getRequestData();
                if (Object.keys(reqData).length === 0) {
                    return;
                }

                try {

                    let payment_request = this.stripe.paymentRequest(reqData);
                    this.createButton(payment_request);
                    this.productEvents(payment_request);

                    /**
                     * Bind core events
                     */
                    payment_request.canMakePayment().then(this.makePayment.bind(this)).catch(this.makePaymentCatch.bind(this));
                    payment_request.on('paymentmethod', this.onPaymentMethod.bind(this));
                    payment_request.on('shippingaddresschange', this.shippingAddressChange.bind(this));
                    payment_request.on('shippingoptionchange', this.shippingOptionChange.bind(this));
                    payment_request.on('cancel', this.cancelPayment.bind(this));

                } catch (exc) {
                    console.log(exc);
                }
            }

            productEvents(payment_request) {
                let self = this;

                $('.fkwcs_smart_cart_button').off('click').on('click', function (e) {
                    payment_request.show();
                    e.preventDefault();
                });
                $('.fkwcs_smart_checkout_button').off('click').on('click', function (e) {
                    payment_request.show();
                    e.preventDefault();
                });

                let single_add_to_cart_button = $('.fkwcs_smart_product_button');

                single_add_to_cart_button.off('click').on('click', function (e) {

                    /**
                     * prevent process if button disabled on single product page
                     */
                    if ($(this).hasClass('fkwcs_disabled_btn')) {
                        alert('Please Select Options');
                        return;
                    }


                    payment_request.show();
                    e.preventDefault();
                    let response = $.when(self.addToCartProduct());

                });
                $(document.body).off('show_variation').on('show_variation', function () {
                    /**
                     * Enable button after variation selection
                     */
                    single_add_to_cart_button.removeClass('fkwcs_disabled_btn');
                });

                $(document.body).off('hide_variation').on('hide_variation', function () {
                    /**
                     * Disable button after variation selection
                     */
                    single_add_to_cart_button.addClass('fkwcs_disabled_btn');
                });

                $(document.body).off('woocommerce_variation_has_changed').on('woocommerce_variation_has_changed', function () {
                    self.updateSelectedProductsData(payment_request);
                });


                $('form.cart .quantity').off('input').on('input', '.qty', function () {
                    self.updateSelectedProductsData(payment_request);
                });
            }

            updateSelectedProductsData(payment_request) {
                $.when(this.prepareSelectedProductData()).then(function (response) {

                    /**
                     * Trigger error here
                     */
                    if (response.error) {
                        self.showErrorMessage(response.error);
                    } else {

                        /**
                         * update the payment request
                         */
                        $.when(payment_request.update({
                            total: response.total, displayItems: response.displayItems,
                        })).then(function () {
                        });
                    }
                });
            }

            /**
             * Create payment request express buttons
             * @param payment_request
             */
            createButton(payment_request) {
                let elements = this.stripe.elements();
                this.stripe_button = elements.create('paymentRequestButton', {
                    paymentRequest: payment_request
                });

            }

            makePayment(result) {
                let smart_buttons = $('.fkwcs_smart_buttons');
                if (!result) {
                    if ('yes' === fkwcs_data.debug_log) {
                        /**
                         * console log the reason of why the payment buttons are not showing up
                         */
                        console.log(fkwcs_data.debug_msg);
                        smart_buttons.hide();
                    }
                    return;
                }


                /**
                 * declare wrapper elements
                 * @type {*|jQuery|HTMLElement}
                 */

                let express_checkout_button_icon = $('.fkwcs_express_checkout_button_icon');
                let smart_button_wrapper = $('.fkwcs_stripe_smart_button_wrapper');
                let smart_request_separator = $('#fkwcs-payment-request-separator');

                let iconUrl = '';
                if (result.applePay) {
                    this.express_request_type = 'apple_pay';
                    this.express_button_wrapper = 'fkwcs_ec_applepay_button';
                    iconUrl = 'dark' === fkwcs_data.style.theme ? fkwcs_data.icons.applepay_light : fkwcs_data.icons.applepay_gray;

                } else if (result.googlePay) {
                    this.express_request_type = 'google_pay';
                    this.express_button_wrapper = 'fkwcs_ec_googlepay_button';
                    iconUrl = 'dark' === fkwcs_data.style.theme ? fkwcs_data.icons.gpay_light : fkwcs_data.icons.gpay_gray;

                }else{
                    smart_buttons.hide();
                    return;
                }


                this.makeButtonVisibleInline();


                /* Button Styling */
                this.buttonOnProductPage();
                this.buttonOnCartPage();
                this.buttonOnCheckoutPage();


                express_checkout_button_icon.hide();

                smart_buttons.addClass('fkwcs_express_' + this.express_request_type);

                smart_buttons.removeClass('fkwcs_ec_payment_button' + '-' + fkwcs_data.style.theme);
                smart_buttons.addClass(this.express_button_wrapper + '-' + fkwcs_data.style.theme);

                if ('' !== iconUrl) {
                    express_checkout_button_icon.attr('src', iconUrl);
                    express_checkout_button_icon.show();
                }

                if (smart_request_separator.hasClass('cart')) {
                    smart_request_separator.show();
                }

                if (smart_request_separator.hasClass('checkout')) {
                    smart_request_separator.show();
                }

                if (smart_request_separator.hasClass('fkwcs-product')) {
                    if (!smart_button_wrapper.hasClass('inline')) {
                        smart_request_separator.show();
                    }
                }

                if (smart_button_wrapper.length) {
                    smart_button_wrapper.css("display", "block");
                    $(document.body).trigger('fkwcs_smart_buttons_showed', ['stripe']);
                }

                if ('inline' === fkwcs_data.style.button_position && smart_button_wrapper.hasClass('fkwcs-product')) {
                    document.getElementById('fkwcs_stripe_smart_button_wrapper').style.display = 'inline-block';
                }

            }

            /**
             * CB for the payment method selection during express checkout button
             * @param event
             */
            onPaymentMethod(event) {
                let payment_data = this.paymentMethodData(event);
                $.ajax({
                    type: 'POST', data: payment_data, dataType: 'json', url: this.ajaxEndpoint('wc_stripe_create_order'), success: (response) => {
                        if ('success' === response.result) {
                            if (false === this.confirmPaymentIntent(event, response.redirect)) {
                                window.location = response.redirect;
                            }
                        } else {
                            this.abortPayment(event, response.messages);
                        }
                    }
                });
            }

            addToCartProduct() {
                let productId = $('.single_add_to_cart_button').val();
                let single_var = $('.single_variation_wrap');


                /**
                 * Find product ID if its a variable product
                 */
                if (single_var.length) {
                    productId = single_var.find('input[name="product_id"]').val();
                }

                var qtyProduct = $('.quantity .qty').val();
                const productData = {
                    fkwcs_nonce: fkwcs_data.fkwcs_nonce,
                    action: 'add_to_cart',
                    product_id: productId,
                    qty: qtyProduct,
                    attributes: $('.variations_form').length ? this.getVariationAttributes().attributes : [],
                };

                /**
                 * Iterate over the add to cart forms to handle addons data too during request
                 * @type {*|jQuery}
                 */
                const formCartData = $('form.cart').serializeArray();
                $.each(formCartData, function (i, field) {
                    if (/^addon-/.test(field.name)) {
                        if (/\[\]$/.test(field.name)) {
                            const fieldName = field.name.substring(0, field.name.length - 2);
                            if (productData[fieldName]) {
                                productData[fieldName].push(field.value);
                            } else {
                                productData[fieldName] = [field.value];
                            }
                        } else {
                            productData[field.name] = field.value;
                        }
                    } else if (/^fkwcs_plan_/.test(field.name)) {
                        productData[field.name] = field.value;
                    }
                });
                return $.ajax({
                    type: 'POST', data: productData, url: this.ajaxEndpoint('fkwcs_add_to_cart'),
                });
            }


            prepareSelectedProductData() {

                let is_variable_product = $('.single_variation_wrap');
                let product_id = $('.single_add_to_cart_button').val();
                if (is_variable_product.length > 0) {
                    product_id = $('.single_variation_wrap').find('input[name="product_id"]').val();
                }

                let product_addons = $('#product-addons-total');
                let addon_price_value = 0;
                if (product_addons.length > 0) {
                    let addons_price_data = product_addons.data('price_data') || [];
                    addon_price_value = addons_price_data.reduce(function (sum, single) {
                        return sum + single.cost;
                    }, 0);
                }

                const data = {
                    fkwcs_nonce: fkwcs_data.fkwcs_nonce,
                    product_id: product_id,
                    qty: $('.quantity .qty').val(),
                    addon_value: addon_price_value,
                    attributes: $('.variations_form').length ? this.getVariationAttributes().attributes : [],

                };

                return $.ajax({
                    type: 'POST', data: data, url: this.ajaxEndpoint('fkwcs_selected_product_data'),
                });
            }


            logError(error, order_id = '', failed = false) {
                $.ajax({
                    type: 'POST', url: fkwcs_data.admin_ajax, data: {
                        "action": 'fkwcs_js_errors', "_security": fkwcs_data.js_nonce, "failed": failed, "error": error
                    }
                });
            }

            getVariationAttributes() {


                let variation_forms = $('.variations_form');
                let select_list = variation_forms.find('.variations select');
                let attributes = {};
                let count = 0, chosen = 0;
                select_list.each(function () {
                    let name = $(this).data('attribute_name') || $(this).attr('name');
                    attributes[name] = $(this).val() || '';
                    count++;
                });
                return {
                    count, chosenCount: chosen, attributes,
                };
            }

            /**
             * Prepare Payment method data to pass onto confirm button
             * @param event
             * @returns {*|{billing_last_name: (*|string), billing_phone: (*|string|string), payment_request_type: null, billing_country: (*|string), billing_city: (*|string), fkwcs_nonce: *, billing_company: string, billing_state: (*|string), terms: number, billing_address_1: (*|string), shipping_method: *[], order_comments: string, billing_email: (*|string), billing_address_2: (*|string), billing_postcode: (*|string), fkwcs_source, billing_first_name: (*|string), payment_method: string}}
             * @constructor
             */
            paymentMethodData(event) {

                /**
                 * Gather Data from the chosen method
                 */
                const paymentMethod = event.paymentMethod;
                const billingDetails = paymentMethod.billing_details;
                const email = billingDetails.email;
                const phone = billingDetails.phone;
                const billing = billingDetails.address;
                const name = billingDetails.name;
                const shipping = event.shippingAddress;

                /**
                 * Prepare Data
                 * @type {{billing_last_name: (*|string), billing_phone: (*|string|string), payment_request_type: null, billing_country: (*|string), billing_city: (*|string), fkwcs_nonce: *, billing_company: string, billing_state: (*|string), terms: number, billing_address_1: (*|string), shipping_method: *[], order_comments: string, billing_email: (*|string), billing_address_2: (*|string), billing_postcode: (*|string), fkwcs_source, billing_first_name: (*|string), payment_method: string}}
                 */
                let data = {
                    fkwcs_nonce: fkwcs_data.fkwcs_nonce,
                    billing_first_name: null !== name ? name.split(' ').slice(0, 1).join(' ') : 'test',
                    billing_last_name: null !== name ? name.split(' ').slice(1).join(' ') : 'test',
                    billing_company: '',
                    billing_email: null !== email ? email : event.payerEmail,
                    billing_phone: null !== phone ? phone : event.payerPhone && event.payerPhone.replace('/[() -]/g', ''),
                    order_comments: '',
                    payment_method: 'fkwcs_stripe',
                    terms: 1,
                    fkwcs_source: paymentMethod.id,
                    payment_request_type: this.express_request_type,
                };

                /**
                 * Prepare billing address
                 * @type {*}
                 */
                data = this.prepareBillingAddress(data, billing);
                /**
                 * Prepare Shipping address
                 * @type {*}
                 */
                data = this.prepareShippingAddress(data, shipping);

                /**
                 * If its a checkout page from where the request is getting formed, then loop over form data to combine data
                 */
                if (fkwcs_data.is_checkout) {


                    /**
                     * Here the shippingoption that we get in return from payment request button is the prior one, so we need to set it checked
                     */
                    if (null !== event.shippingOption) {
                        $('input[name="shipping_method[0]"][value="' + event.shippingOption.id + '"]').prop('checked', true);

                    }

                    let formData = $("form[name=checkout]").serializeArray();
                    $.each(formData, function (i, field) {
                        if (false === Object.prototype.hasOwnProperty.call(data, field.name) || '' === data[field.name]) {
                            data[field.name] = field.value;
                        }
                    });
                    data.page_from = 'checkout';
                } else if (fkwcs_data.is_product === 'yes') {
                    data.page_from = 'product';
                } else {
                    data.page_from = 'cart';
                }

                /**
                 * We need to unset the payment token so that payment could be treated as new payment method
                 */
                if (true === Object.prototype.hasOwnProperty.call(data, 'wc-fkwcs_stripe-payment-token')) {
                    delete data['wc-fkwcs_stripe-payment-token'];
                }

                data = JSON.parse(JSON.stringify(data));
                return data;
            }

            /**
             * Prepare Billing Address data using data return by stripe buttons
             * @param address_data
             * @param billing
             * @returns {*}
             */
            prepareBillingAddress(address_data, billing) {
                address_data.billing_country = null !== billing ? billing.country : '';
                address_data.billing_address_1 = null !== billing ? billing.line1 : '';
                address_data.billing_address_2 = null !== billing ? billing.line2 : '';
                address_data.billing_city = null !== billing ? billing.city : '';
                address_data.billing_state = null !== billing ? billing.state : '';
                address_data.billing_postcode = null !== billing ? billing.postal_code : '';

                return address_data;
            }

            /**
             * Prepare Shipping Address data using data return by stripe buttons
             * @param address_data
             * @param shipping_data
             * @returns {*}
             */
            prepareShippingAddress(address_data, shipping_data) {

                if (shipping_data) {
                    address_data.shipping_first_name = shipping_data.recipient.split(' ').slice(0, 1).join(' ');
                    address_data.shipping_last_name = shipping_data.recipient.split(' ').slice(1).join(' ');
                    address_data.shipping_company = shipping_data.organization;
                    address_data.shipping_country = shipping_data.country;
                    address_data.shipping_address_1 = typeof shipping_data.addressLine[0] === 'undefined' ? '' : shipping_data.addressLine[0];
                    address_data.shipping_address_2 = typeof shipping_data.addressLine[1] === 'undefined' ? '' : shipping_data.addressLine[1];
                    address_data.shipping_city = shipping_data.city;
                    address_data.shipping_state = shipping_data.region;
                    address_data.shipping_postcode = shipping_data.postalCode;
                    address_data.ship_to_different_address = 1;
                }
                return address_data;
            }

            /**
             * Cb to handle response from the AJAX request on payment method
             * @param event
             * @param hash
             */
            confirmPaymentIntent(event, hash) {
                let hashpartials = hash.match(/^#?fkwcs-confirm-(pi|si)-([^:]+):(.+)$/);
                if (!hashpartials || 4 > hashpartials.length) {
                    return false;
                }
                let type = hashpartials[1];
                let intentClientSec = hashpartials[2];
                let redirectURI = decodeURIComponent(hashpartials[3]);
                this.confirmPayment(event, intentClientSec, redirectURI, type);
            }

            /**
             * Attempt to confirm the payment intent using Stripe methods
             * @param event
             * @param clientSecret
             * @param redirectURL
             * @param intent_type
             */
            confirmPayment(event, clientSecret, redirectURL, intent_type) {


                let cardPayment = null;
                if ( intent_type == 'si') {
                    cardPayment = this.stripe.handleCardSetup(clientSecret, {});
                } else {
                    cardPayment = this.stripe.confirmCardPayment(clientSecret, {});
                }
                cardPayment.then((result) => {
                    if (result.error) {
                        /**
                         * Insert logs to the server and show error messages
                         */
                        this.logError(result.error);
                        $('.woocommerce-error').remove();
                        $('form.woocommerce-checkout').unblock();
                        $('.woocommerce-notices-wrapper:first-child').html('<div class="woocommerce-error fkwcs-errors">' + result.error.message + '</div>').show();

                        return;
                    }

                    let intent = result[('si' === intent_type) ? 'setupIntent' : 'paymentIntent'];
                    if ('requires_capture' !== intent.status && 'succeeded' !== intent.status) {
                        return;
                    }
                    event.complete('success');
                    $('form.woocommerce-checkout').addClass('processing');
                    $('form.woocommerce-checkout').block({
                        message: null, overlayCSS: {
                            background: '#fff', opacity: 0.6
                        }
                    });
                    window.location = redirectURL;
                });
            }

            abortPayment(event, message) {
                event.complete('fail');
                this.showErrorMessage(message);
            }

            /**
             * Shipping address selection change, responsible for new shipping methods
             * @param event
             * @returns {*}
             */
            shippingAddressChange(event) {
                let address = event.shippingAddress;
                let data = {
                    fkwcs_nonce: fkwcs_data.fkwcs_nonce,
                    country: address.country,
                    state: address.region,
                    postcode: address.postalCode,
                    city: address.city,
                    address: typeof address.addressLine[0] === 'undefined' ? '' : address.addressLine[0],
                    address_2: typeof address.addressLine[1] === 'undefined' ? '' : address.addressLine[1],
                    payment_request_type: this.express_request_type,
                    is_product_page: this.is_product_page,
                };

                return $.ajax({
                    type: 'POST', data: data, url: this.ajaxEndpoint('fkwcs_update_shipping_address'), success: (response) => {
                        if ('success' === response.result) {
                            /**
                             * return back to String FW to show current items along with the status
                             */
                            event.updateWith({
                                status: response.result,total: response.total, shippingOptions: response.shipping_methods, displayItems: response.displayItems
                            });
                            return;
                        }
                        if ('fail' === response.result) {
                            event.updateWith({status: 'fail'});
                        }
                    }
                });
            }

            shippingOptionChange(event) {
                let shippingOption = event.shippingOption;
                const data = {
                    fkwcs_nonce: fkwcs_data.fkwcs_nonce, shipping_method: [shippingOption.id], payment_request_type: this.express_request_type, is_product_page: this.is_product_page,
                };

                return $.ajax({
                    type: 'POST', data: data, url: this.ajaxEndpoint('fkwcs_update_shipping_option'), success: (response) => {
                        if ('success' === response.result) {
                            event.updateWith({
                                status: 'success', total: response.total, displayItems: response.displayItems
                            });
                        }
                        if ('fail' === response.result) {
                            event.updateWith({status: 'fail'});
                        }
                    }
                });
            }

            /**
             * console log error while any error occurred
             * @param error
             */
            makePaymentCatch(error) {
                console.log(error);
            }

            cancelPayment() {
                $(document.body).trigger('fkwcs_express_cancel_payment', this);

            }


            /**
             * Controller for error messages behaviour on multiple environment
             * @param message
             */
            showErrorMessage(message) {
                $('.woocommerce-error').remove();

                if ('no' !== fkwcs_data.is_product) {
                    let element = $('.product').first();
                    element.before(message);
                    window.scrollTo({top: 100, behavior: 'smooth'});
                } else {
                    let $form = $('form.checkout').closest('form');
                    $form.before(message);
                    window.scrollTo({top: 100, behavior: 'smooth'});
                }
            }

            getCartDetails() {
                var data = {
                    fkwcs_nonce: fkwcs_data.fkwcs_nonce,
                };
                let current = this;

                $.ajax({
                    type: 'POST', data: data, url: this.ajaxEndpoint('fkwcs_get_cart_details'), success: (response) => {
                        if (response.success) {
                            /**
                             * return back to String FW to show current items along with the status
                             */
                            current.setRequestData(response.data);
                            current.setupPaymentRequest();
                        }
                    }
                });
            }

            /**
             * WooCommerce events to modify data onto
             */
            wcEvents() {
                let self = this;
                $(document.body).on('updated_checkout', function (e, v) {
                    if ( v && v.fragments ) {
                        self.setRequestData(v.fragments.fkwcs_cart_details);
                        self.setupPaymentRequest();
                    }
                });

                $(document.body).on('updated_cart_totals', function () {
                    self.getCartDetails();
                });


                $(document.body).on('wc_fragments_refreshed added_to_cart removed_from_cart', function () {
                    setTimeout(() => {
                        if (typeof wc_cart_fragments_params === 'undefined') {
                            return false;
                        }
                        if (typeof (Storage) !== "undefined") {
                            if ('yes' === fkwcs_data.is_product) {
                                return;
                            }
                            let json = sessionStorage.getItem(wc_cart_fragments_params.fragment_name);
                            json = JSON.parse(json);
                            if (typeof json == "object") {
                                self.setRequestData(json.fkwcs_cart_details);
                                self.setupPaymentRequest();
                            }
                        }
                    }, 300);
                });


                /**
                 * FK Cart events added here to handle buttons and their data
                 */
                $(document.body).on('fkwcs_express_button_init', () => {
                    this.setupPaymentRequest();
                });

                if ('yes' === fkwcs_data.is_product) {
                    $(document.body).on('fkcart-open', () => {

                        this.fkCartExistingRequestData = JSON.stringify(this.getRequestData());
                        this.setRequestData(this.fkCartRequestData);
                        this.setupPaymentRequest();


                    });

                    $(document.body).on('fkcart-close', () => {
                        let data = JSON.parse(this.fkCartExistingRequestData);
                        if (!data.hasOwnProperty('total')) {
                            return;
                        }
                        this.request_data = data;
                        this.setupPaymentRequest();


                    });
                }


                $(document.body).on('fkcart_fragments_refreshed', (e, v) => {

                    const fkcartSliderModal = $('#fkcart-modal');
                    //return if cart details not found
                    if (typeof v === "undefined" || !v.hasOwnProperty('fkwcs_cart_details')) {
                        return;
                    }

                    if (fkcartSliderModal.hasClass('fkcart-show')) {

                        this.setRequestData(v.fkwcs_cart_details);
                        this.setupPaymentRequest();
                    } else {

                        this.fkCartRequestData = v.fkwcs_cart_details;

                    }


                });

            }

            /**
             * Set Css Property
             * @param selector
             * @param property
             * @param value
             */
            setCss(selector, property, value) {
                if (!this.css_selector.hasOwnProperty(selector)) {
                    this.css_selector[selector] = {};
                }
                this.css_selector[selector][property] = value;
            }

            /**
             * Apply css using css selector object
             */
            applyCss() {
                for (let selector in this.css_selector) {
                    if (Object.keys(this.css_selector[selector]).length === 0) {
                        continue;
                    }
                    for (let property in this.css_selector[selector]) {
                        $(selector).css(property, this.css_selector[selector][property]);
                    }
                }
            }

            /**
             * Controller button to control CSS of the button on single product page
             */
            buttonOnProductPage() {

                /**
                 * bail out if not the product page
                 */
                if (fkwcs_data.is_product_page != 1 || $('form.cart button.single_add_to_cart_button').length === 0) {
                    return;
                }

                let ADCbutton = $('form.cart button.single_add_to_cart_button:visible');
                let Expressbtn = $('.fkwcs_smart_buttons');
                let width = this.style_value.button_length > 10 ? 'min-width' : 'width';
                let addToCartMinWidthType = 'px';

                if ('above' === this.style_value.button_position) {
                    this.setCss('#fkwcs_stripe_smart_button_wrapper', 'width', '100%');
                    this.setCss('#fkwcs-payment-request-separator', 'width', '200px');
                    this.setCss('form.cart button.single_add_to_cart_button', width, '200px');
                    this.setCss('form.cart button.single_add_to_cart_button', 'float', 'left');
                    this.setCss('form.cart', 'display', 'inline-block');
                    this.setCss('.fkwcs_smart_buttons', width, '200px');
                } else {
                    let addToCartMinWidth = ADCbutton.outerWidth() + $('form.cart .quantity').width() + parseInt($('form.cart .quantity').css('marginRight').replace('px', ''));

                    if ('inline' === this.style_value.button_position) {
                        addToCartMinWidth = ADCbutton.outerWidth();
                        addToCartMinWidth = addToCartMinWidth < 120 ? 150 : addToCartMinWidth;

                        if ($('form.cart').width() < 500) {
                            this.makeButtonVisibleInline();
                        }
                        this.setCss('form.cart button.single_add_to_cart_button', width, addToCartMinWidth + 'px');
                    } else {
                        this.setCss('form.grouped_form button.single_add_to_cart_button', width, addToCartMinWidth + 'px');

                        /**
                         * Compatibility with Theme Kadence button
                         * @type {*|jQuery|HTMLElement}
                         */
                        let KDButton = $('.theme-kadence button.single_add_to_cart_button');
                        if (KDButton.length > 0) {
                            addToCartMinWidth = 100;
                            addToCartMinWidthType = '%';
                            this.setCss('.theme-kadence button.single_add_to_cart_button', width, addToCartMinWidth + addToCartMinWidthType);
                            this.setCss('.theme-kadence button.single_add_to_cart_button', 'margin-top', '20px');
                        }
                    }

                    this.setCss('#fkwcs_stripe_smart_button_wrapper', width, addToCartMinWidth + addToCartMinWidthType);
                    this.setCss('form.cart .fkwcs_smart_buttons', width, addToCartMinWidth + addToCartMinWidthType);
                    if ('below' === this.style_value.button_position) {
                        this.setCss('.theme-twentytwentytwo .fkwcs_smart_buttons', width, ADCbutton.outerWidth() + 'px');
                        this.setCss('.theme-twentytwentytwo #fkwcs-payment-request-separator', width, ADCbutton.outerWidth() + 'px');
                    }
                }
                this.applyCss();
                this.expressBtnStyle(Expressbtn, ADCbutton);

            }

            buttonOnCartPage() {
                if (fkwcs_data.is_cart != "yes" || $('.wc-proceed-to-checkout .checkout-button').length == 0) {
                    return;
                }
                const BtnCart = $('.wc-proceed-to-checkout .checkout-button');
                const fkwcsExpressCheckoutButton = $('.fkwcs_smart_buttons');
                if ($('.place-order #place_order').outerHeight() > 30) {
                    this.setCss('.fkwcs_smart_buttons', 'height', BtnCart.outerHeight() + 'px');
                }
                this.setCss('.fkwcs_smart_buttons', 'font-size', BtnCart.css('font-size'));
                this.applyCss();
                this.expressBtnStyle(fkwcsExpressCheckoutButton, BtnCart);

            }

            buttonOnCheckoutPage() {
                let billing_fields = $('.woocommerce-billing-fields');
                if (fkwcs_data.is_checkout != "yes" || billing_fields.length == 0) {
                    return;
                }
                this.setCss('#fkwcs_stripe_smart_button_wrapper', 'max-width', billing_fields.outerWidth(true));
                this.applyCss();
            }


            /**
             * Dynamic CSS for the express checkout button
             * @param smart_button
             * @param wc_button_class
             */
            expressBtnStyle(smart_button, wc_button_class) {

                let style_props = ['padding', 'border-radius', 'box-shadow', 'font-weight', 'text-shadow', 'font-size', 'line-height', 'padding'];
                $.each(style_props, function (k, v) {
                    smart_button.css(v, wc_button_class.css(v));
                });
                smart_button.css('max-height', wc_button_class.outerHeight() + 'px');
            }


            /**
             * Controller method to show button inline
             */
            makeButtonVisibleInline() {

                let addToCartButtonHeight = '';
                let availableWidth = '';
                let wrapper = $('#fkwcs_stripe_smart_button_wrapper');
                if (wrapper.length == 0) {
                    return;
                }
                if (wrapper.hasClass('inline')) {
                    let productWrapper = wrapper.parent();
                    let addToCartButtonElem = productWrapper.children('.single_add_to_cart_button');
                    let quantitySelector = productWrapper.children('.quantity');
                    let totalWidth = productWrapper.outerWidth();
                    let addToCartButtonWidth = addToCartButtonElem.outerWidth();
                    let quantityElemWidth = quantitySelector.outerWidth();
                    availableWidth = totalWidth - (addToCartButtonWidth + quantityElemWidth + 10);
                    this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'marginRight', quantitySelector.css('marginRight'));

                    if (availableWidth > addToCartButtonWidth) {
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'margin', 0);
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'marginRight', quantitySelector.css('marginRight'));
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'clear', 'unset');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper', 'margin', '0px');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper', 'display', 'inline-block');
                    } else {
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'margin', '10px 0');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'flex', 'initial');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'clear', 'both');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .theme-flatsome .cart .quantity', 'width', '100%');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .theme-flatsome .cart .quantity', 'clear', 'both');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper', 'maringTop', '10px');
                        this.setCss('#fkwcs_stripe_smart_button_wrapper', 'display', 'block');
                    }

                    addToCartButtonHeight = addToCartButtonElem.outerHeight();
                    if (addToCartButtonHeight > 60) {
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'height', '60');
                    }
                    if (addToCartButtonHeight < 35) {
                        this.setCss('#fkwcs_stripe_smart_button_wrapper .single_add_to_cart_button', 'height', '35');
                    }

                    $('#fkwcs_stripe_smart_button_wrapper').width(addToCartButtonWidth);
                }
                this.applyCss();
            }


        }

        new

        FKWCS_Smart_Buttons();
    }

)(jQuery);