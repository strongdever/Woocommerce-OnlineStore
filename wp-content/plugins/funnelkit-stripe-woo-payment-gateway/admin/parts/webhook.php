<?php
/**
 * @var $value []
 */
$label   = __( 'Create Webhook', 'funnelkit-stripe-woo-payment-gateway' );
$sec_var = '';
?>
<tr valign="top">
    <th scope="row">
        <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
    </th>
    <td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
        <fieldset>
            <a id="<?php echo esc_attr( $value['id'] ); ?>" class="button-primary <?php echo esc_attr( $value['class'] ); ?>" href="javascript:void(0)">
                <span><?php echo esc_html( $label ); ?></span>
            </a>
        </fieldset>
        <p class="description">
			<?php esc_html_e( 'Click on create webhook to generate Webhook Secret', 'funnelkit-stripe-woo-payment-gateway' ) ?> <br></p>


    </td>
</tr>

