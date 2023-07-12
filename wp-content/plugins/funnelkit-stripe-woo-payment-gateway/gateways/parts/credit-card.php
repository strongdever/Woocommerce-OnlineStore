<?php

if ( ! empty( $this->description ) ) {

	echo '<div class="fkwcs-test-description"><p>';
	wptexturize( $this->description );  //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
	echo '</p></div>';
}

$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && is_user_logged_in(); //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
if ( $display_tokenization ) {
	?>
    <div class="fkwcs-saved-payment-methods">
		<?php
		$this->saved_payment_methods(); //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
		?>
    </div>
	<?php
}
?>
<div class="fkwcs-stripe-elements-wrapper" id="fkwcs-stripe-elements-wrapper">
    <div id="fkwcs-stripe-elements-form" class="fkwcs-stripe-elements-form">
		<?php

		if ( 'yes' === $this->inline_cc ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
			?>
            <div class="fkwcs-credit-card-field fkwcs-stripe-elements-field"></div>
            <div class="fkwcs-credit-card-error fkwcs-error-text"></div>
			<?php
		} else {
			?>
            <div class="fkwcs-credit-card-field">

                <div class="fkwcs-form-row fkwcs-form-row-wide">
                    <label for="stripe-card-element"><?php esc_html_e( 'Card number', 'woocommerce' ); ?> <span class="required">*</span></label>
                    <div class="fkwcs-stripe-card-group fkwcs-credit-card-number-group">
                        <div class="fkwcs-credit-card-number fkwcs-stripe-elements-field"></div>
                    </div>
                    <div class="fkwcs-credit-card-error fkwcs-error-text"></div>
                </div>

                <div class="fkwcs-form-row fkwcs-form-row-wide fkwcs-field-wrapper">
                    <div class="fkwcs-form-row-first">
                        <label for="stripe-card-expiry"><?php esc_html_e( 'Expiry date', 'woocommerce' ); ?> <span class="required">*</span></label>
                        <div class="fkwcs-card-exp fkwcs-stripe-card-group">
                            <div class="fkwcs-credit-expiry fkwcs-stripe-elements-field"></div>
                            <span class="fkwcs-icon" data-tip="Enter Credit Card Expiry"></span>
                        </div>
                        <div class="fkwcs-credit-expiry-error fkwcs-error-text"></div>
                    </div>
                    <div class="fkwcs-form-row-last">
                        <label for="stripe-card-cvc"><?php esc_html_e( 'CVC', 'woocommerce' ); ?> <span class="required">*</span></label>
                        <div class="fkwcs-card-exp fkwcs-stripe-card-group">
                            <div class="fkwcs-credit-cvc-wrapper">
                                <div class="fkwcs-credit-cvc fkwcs-stripe-elements-field"></div>
                                <span class="fkwcs-icon fkwcs-question-icon fkwcs-tooltip" data-tip="<?php esc_html_e( '3 digit Security Code usually found on the back of your card. American Express Cards have 4 digit code usually found on the front.', 'funnelkit-stripe-woo-payment-gateway' ) ?>"></span>
                            </div>
                        </div>
                        <div class="fkwcs-credit-cvc-error fkwcs-error-text"></div>
                    </div>
                </div>

            </div>
			<?php
		}
		if ( apply_filters( 'fkwcs_stripe_display_save_payment_method_checkbox', $display_tokenization ) && 'yes' === $this->enable_saved_cards && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable,WordPress.Security.NonceVerification.Recommended
			echo '<div class="fkwcs-form-row fkwcs-form-row-wide"><fieldset id="fkwcs-save-cc-fieldset">';
			echo '<span class="fkwcs-save-cards">';
			echo '<label><input type="checkbox" name="wc-fkwcs_stripe-new-payment-method" value="on"/>' . wp_kses_post( apply_filters( 'fkwcs_saved_cards_label', __( 'Save payment information to my account for future purchases.', 'funnelkit-stripe-woo-payment-gateway' ) ) );
			echo '</label></span>';
			echo '</fieldset></div>';
		}

		if ( 'test' === $this->test_mode ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
			echo '<div class="fkwcs-test-description"><p>';
			echo wp_kses_post( $this->get_test_mode_description() ); //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
			echo '</p></div>';
		}

		?>


    </div>
</div>