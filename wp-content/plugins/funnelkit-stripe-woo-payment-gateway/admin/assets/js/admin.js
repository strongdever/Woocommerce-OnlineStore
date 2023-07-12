(function ($) {

    let style = fkwcs_admin_data.settings;
    const icons = fkwcs_admin_data.icons;
    let smart_button_result = {'apple_pay': false, 'google_pay': false};
    if ($('a[href="' + fkwcs_admin_data.site_url + '&tab=fkwcs_api_settings"]').hasClass('nav-tab-active')) {
        $('a[href="' + fkwcs_admin_data.site_url + '&tab=checkout"]').addClass('nav-tab-active');
    }

    if ($('.multiselect').length) {
        $('.multiselect').selectWoo();
    }


    $('.fkwcs-allowed-countries').on('change', switch_countries);


    function switch_countries() {

        const getOption = $('.fkwcs-allowed-countries').val();
        const exceptCountries = $('.fkwcs-except-countries').parents('tr');
        const specificCountries = $('.fkwcs-specific-countries').parents('tr');
        if (getOption === 'all_except') {
            exceptCountries.show();
            specificCountries.hide();
        } else if (getOption === 'specific') {
            exceptCountries.hide();
            specificCountries.show();
        } else {
            exceptCountries.hide();
            specificCountries.hide();
        }
    }

    switch_countries();

    function generateCheckoutDemo() {
        try {
            let iconUrl = '';
            let buttonClass = '';
            let requestType = '';

            const prButton = $('.fkwcs-payment-request-custom-button-render');

            prButton.on('click', function (e) {
                e.preventDefault();
            });

            if ($('.fkwcs_express_checkout_preview_wrapper .fkwcs_express_checkout_preview').length > 0) {
                $('.fkwcs-payment-request-custom-button-admin').show();
                $('.fkwcs_button_preview_label').css({display: 'block'});
                $('.fkwcs_preview_notice').css({display: 'block'});
                $('.fkwcs_express_checkout_preview_wrapper .fkwcs_express_checkout_preview').fadeIn();
                $('.fkwcs_preview_title').html($('#fkwcs_express_checkout_title').val());

                const buttonWidth = $('#fkwcs_express_checkout_button_width').val() ? $('#fkwcs_express_checkout_button_width').val() + 'px' : '100%';
                const buttonWidthOriginVal = $('#fkwcs_express_checkout_button_width').val();

                if (buttonWidthOriginVal > 380) {
                    prButton.css('max-width', buttonWidth);
                    prButton.css('width', '100%');
                } else if ('' !== buttonWidthOriginVal && buttonWidthOriginVal < 101) {
                    prButton.css('width', '112px');
                    prButton.css('min-width', '112px');
                } else {
                    prButton.css('min-width', buttonWidth);
                }

                // Show Apple Pay, GooglePay button for now
                result = {applePay: true, googlePay: true}

                if (result.applePay) {
                    requestType = 'apple_pay';
                    buttonClass = 'fkwcs-express-checkout-applepay-button';
                    iconUrl = 'dark' === style.theme ? icons.applepay_light : icons.applepay_gray;
                    $('.fkwcs-express-checkout-button-icon:first').attr('src', iconUrl);
                }

                if (result.googlePay) {
                    requestType = 'google_pay';
                    buttonClass = 'fkwcs-express-checkout-googlepay-button';
                    iconUrl = 'dark' === style.theme ? icons.gpay_light : icons.gpay_gray;
                    $('.fkwcs-express-checkout-button-icon:last').attr('src', iconUrl);
                }

                removeAllButtonThemes();
                $('.fkwcs-payment-request-custom-button-render').addClass('fkwcs-express-' + requestType);

                $('.fkwcs-payment-request-custom-button-render').addClass(buttonClass + '--' + style.theme);
                $('.fkwcs-payment-request-custom-button-render .fkwcs-express-checkout-button-label').html(style.text);
                $('.fkwcs_express_checkout_preview_wrapper').show();

            }

        } catch (e) {
            console.log(e)
        }
    }

    function removeAllButtonThemes() {
        let btn = $('.fkwcs-payment-request-custom-button-render');
        if (btn.length == 0) {
            return;
        }
        btn.removeClass('fkwcs-express-checkout-payment-button--dark');
        btn.removeClass('fkwcs-express-checkout-payment-button--light');
        btn.removeClass('fkwcs-express-checkout-payment-button--light-outline');
        btn.removeClass('fkwcs-express-checkout-googlepay-button--dark');
        btn.removeClass('fkwcs-express-checkout-googlepay-button--light');
        btn.removeClass('fkwcs-express-checkout-googlepay-button--light-outline');
        btn.removeClass('fkwcs-express-checkout-applepay-button--dark');
        btn.removeClass('fkwcs-express-checkout-applepay-button--light');
        btn.removeClass('fkwcs-express-checkout-applepay-button--light-outline');
    }

    function removeCheckoutPreviewElement() {
        $('.fkwcs_preview_title, .fkwcs_preview_tagline, .fkwcs_preview_notice').remove();
        $('.fkwcs-payment-request-custom-button-admin').css({margin: '0 auto', float: 'none', width: '100%'});
    }

    function toggleOptions() {
        const pages = $('#fkwcs_express_checkout_location option:selected').toArray().map((item) => item.value);

        let page_descriptions = $('#fkwcs_express_checkout_product_page-description');
        if (jQuery.inArray('product', pages) !== -1) {
            $('.fkwcs_product_options').each(function () {
                $(this).parents('tr').show();
            });
            page_descriptions.show();
            page_descriptions.prev('h2').show();
        } else {
            $('.fkwcs_product_options').each(function () {
                $(this).parents('tr').hide();
            });
            page_descriptions.hide();
            page_descriptions.prev('h2').hide();
        }

        if (jQuery.inArray('cart', pages) !== -1) {
            $('.fkwcs_cart_options').each(function () {
                $(this).parents('tr').show();
            });
            page_descriptions.show();
            page_descriptions.prev('h2').show();
        } else {
            $('.fkwcs_cart_options').each(function () {
                $(this).parents('tr').hide();
            });
            page_descriptions.hide();
            page_descriptions.prev('h2').hide();
        }

        if (jQuery.inArray('checkout', pages) !== -1) {
            $('.fkwcs_checkout_options').each(function () {
                $(this).parents('tr').show();
                addCheckoutPreviewElement();
                $('#fkwcs_express_checkout_title').trigger('keyup');
            });
            page_descriptions.show();
            page_descriptions.prev('h2').show();
        } else {
            $('.fkwcs_checkout_options').each(function () {
                $(this).parents('tr').hide();
                removeCheckoutPreviewElement();
            });
            page_descriptions.hide();
            page_descriptions.prev('h2').hide();
        }
    }

    function addCheckoutPreviewElement() {
        removeCheckoutPreviewElement();
    }

    function checkPaymentRequestAvailibility() {

        var stripe = Stripe(fkwcs_admin_data.pub_key);

        var paymentRequest = stripe.paymentRequest({
            country: 'US', currency: 'usd', total: {
                label: 'Demo total', amount: 1099,
            }, requestPayerName: true, requestPayerEmail: true,
        });

        paymentRequest.canMakePayment().then(function (result) {
            if (!result) {
                return;
            }

            if (result.googlePay) {
                smart_button_result.google_pay = result.googlePay;
            }
            if (result.applePay) {
                smart_button_result.apple_pay = result.applePay;
            }
        });

    }

    function HideShowKeys(cond = '') {
        if (cond === true) {
            toggleTestKeys(1);
            toggleLiveKeys(0);
            if (fkwcs_admin_data.is_connected === '') {
                const connectButton = '<button name="connect" class="button-primary" type="button" id="fkwcs_test_connection" data-mode="manual">' + fkwcs_admin_data.test_btn_label + '</button>';
                $('.woocommerce .submit').append(connectButton);
                $('.woocommerce-save-button').hide();
            }

        }


        if (cond === false) {
            $('#fkwcs_test_pub_key').closest('tr').hide();
            $('#fkwcs_test_secret_key').closest('tr').hide();
            $('#fkwcs_pub_key').closest('tr').hide();
            $('#fkwcs_secret_key').closest('tr').hide();
            if (fkwcs_admin_data.is_connected === '') {
                $('#fkwcs_mode').closest('tr').hide();
                $('label[for=fkwcs_webhook_url]').closest('tr').hide();
                $('#fkwcs_live_webhook_secret').closest('tr').hide();
                $('#fkwcs_test_webhook_secret').closest('tr').hide();
                $('#fkwcs_create_webhook_button').closest('tr').hide();
                $('#fkwcs_delete_webhook_button').closest('tr').hide();
                $('#fkwcs_delete_webhook_button').closest('tr').hide();
                $('#fkwcs_debug_log').closest('tr').hide();
                $('#fkwcs_test_connection').closest('tr').hide();
            }
        }
    }

    function toggleLiveKeys(toggle = 0) {
        if (toggle) {
            $('#fkwcs_pub_key').closest('tr').show();
            $('#fkwcs_secret_key').closest('tr').show();
            $('#fkwcs_live_webhook_secret').closest('tr').show();
        } else {
            $('#fkwcs_pub_key').closest('tr').hide();
            $('#fkwcs_secret_key').closest('tr').hide();
            $('#fkwcs_live_webhook_secret').closest('tr').hide();
        }
    }

    function toggleTestKeys(toggle = 0) {
        if (toggle) {
            $('#fkwcs_test_pub_key').closest('tr').show();
            $('#fkwcs_test_secret_key').closest('tr').show();
            $('#fkwcs_test_webhook_secret').closest('tr').show();
        } else {
            $('#fkwcs_test_pub_key').closest('tr').hide();
            $('#fkwcs_test_secret_key').closest('tr').hide();
            $('#fkwcs_test_webhook_secret').closest('tr').hide();
        }
    }

    function show_smart_button_connection() {
        let g = $('#is_google_pay_available')
        if (smart_button_result.google_pay) {
            g.addClass('fkwcs-express-button-active');
            g.children('.fkwcs_btn_connection').html('&#9989;');
        } else {
            g.addClass('fkwcs-express-button-not-active');
        }
        let a = $('#is_apple_pay_available')
        if (smart_button_result.apple_pay) {
            a.addClass('fkwcs-express-button-active');
            a.children('.fkwcs_btn_connection').html('&#9989;');
        } else {
            a.addClass('fkwcs-express-button-not-active');
        }
    }

    if (fkwcs_admin_data.is_connected === '1') {
        $('.fkwcs_connect_btn').closest('tr').hide();
    }

    if (fkwcs_admin_data.is_connected === '' && 'fkwcs_api_settings' === fkwcs_admin_data.fkwcs_admin_settings_tab) {
        $('.woocommerce-save-button').hide();
    }

    if (fkwcs_admin_data.is_manually_connected) {
        HideShowKeys(true);
        setTimeout(function () {
            $("#fkwcs_mode").trigger('change');
        }, 100);
    } else {
        HideShowKeys(false);
        $('.fkwcs_inline_notice').hide();
    }

    $(document).ready(function () {

        generateCheckoutDemo(style);
        toggleOptions();
        checkPaymentRequestAvailibility();

        $('#fkwcs_express_checkout_button_text, #fkwcs_express_checkout_button_theme').change(function () {
            style = {
                text: '' === $('#fkwcs_express_checkout_button_text').val() ? fkwcs_admin_data.messages.default_text : $('#fkwcs_express_checkout_button_text').val(),
                theme: $('#fkwcs_express_checkout_button_theme').val(),
            };
            $('.fkwcs_express_checkout_preview_wrapper .fkwcs-payment-request-custom-button-admin').hide();
            generateCheckoutDemo(style);
        });

        $('#fkwcs_express_checkout_location').change(function () {
            toggleOptions();
        });


        // Forcefully trigger change function to load elements
        $('#fkwcs_express_checkout_button_text, #fkwcs_express_checkout_button_theme').trigger('change');

        $('#fkwcs_express_checkout_title').keyup(function () {
            $('.fkwcs_preview_title').html($(this).val());
        });


        $('#fkwcs_express_checkout_button_width').change(function () {
            let buttonWidth = $(this).val();

            if ('' === buttonWidth) {
                buttonWidth = '100%';
            }
            let button_render = $('.fkwcs-payment-request-custom-button-render');
            if (buttonWidth > 380) {
                button_render.css('max-width', buttonWidth);
                button_render.css('width', '100%');
            } else if (buttonWidth < 100 && '' !== buttonWidth) {
                button_render.css('width', '112px');
                button_render.css('min-width', '112px');
            } else {
                button_render.width(buttonWidth);
            }
        });
        $('button.fkwcs_test_visibility').on('click', function () {
            $('.fkwcs-btn-type-info-wrapper').hide();
            let spinner = $('.fkwcs_express_checkout_connection_div');
            spinner.addClass('fkwcs_show_spinner');

            setTimeout(function () {
                show_smart_button_connection();
                $('.fkwcs-btn-type-info-wrapper').show();
                spinner.removeClass('fkwcs_show_spinner');
            }, 1500, spinner)

        });

    })

    $(document).on('change', '#fkwcs_mode', function (e) {

        let connection_status = $('div.account_status').data('account-connect');
        if ('yes' === connection_status) {
            // Account is connected then return here;
            return;
        }


        if ('test' === $(this).val()) {
            toggleTestKeys(1);
            toggleLiveKeys(0);
        } else {
            toggleTestKeys(0);
            toggleLiveKeys(1);
        }

    });

    $(document).on('click', '#fkwcs_disconnect_acc', function (e) {
        e.preventDefault();
        $.ajax({
            type: 'GET', dataType: 'json', url: fkwcs_admin_data.ajax_url, data: {action: 'fkwcs_disconnect_account', _security: fkwcs_admin_data.fkwcs_admin_nonce}, beforeSend: () => {
                $('body').css('cursor', 'progress');
            }, success(response) {
                if (response.success === true) {
                    const icon = '✔';
                    alert(fkwcs_admin_data.stripe_disconnect + ' ' + icon);
                    window.location.href = fkwcs_admin_data.dashboard_url;
                } else if (response.success === false) {
                    alert(response.data.message);
                }
                $('body').css('cursor', 'default');
            }, error() {
                $('body').css('cursor', 'default');
                alert(fkwcs_admin_data.generic_error);
            },
        });
    });

    $(document).on('click', '#fkwcs_test_connection', function (e) {
        e.preventDefault();
        const fkwcsTestSecretKey = $('#fkwcs_test_secret_key').val();
        const fkwcsSecretKey = $('#fkwcs_secret_key').val();
        const fkwcsTestPubKey = $('#fkwcs_test_pub_key').val();
        const fkwcsPubKey = $('#fkwcs_pub_key').val();
        const messages = [];

        const mode = $('#fkwcs_mode').val();


        if (('test' === mode && '' !== fkwcsTestSecretKey && '' !== fkwcsTestPubKey) || ('live' === mode && '' !== fkwcsSecretKey && '' !== fkwcsPubKey)) {
            $.blockUI({message: ''});
            const mode = ('undefined' === typeof $(this).data('mode')) ? '' : $(this).data('mode');
            $.ajax({
                type: 'GET',
                dataType: 'json',
                url: fkwcs_admin_data.ajax_url,
                data: {action: 'fkwcs_test_stripe_connection', _security: fkwcs_admin_data.fkwcs_admin_nonce, fkwcs_test_sec_key: fkwcsTestSecretKey, fkwcs_secret_key: fkwcsSecretKey},
                beforeSend: () => {
                    $('body').css('cursor', 'progress');
                },
                success(response) {
                    const messages = [];
                    const res = response.data.data;
                    let br = '';
                    let icon = '❌';
                    if (res.live.status !== 'invalid') {
                        if (res.live.status === 'success') {
                            icon = '✔';
                        } else {
                            $('#fkwcs_secret_key').val('');
                            $('#fkwcs_pub_key').val('');
                        }
                        messages.push(res.live.mode + ' ' + icon + '\n' + res.live.message);
                        br = '----\n';
                    } else {
                        if ('manual' !== mode) {
                            messages.push(res.live.mode + ' ' + icon + '\n' + fkwcs_admin_data.stripe_key_unavailable);
                            br = '----\n';
                        }
                        $('#fkwcs_secret_key').val('');
                        $('#fkwcs_pub_key').val('');
                    }
                    icon = '❌';
                    if (res.test.status !== 'invalid') {
                        if (res.test.status === 'success') {
                            icon = '✔';
                        } else {
                            $('#fkwcs_test_secret_key').val('');
                            $('#fkwcs_test_pub_key').val('');
                        }
                        messages.push(br + res.test.mode + ' ' + icon + '\n' + res.test.message);
                    } else {
                        if ('manual' !== mode) {
                            messages.push(br + res.test.mode + ' ' + icon + '\n' + fkwcs_admin_data.stripe_key_unavailable);
                        }
                        $('#fkwcs_test_secret_key').val('');
                        $('#fkwcs_test_pub_key').val('');
                    }
                    $.unblockUI();
                    alert(messages.join('\n'));
                    $('body').css('cursor', 'default');
                    if ('manual' === mode && ('success' === res.live.status || 'success' === res.test.status)) {
                        $('.woocommerce-save-button').trigger('click');
                    }
                },
                error() {
                    $('body').css('cursor', 'default');
                    $.unblockUI();
                    alert(fkwcs_admin_data.stripe_key_error + fkwcs_admin_data.fkwcs_mode);
                },
            });
        } else {
            alert(fkwcs_admin_data.stripe_key_notice);
        }
    });

    $(document).on('click', '.fkwcs_dismiss_notice', function (e) {
        e.preventDefault();
        let notice_id = $(this).data('notice');
        $.ajax({
            type: 'GET', dataType: 'json', url: fkwcs_admin_data.ajax_url, data: {
                'notice_identifier': notice_id, action: 'fkwcs_dismiss_notice', _security: fkwcs_admin_data.fkwcs_admin_nonce
            }, success(response) {
                $('.fkwcs_dismiss_notice_wrap_' + notice_id).remove();
            }, error() {
            },
        });

    });
    $(document).on('click', '.fkwcs_apple_pay_domain_verification', function (e) {
        e.preventDefault();


        $.blockUI({message: ''});
        $.ajax({
            type: 'GET', dataType: 'json', url: fkwcs_admin_data.ajax_url, data: {action: 'fkwcs_apple_pay_domain_verification', _security: fkwcs_admin_data.fkwcs_admin_nonce}, beforeSend: () => {
                $('body').css('cursor', 'progress');
            }, success(response) {
                $.unblockUI();
                alert(response.data.msg);
                $('body').css('cursor', 'default');
                window.location.reload();

            }, error() {
                $('body').css('cursor', 'default');
                $.unblockUI();
                alert(fkwcs_admin_data.stripe_notice_re_verify);
            },
        });

    });

    $(document).on('click', '#fkwcs_create_webhook_button', function (e) {
        e.preventDefault();
        const fkwcsTestSecretKey = $('#fkwcs_test_secret_key').val();
        const fkwcsSecretKey = $('#fkwcs_secret_key').val();
        const fkwcsTestPubKey = $('#fkwcs_test_pub_key').val();
        const fkwcsPubKey = $('#fkwcs_pub_key').val();
        const fkwcsTestWebhookKey = $('#fkwcs_test_webhook_secret').val();
        const fkwcsLiveWebhookKey = $('#fkwcs_live_webhook_secret').val();

        const messages = [];
        const mode = $('#fkwcs_mode').val();


        if (('test' === mode && '' !== fkwcsTestSecretKey && '' !== fkwcsTestPubKey) || ('live' === mode && '' !== fkwcsSecretKey && '' !== fkwcsPubKey)) {
            $.blockUI({message: ''});
            $.ajax({
                type: 'GET', dataType: 'json', url: fkwcs_admin_data.ajax_url, data: {
                    action: 'fkwcs_create_webhook',
                    _security: fkwcs_admin_data.fkwcs_admin_nonce,
                    fkwcs_test_sec_key: fkwcsTestSecretKey,
                    fkwcs_secret_key: fkwcsSecretKey,
                    mode: mode,
                    fkwcs_test_webhook_key: fkwcsTestWebhookKey,
                    fkwcs_live_webhook_secret: fkwcsLiveWebhookKey
                }, beforeSend: () => {
                    $('body').css('cursor', 'progress');
                }, success(response) {
                    $.unblockUI();
                    alert(response.data.msg);
                    $('body').css('cursor', 'default');
                    window.location.reload();
                },
            });
        } else {
            alert(fkwcs_admin_data.stripe_key_notice);
        }
    });

    $(document).on('click', '#fkwcs_delete_webhook_button', function (e) {
        e.preventDefault();
        const fkwcsTestSecretKey = $('#fkwcs_test_secret_key').val();
        const fkwcsSecretKey = $('#fkwcs_secret_key').val();
        const fkwcsTestPubKey = $('#fkwcs_test_pub_key').val();
        const fkwcsPubKey = $('#fkwcs_pub_key').val();

        const messages = [];
        const mode = $('#fkwcs_mode').val();

        if ('test' === mode || 'live' === mode) {
            $.blockUI({message: ''});
            $.ajax({
                type: 'GET',
                dataType: 'json',
                url: fkwcs_admin_data.ajax_url,
                data: {action: 'fkwcs_delete_webhook', _security: fkwcs_admin_data.fkwcs_admin_nonce, fkwcs_mode: mode},
                beforeSend: () => {
                    $('body').css('cursor', 'progress');
                },
                success(response) {
                    let br = '';
                    $.unblockUI();
                    alert(response.data.msg);
                    $('body').css('cursor', 'default');
                    window.location.reload();
                },
                error() {
                    $('body').css('cursor', 'default');
                    $.unblockUI();
                    alert(fkwcs_admin_data.stripe_key_error + fkwcs_admin_data.fkwcs_mode);
                },
            });
        } else {
            alert(fkwcs_admin_data.stripe_key_notice);
        }
    });
}(jQuery));
