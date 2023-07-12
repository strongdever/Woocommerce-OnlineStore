<?php
/**
 * @var $value []
 */
$label   = __( 'Connect with Stripe', 'funnelkit-stripe-woo-payment-gateway' );
$sec_var = '';

?>
<tr valign="top">
    <th scope="row">
        <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
    </th>
    <td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
        <fieldset>
            <a class="fkwcs_connect_btn" href="<?php echo esc_url( $this->get_connect_url() . $sec_var ); ?>">
                <span><?php echo esc_html( $label ); ?></span>
            </a>
            <div class="wc-connect-stripe-help">
				<?php
				/* translators: %1$1s, %2$2s: HTML Markup */
				echo wp_kses_post( sprintf( __( 'Need help connecting with Stripe?  %1$s Read document. %2$s', 'funnelkit-stripe-woo-payment-gateway' ), '<a href="' . esc_url( 'https://funnelkit.com/docs/stripe-gateway-for-woocommerce/getting-started/setup/' ) . '" target="_blank">', '</a>' ) );
				?>
            </div>
			<?php

			if ( isset( $_GET['connect'] ) && 'manually' === $_GET['connect'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
                <div class="notice inline notice-warning fkwcs_inline_notice" style="margin: 15px 0 -10px">
                    <p><?php esc_html_e( 'Although you can add your API keys manually, we recommend using Stripe Connect. Stripe Connect prevents issues that can arise when copying and pasting account details from Stripe into Funnelkit Stripe gateway.', 'funnelkit-stripe-woo-payment-gateway' ); ?></p>
                </div>
			<?php } ?>
        </fieldset>
    </td>
</tr>
