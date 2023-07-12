<?php
/**
 * @var $value []
 */
$label   = __( 'Delete Webhook', 'funnelkit-stripe-woo-payment-gateway' );
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
			<?php
			$webhook_id = "";
			if ( ! empty( get_option( 'fkwcs_live_created_webhook' ) ) ) {
				$webhook_id = get_option( 'fkwcs_live_created_webhook' );
			} else {
				$webhook_id = get_option( 'fkwcs_test_created_webhook' );
			}

			?>
            <br/>
            <br/>
            <div class="fkwcs_admin_settings_webhook_id"> &#9989; Webhook Created. ID : <b><?php esc_html_e( $webhook_id['id'] ); ?> </b>
            </div>
            <br/>

        </fieldset>
    </td>
</tr>
