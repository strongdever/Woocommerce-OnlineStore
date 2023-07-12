/**
 * global fkwcs_data
 * global Stripe
 */
jQuery(function ($) {
    const style = {
        base: {
            padding: '3px',
            iconColor: '#c4f0ff',
            color: '#32325d',
            fontWeight: '500',
            fontFamily: 'Roboto, Open Sans, Segoe UI, sans-serif',
            fontSize: '15px',
            fontSmoothing: 'antialiased',
            '::placeholder': {
                color: '#aab7c4', backgroundColor: '#fff',
            },
            backgroundColor: '#fff'
        }, invalid: {
            iconColor: 'red', color: 'red',
        },
    };

    let wcCheckoutForm = $('form.woocommerce-checkout');
    const homeURL = fkwcs_data.get_home_url;
    const stripeLocalized = fkwcs_data.stripe_localized;

    function scrollToDiv(id) {

        jQuery('html, body').animate({
            scrollTop: jQuery(id).offset().top
        }, 500);


    }

    function getStripeLocalizedMessage(type, message) {
        return (null !== stripeLocalized[type] && undefined !== stripeLocalized[type]) ? stripeLocalized[type] : message;
    }


    class Gateway {
        constructor(stripe, gateway_id) {
            this.gateway_id = gateway_id;
            this.error_container = '.fkwcs-credit-card-error';
            this.gateway_container = '';
            this.stripe = stripe;
            this.mode = 'test';
            this.setup_ready = false;
            this.elements = this.stripe.elements();
            this.setupGateway();
            this.wc_events();
        }

        wc_events() {


            let self = this;
            let token_radio = $(`input[name='wc-${self.gateway_id}-payment-token']:checked`);

            let add_payment_method = $('form#add_payment_method');
            $('form.checkout').on('checkout_place_order_' + this.gateway_id, this.processingSubmit.bind(this));

            if ($('form#order_review').length > 0) {
                $('form#order_review').on('submit', this.processOrderReview.bind(this));
                wcCheckoutForm = $('form#order_review');
            }
            if (add_payment_method.length > 0) {
                add_payment_method.on('submit', this.add_payment_method.bind(this));
                wcCheckoutForm = add_payment_method;
            }

            $('#createaccount').on('change', function () {
                if ($(this).is(':checked')) {
                    $('#fkwcs-save-cc-fieldset').show();
                } else {
                    $('#fkwcs-save-cc-fieldset').hide();
                }
            });
            $(document.body).on('change', 'input[name="payment_method"]', function () {
                self.showError();
                if (self.gateway_id === $(this).val()) {
                    self.resetGateway();
                }
            });
            $(document.body).on('updated_checkout', function () {
                if (self.gateway_id === self.selectedGateway()) {
                    token_radio.trigger('change');
                    self.mountGateway();
                }
            });

            if (document.readyState === 'complete' || document.readyState === 'loading') {
                $(document).ready(function () {
                    if (self.gateway_id === self.selectedGateway()) {
                        self.mountGateway();
                    }
                });
            } else {
                $(window).on('load', function () {
                    if (self.gateway_id === self.selectedGateway()) {
                        self.mountGateway();
                    }
                });
            }

            $(window).on('fkwcs_on_hash_change', this.onHashChange.bind(this));
            $(document.body).on('change', `input[name='wc-${this.gateway_id}-payment-token']`, function () {
                if ('new' !== $(this).val()) {
                    self.hideGatewayContainer();
                } else {
                    self.showGatewayContainer();
                }

            });
            token_radio.trigger('change');
        }

        setupGateway() {

        }


        resetGateway() {

        }


        mountGateway() {

        }

        createSource() {

        }

        processingSubmit(e) {

        }

        processOrderReview(e) {

        }

        add_payment_method(e) {

        }

        hideGatewayContainer() {
            $(this.gateway_container).length > 0 ? $(this.gateway_container).hide() : ''; // jshint ignore:line
        }

        showGatewayContainer() {
            $(this.gateway_container).length > 0 ? $(this.gateway_container).show() : ''; // jshint ignore:line
        }

        appendMethodId(payment_method) {
            let source_el = $('.fkwcs_source');
            if (source_el.length > 0) {
                source_el.remove();
            }
            wcCheckoutForm.append(`<input type='hidden' name='fkwcs_source' class='fkwcs_source' value='${payment_method}'>`);
        }

        getAddress() {
            const billingCountry = document.getElementById('billing_country');
            const billingPostcode = document.getElementById('billing_postcode');
            const billingCity = document.getElementById('billing_city');
            const billingState = document.getElementById('billing_state');
            const billingAddress1 = document.getElementById('billing_address_1');
            const billingAddress2 = document.getElementById('billing_address_2');

            return {
                country: null !== billingCountry ? billingCountry.value : '',
                city: null !== billingCity ? billingCity.value : '',
                postal_code: null !== billingPostcode ? billingPostcode.value : '',
                state: null !== billingState ? billingState.value : '',
                line1: null !== billingAddress1 ? billingAddress1.value : '',
                line2: null !== billingAddress2 ? billingAddress2.value : '',
            };
        }

        getBillingAddress(type) {
            if ($('form#order_review').length > 0) {
                return fkwcs_data.current_user_billing;
            }

            if (typeof type !== undefined && 'add_payment' === type) {
                return {};
            }
            const billingFirstName = document.getElementById('billing_first_name');
            const billingLastName = document.getElementById('billing_last_name');
            const billingEmail = document.getElementById('billing_email');
            const billingPhone = document.getElementById('billing_phone');

            const firstName = null !== billingFirstName ? billingFirstName.value : '';
            const lastName = null !== billingLastName ? billingLastName.value : '';

            return {
                name: firstName + ' ' + lastName, email: null !== billingEmail ? billingEmail.value : '', phone: null !== billingPhone ? billingPhone.value : '', address: this.getAddress(),
            };
        }

        selectedGateway() {
            let el = $('input[name="payment_method"]:checked');
            if (el.length > 0) {
                return el.val();
            }
            return '';
        }

        confirmStripePayment(clientSecret, redirectURL, intent_type, authenticationAlready = false, order_id = false) {
            console.log('Please override in child class');
        }


        onHashChange(e, partials) {


            const type = partials[1];
            const intentClientSecret = partials[2];
            const redirectURL = decodeURIComponent(partials[3]);
            const order_id = decodeURIComponent(partials[4]);
            const payment_method = decodeURIComponent(partials[5]);


            // Cleanup the URL
            if (this.gateway_id === payment_method) {
                this.confirmStripePayment(intentClientSecret, redirectURL, type, order_id);
            }
        }

        showError(error) {

            wcCheckoutForm.removeClass('processing');
            this.unblockElement();
            if (error) {
                $(this.error_container).html(error.message);
            } else {
                $(this.error_container).html('');
            }
        }

        showNotice(message) {
            if (typeof message === 'object') {
                if (message.type === "validation_error") {
                    this.unblockElement();
                    return;
                }
                message = message.message;
            }
            $('.woocommerce-error').remove();
            $('.woocommerce-notices-wrapper').eq(0).html('<div class="woocommerce-error fkwcs-errors">' + message + '</div>').show();
            this.unblockElement();
            scrollToDiv('.woocommerce-notices-wrapper');

        }

        unblockElement() {
            $('form.woocommerce-checkout').unblock();
            $('form#order_review').unblock();
            $('form#add_payment_method').unblock();
        }

        logError(error, order_id = '') {
            let body = $('body');
            $.ajax({
                type: 'POST', url: fkwcs_data.admin_ajax, data: {
                    "action": 'fkwcs_js_errors', "_security": fkwcs_data.js_nonce, "order_id": order_id, "error": error
                }, beforeSend: () => {
                    body.css('cursor', 'progress');
                }, success(response) {
                    if (response.success === false) {
                        return response.message;
                    }
                    body.css('cursor', 'default');
                }, error() {
                    body.css('cursor', 'default');
                },
            });
        }

    }


    class FKWCS_Stripe extends Gateway {
        constructor(stripe, gateway_id) {
            super(stripe, gateway_id);
            this.error_container = '.fkwcs-credit-card-error';

        }


        setupGateway() {

            this.gateway_container = '.fkwcs-stripe-elements-form';
            if (this.isInlineGateway()) {
                this.inLineFields();
            } else {
                this.separateFields();
            }
        }


        isInlineGateway() {
            return ('yes' === fkwcs_data.inline_cc);
        }

        inLineFields() {

            this.card = this.elements.create('card', {
                base: {
                    color: '#32325d',
                }, hidePostalCode: true, iconStyle: 'solid'
            });
            /**
             * display error messages
             */

            this.card.on('change', ({brand, error}) => {
                this.showError();
                if (error) {
                    this.showError(error);
                    return;
                }

                if (brand) {
                    if (this.isAllowedBrand(brand)) {
                        this.showError();
                        return;
                    }
                    if ('unknown' === brand) {
                        $(this.error_cotainer).html('');
                        this.showError();
                    } else {
                        this.showError({'error': fkwcs_data.default_cards[brand] + ' ' + fkwcs_data.not_allowed_string});
                    }
                }
            });
        }

        separateFields() {
            this.cardNumber = this.elements.create('cardNumber', {'style': style});
            this.cardExpiry = this.elements.create('cardExpiry', {'style': style});
            this.cardCvc = this.elements.create('cardCvc', {'style': style});
            /**
             * display error messages
             */
            this.cardNumber.on('change', ({brand, error}) => {
                let card_number_div = $('#fkwcs-stripe-elements-wrapper .fkwcs-credit-card-number');
                let card_icon_holder = $('.fkwcs-stripe-elements-field');

                this.showError();
                if (error) {
                    card_number_div.addClass('haserror');
                    this.showError(error);
                    return;
                }
                card_number_div.removeClass('haserror');
                let imageUrl = fkwcs_data.assets_url + '/icons/card.svg';
                if ('unknown' === brand) {
                    card_icon_holder.removeClass('fkwcs_brand');
                    card_number_div.css("background-image", 'url(' + imageUrl + ')');

                    return;
                }

                if (brand) {

                    if (!this.isAllowedBrand(brand)) {
                        if ('unknown' === brand) {
                            card_icon_holder.removeClass('fkwcs_brand');
                            card_number_div.css("background-image", 'url(' + imageUrl + ')');
                        } else {
                            $('.fkwcs-credit-card-error').html(fkwcs_data.default_cards[brand] + ' ' + fkwcs_data.not_allowed_string);
                        }
                        return;
                    }
                    if (card_number_div.length > 0) {
                        imageUrl = fkwcs_data.assets_url + '/icons/' + brand + '.svg';

                        card_icon_holder.addClass('fkwcs_brand');

                        card_number_div.css("background-image", 'url(' + imageUrl + ')');
                    }
                }
            });
            this.cardExpiry.on('change', ({error}) => {

                if (error) {
                    $('.fkwcs-credit-expiry').addClass('haserror');
                    $('.fkwcs-credit-expiry-error').html(error.message);
                } else {
                    $('.fkwcs-credit-expiry-error').html('').removeClass('haserror');
                }
            });
            this.cardCvc.on('change', ({error}) => {
                if (error) {
                    $('.fkwcs-credit-cvc-error').html(error.message);
                    $('.fkwcs-credit-cvc').addClass('haserror');
                } else {
                    $('.fkwcs-credit-cvc-error').html('').removeClass('haserror');
                }
            });
        }


        resetGateway() {

            if (this.isInlineGateway()) {
                if (null !== this.card) {
                    this.card.unmount();
                }
            } else {
                if (null !== this.cardNumber) {
                    console.trace();
                    this.cardNumber.unmount();
                    this.cardExpiry.unmount();
                    this.cardCvc.unmount();
                }
            }
            this.mountGateway();
        }

        mountGateway() {

            this.mountCard();

        }

        mountCard() {
            $('.fkwcs-stripe-elements-wrapper').show();
            if (this.isInlineGateway()) {
                if (!$('.fkwcs-stripe-elements-wrapper .fkwcs-credit-card-field').html() && null !== this.card) {
                    this.card.mount('.fkwcs-stripe-elements-wrapper .fkwcs-credit-card-field');
                }
                return;
            }
            if (!this.isInlineGateway() && null !== this.cardNumber) {
                this.cardNumber.mount('.fkwcs-stripe-elements-wrapper .fkwcs-credit-card-number');
                this.cardExpiry.mount('.fkwcs-stripe-elements-wrapper .fkwcs-credit-expiry');
                this.cardCvc.mount('.fkwcs-stripe-elements-wrapper .fkwcs-credit-cvc');
            }
        }

        getCardElement() {
            let card_element = null;
            if (this.isInlineGateway()) {
                card_element = this.card;
            } else {
                card_element = this.cardNumber;
            }
            return card_element;
        }

        createSource(type) {
            wcCheckoutForm.block({
                message: null, overlayCSS: {
                    background: '#fff', opacity: 0.6
                }
            });


            this.stripe.createPaymentMethod({
                type: 'card', card: this.getCardElement(), billing_details: this.getBillingAddress(type),
            }).then((response) => {

                this.handleSourceResponse(response);
            });
        }

        handleSourceResponse(response) {
            if (response.error) {
                this.showNotice(response.error);
                return;
            }
            if (response.paymentMethod) {
                this.appendMethodId(response.paymentMethod.id);
                if ($('form#order_review').length && 'yes' === fkwcs_data.is_change_payment_page) {
                    this.create_setup_intent(response.paymentMethod.id, $('form#order_review'));
                } else if ($('form#add_payment_method').length) {
                    this.create_setup_intent(response.paymentMethod.id, $('form#add_payment_method'));
                } else {
                    if ($('form#order_review').length > 0) {
                        $('form#order_review').trigger('submit');
                    } else {
                        $('form.checkout').trigger('submit');

                    }
                }
            }
        }

        create_setup_intent(payment_method, form_el) {
            let process_data = {
                'action': 'fkwcs_create_setup_intent', 'fkwcs_nonce': fkwcs_data.fkwcs_nonce, 'fkwcs_source': payment_method
            };

            $.ajax({
                type: 'POST', dataType: 'json', url: fkwcs_data.admin_ajax, data: process_data, beforeSend: () => {
                    $('body').css('cursor', 'progress');
                }, success: (response) => {
                    if (response.status === 'success') {
                        const clientSecret = response.data.client_secret;
                        let confirmSetup = this.stripe.confirmCardSetup(clientSecret, {payment_method: payment_method});
                        confirmSetup.then((resp) => {
                            if (resp.hasOwnProperty('error')) {
                                form_el.unblock();
                                this.showNotice(resp.error);

                                return;
                            }

                            form_el.trigger('submit');
                        });
                        confirmSetup.catch((error) => {
                            form_el.unblock();
                            console.log('error', error);

                        });

                    } else if (response.status === false) {
                        return false;
                    }
                    $('body').css('cursor', 'default');
                }, error() {
                    $('body').css('cursor', 'default');
                    alert('Something went wrong!');
                },
            });
        }


        confirmStripePayment(clientSecret, redirectURL, intent_type, order_id = false) {


            let cardPayment = null;
            if ('si' == intent_type) {
                cardPayment = this.stripe.handleCardSetup(clientSecret, {});
            } else {
                cardPayment = this.stripe.confirmCardPayment(clientSecret, {});
            }

            cardPayment.then((result) => {
                if (result.error) {
                    this.showNotice(result.error);
                    let source_el = $('.fkwcs_source');
                    if (source_el.length > 0) {
                        source_el.remove();
                    }

                    if (result.error.hasOwnProperty('type') && result.error.type === 'api_connection_error') {
                        return;
                    }
                    this.logError(result.error, order_id);


                } else {

                    let intent = result[('si' === intent_type) ? 'setupIntent' : 'paymentIntent'];
                    if ('requires_capture' !== intent.status && 'succeeded' !== intent.status) {
                        return;
                    }
                    window.location = redirectURL;
                }
            }).catch(function (error) {

                // Report back to the server.
                $.get(redirectURL + '&is_ajax');
            });
        }


        hasSource() {
            let saved_source = $('input[name="wc-fkwcs_stripe-payment-token"]:checked');
            if (saved_source.length > 0 && 'new' !== saved_source.val()) {
                return saved_source.val();
            }

            let source_el = $('.fkwcs_source');
            if (source_el.length > 0) {
                return source_el.val();
            }

            return '';
        }

        processingSubmit(e) {

            let source = this.hasSource();
            if ('' === source) {
                this.createSource('submit');
                e.preventDefault();
                return false;
            }

        }

        processOrderReview(e) {

            if (this.gateway_id === this.selectedGateway()) {


                let source = this.hasSource();

                if ('' === source) {
                    this.createSource('order_review');
                    e.preventDefault();
                    return false;
                }
            }
        }

        add_payment_method(e) {
            let source = this.hasSource();
            if ('' === source) {
                this.createSource('add_payment');
                e.preventDefault();
                return false;
            }
        }

        onEarlyRenewalSubmit(e) {
            e.preventDefault();

            $.ajax({
                url: $('#early_renewal_modal_submit').attr('href'), method: 'get', success: (html) => {
                    var response = JSON.parse(html);
                    if (response.fkwcs_stripe_sca_required) {
                        this.confirmStripePayment(response.intent_secret, response.redirect_url);
                    } else {
                        window.location = response.redirect_url;
                    }
                },
            });

            return false;
        }

        isAllowedBrand(brand) {
            if (0 === fkwcs_data.allowed_cards.length) {
                return false;
            }

            return (-1 === $.inArray(brand, fkwcs_data.allowed_cards)) ? false : true;
        }

        wc_events() {
            super.wc_events();
            /**
             * If this is the change payment or a pay page we need to trigger the tokenization form
             */
            if ('yes' === fkwcs_data.is_change_payment_page || 'yes' === fkwcs_data.is_pay_for_order_page) {

                $(document.body).trigger('wc-credit-card-form-init');


                /**
                 * IN case of SCA payments we need to trigger confirmStripePayment as hash change will not fire auto
                 * @type {RegExpMatchArray}
                 */
                let partials = window.location.hash.match(/^#?fkwcs-confirm-(pi|si)-([^:]+):(.+):(.+)$/);
                if (null == partials) {
                    partials = window.location.hash.match(/^#?fkwcs-confirm-(pi|si)-([^:]+):(.+)$/);
                }
                if (partials) {
                    const type = partials[1];
                    const intentClientSecret = partials[2];
                    const redirectURL = decodeURIComponent(partials[3]);
                    const order_id = decodeURIComponent(partials[4]);


                    // Cleanup the URL
                    this.confirmStripePayment(intentClientSecret, redirectURL, type, order_id);
                }


            }
            // Subscription early renewals modal.
            if ($('#early_renewal_modal_submit[data-payment-method]').length) {
                $('#early_renewal_modal_submit[data-payment-method=fkwcs_stripe]').on('click', this.onEarlyRenewalSubmit.bind(this));
            } else {
                $('#early_renewal_modal_submit').on('click', this.onEarlyRenewalSubmit.bind(this));
            }
            $(document.body).on('change', '.woocommerce-SavedPaymentMethods-tokenInput', function () {
                let name = $(this).attr('name');
                let el = $('.fkwcs-stripe-elements-wrapper');
                if (name == 'wc-fkwcs_stripe-payment-token') {
                    let vl = $(this).val();
                    if ('new' == vl) {
                        el.show();
                    } else {
                        el.hide();
                    }

                } else {
                    el.show();
                }
            });
        }


    }

    class FKWCS_P24 extends Gateway {
        constructor(stripe, gateway_id) {
            super(stripe, gateway_id);
            this.selectedP24Bank = '';
            this.error_container = '.fkwcs_stripe_p24_error';
        }

        setupGateway() {
            let self = this;
            this.p24 = this.elements.create('p24Bank', {"style": style});
            this.p24.on('change', function (event) {
                self.selectedP24Bank = event.value;
                self.showError();
            });
        }

        resetGateway() {
            this.p24.unmount();
            this.mountGateway();
        }

        mountGateway() {
            let p24_form = $('.fkwcs_stripe_p24_form');
            if (0 === p24_form.length) {
                return;
            }
            p24_form.show();
            let selector = '.fkwcs_stripe_p24_form .fkwcs_stripe_p24_select';
            this.p24.mount(selector);
            $(selector).css({backgroundColor: '#fff'});

        }

        processingSubmit(e) {
            // check for P24.
            if ('' === this.selectedP24Bank) {
                this.showError({error: fkwcs_data.empty_bank_message});
                this.showNotice(fkwcs_data.empty_bank_message);
                return false;
            }
            this.showError('');
        }

        confirmStripePayment(clientSecret, redirectURL, intent_type, authenticationAlready = false, order_id = false) {

            if (this.gateway_id === this.selectedGateway()) {
                this.stripe.confirmP24Payment(clientSecret, {
                    payment_method: {
                        billing_details: this.getBillingAddress(),
                    }, return_url: homeURL + redirectURL,
                });
            }

        }


    }

    class FKWCS_Sepa extends Gateway {
        constructor(stripe, gateway_id) {
            super(stripe, gateway_id);
            this.error_container = '.fkwcs_stripe_sepa_error';

            this.sepaIBAN = false;
            this.paymentMethod = '';
            this.emptySepaIBANMessage = fkwcs_data.empty_sepa_iban_message;
        }

        setupGateway() {
            let self = this;
            this.gateway_container = '.fkwcs_stripe_sepa_payment_form';

            let sepaOptions = Object.keys(fkwcs_data.sepa_options).length ? fkwcs_data.sepa_options : {};
            this.sepa = this.elements.create('iban', sepaOptions);
            this.sepa.on('change', ({error}) => {
                if (this.isSepaSaveCardChosen()) {
                    return true;
                }
                if (error) {
                    self.sepaIBAN = false;
                    self.emptySepaIBANMessage = error.message;

                    self.showError(error);
                    self.logError(error);
                    return;
                }
                this.sepaIBAN = true;
                self.showError('');

            });
            this.setup_ready = true;
        }

        resetGateway() {
            this.sepa.unmount();
            this.mountGateway();
        }

        mountGateway() {
            if (false === this.setup_ready) {
                return;

            }
            if (0 === $('.payment_method_fkwcs_stripe_sepa').length) {
                return false;
            }

            this.sepa.mount('.fkwcs_stripe_sepa_iban_element_field');
            $('.fkwcs_stripe_sepa_payment_form .fkwcs_stripe_sepa_iban_element_field').css({backgroundColor: '#fff', borderRadius: '3px'});
        }

        isSepaSaveCardChosen() {
            return ($('#payment_method_fkwcs_stripe_sepa').is(':checked') && $('input[name="wc-fkwcs_stripe_sepa-payment-token"]').is(':checked') && 'new' !== $('input[name="wc-fkwcs_stripe_sepa-payment-token"]:checked').val());
        }

        processingSubmit(e) {
            if ('' === this.paymentMethod && !this.isSepaSaveCardChosen()) {
                if (false === this.sepaIBAN) {
                    this.showError(this.emptySepaIBANMessage);
                    return false;
                }


                this.createPaymentMethod();
                return false;
            }
        }

        processOrderReview(e) {
            if (this.gateway_id === this.selectedGateway()) {
                if ('' === this.paymentMethod && !this.isSepaSaveCardChosen()) {
                    this.createPaymentMethod();
                    return false;
                }
            }
        }

        createPaymentMethod() {
            /**
             * @todo
             * need return true if cart total is 0
             */

            this.stripe.createPaymentMethod({
                type: 'sepa_debit', sepa_debit: this.sepa, billing_details: this.getBillingAddress(),
            }).then((result) => {

                if (result.error) {
                    this.logError(result.error);

                    this.showNotice(getStripeLocalizedMessage(result.error.code, result.error.message));
                    return;
                }

                // Handle result.error or result.paymentMethod
                if (result.paymentMethod) {
                    wcCheckoutForm.find('.fkwcs_payment_method').remove();
                    this.paymentMethod = result.paymentMethod.id;
                    this.appendMethodId(this.paymentMethod);
                    wcCheckoutForm.trigger('submit');
                }
            });

        }


        confirmStripePayment(clientSecret, redirectURL, intent_type, authenticationAlready = false, order_id = false) {
            if (this.gateway_id !== this.selectedGateway()) {
                return;
            }

            if (this.isSepaSaveCardChosen() || authenticationAlready) {
                this.stripe.confirmSepaDebitPayment(clientSecret, {}).then((result) => {
                    if (result.error) {
                        this.logError(result.error, order_id);

                        this.showNotice(getStripeLocalizedMessage(result.error.code, result.error.message));
                        return;
                    }

                    // The payment has been processed!
                    if (result.paymentIntent.status === 'succeeded' || result.paymentIntent.status === 'processing') {
                        $('.woocommerce-error').remove();
                        window.location = redirectURL;
                    }

                });
            } else {
                this.stripe.confirmSepaDebitPayment(clientSecret, {
                    payment_method: {
                        sepa_debit: this.sepa, billing_details: this.getBillingAddress()
                    },
                }).then((result) => {
                    if (result.error) {
                        this.logError(result.error);
                        this.showNotice(getStripeLocalizedMessage(result.error.code, result.error.message));
                        return;
                    }


                    // The payment has been processed!
                    if (result.paymentIntent.status === 'succeeded' || result.paymentIntent.status === 'processing') {
                        $('.woocommerce-error').remove();
                        window.location = redirectURL;
                    }
                });
            }

        }
    }


    class FKWCS_Ideal extends Gateway {
        constructor(stripe, gateway_id) {
            super(stripe, gateway_id);
            this.selectedIdealBank = '';
            this.error_cotainer = '.fkwcs_stripe_ideal_error';
        }

        setupGateway() {
            this.ideal = this.elements.create('idealBank', {"style": style});
            this.ideal.on('change', (event) => {
                this.selectedIdealBank = event.value;
                this.showError();
            });

        }

        resetGateway() {
            this.ideal.unmount('.fkwcs_stripe_ideal_form .fkwcs_stripe_ideal_select');
            this.mountGateway();
        }

        mountGateway() {
            let ideal_form = $('.fkwcs_stripe_ideal_form');
            if (0 === ideal_form.length) {
                return;
            }
            ideal_form.show();
            this.ideal.mount('.fkwcs_stripe_ideal_form .fkwcs_stripe_ideal_select');
            $('.fkwcs_stripe_ideal_form .fkwcs_stripe_ideal_select').css({backgroundColor: '#fff'});
        }

        processingSubmit(e) {


            if ('' === this.selectedIdealBank) {
                this.showError({error: fkwcs_data.empty_bank_message});
                this.showNotice(fkwcs_data.empty_bank_message);
                return false;
            }
            this.showError();

        }


        confirmStripePayment(clientSecret, redirectURL, intent_type, authenticationAlready = false, order_id = false) {

            if (this.gateway_id === this.selectedGateway()) {
                let ideal = this.ideal;
                this.stripe.confirmIdealPayment(clientSecret, {
                    payment_method: {
                        ideal, billing_details: this.getBillingAddress(),
                    }, return_url: homeURL + redirectURL,
                }).then((result) => {
                    if (result.error) {
                        // Show error to your customer (e.g., insufficient funds)
                        this.logError(result.error, order_id);
                        this.showNotice(getStripeLocalizedMessage(result.error.code, result.error.message));
                    }
                });
            }

        }

    }

    class FKWCS_BanContact extends Gateway {
        constructor(stripe, gateway_id) {
            super(stripe, gateway_id);
            this.error_container = `.fkwcs_stripe_bancontact_error`;
        }

        confirmStripePayment(clientSecret, redirectURL, intent_type, authenticationAlready = false, order_id = false) {
            if (this.gateway_id === this.selectedGateway()) {
                this.stripe.confirmBancontactPayment(clientSecret, {
                    payment_method: {
                        billing_details: this.getBillingAddress(),
                    }, return_url: homeURL + redirectURL,
                }).then(({error}) => {
                    if (!error) {
                        return;
                    }
                    this.showError(error);
                    this.logError(error, order_id);
                    this.showNotice(getStripeLocalizedMessage(error.code, error.message));
                });
            }

        }


    }

    function init_gateways() {

        const pubKey = fkwcs_data.pub_key;
        const mode = fkwcs_data.mode;
        if ('' === pubKey || ('live' === mode && !fkwcs_data.is_ssl)) {
            console.log('Live Payment Mode only work only https protocol ');
            return;
        }

        try {
            const stripe = Stripe(pubKey, {locale: fkwcs_data.locale});
            new FKWCS_Stripe(stripe, 'fkwcs_stripe');
            new FKWCS_P24(stripe, 'fkwcs_stripe_p24');
            new FKWCS_Sepa(stripe, 'fkwcs_stripe_sepa');
            new FKWCS_Ideal(stripe, 'fkwcs_stripe_ideal');
            new FKWCS_BanContact(stripe, 'fkwcs_stripe_bancontact');
        } catch (e) {
            console.log(e);
        }


        window.addEventListener('hashchange', function () {

            let partials = window.location.hash.match(/^#?fkwcs-confirm-(pi|si)-([^:]+):(.+):(.+):(.+)$/);
            if (null == partials) {
                partials = window.location.hash.match(/^#?fkwcs-confirm-(pi|si)-([^:]+):(.+)$/);
            }
            if (!partials || 4 > partials.length) {
                return;
            }

            history.pushState({}, '', window.location.pathname);
            $(window).trigger('fkwcs_on_hash_change', [partials]);
        });
    }

    init_gateways();
});