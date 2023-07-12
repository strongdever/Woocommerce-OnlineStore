<?php
/**
 * The list of modules.
 *
 * @package WooCommerce\PayPalCommerce
 */

use WooCommerce\PayPalCommerce\PluginModule;

return function ( string $root_dir ): iterable {
	$modules_dir = "$root_dir/modules";

	$modules = array(
		new PluginModule(),
		( require "$modules_dir/woocommerce-logging/module.php" )(),
		( require "$modules_dir/ppcp-admin-notices/module.php" )(),
		( require "$modules_dir/ppcp-api-client/module.php" )(),
		( require "$modules_dir/ppcp-button/module.php" )(),
		( require "$modules_dir/ppcp-compat/module.php" )(),
		( require "$modules_dir/ppcp-onboarding/module.php" )(),
		( require "$modules_dir/ppcp-session/module.php" )(),
		( require "$modules_dir/ppcp-status-report/module.php" )(),
		( require "$modules_dir/ppcp-subscription/module.php" )(),
		( require "$modules_dir/ppcp-wc-gateway/module.php" )(),
		( require "$modules_dir/ppcp-webhooks/module.php" )(),
		( require "$modules_dir/ppcp-vaulting/module.php" )(),
		( require "$modules_dir/ppcp-order-tracking/module.php" )(),
		( require "$modules_dir/ppcp-uninstall/module.php" )(),
	);

	if ( apply_filters(
		'woocommerce_paypal_payments_blocks_enabled',
		getenv( 'PCP_BLOCKS_ENABLED' ) === '1'
	) ) {
		$modules[] = ( require "$modules_dir/ppcp-blocks/module.php" )();
	}

	return $modules;
};
