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
		( require "$modules_dir/ppcp-blocks/module.php" )(),
	);
	if ( apply_filters(
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		'woocommerce.feature-flags.woocommerce_paypal_payments.applepay_enabled',
		getenv( 'PCP_APPLEPAY_ENABLED' ) === '1'
	) ) {
		$modules[] = ( require "$modules_dir/ppcp-applepay/module.php" )();
	}

	if ( apply_filters(
		//phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		'woocommerce.feature-flags.woocommerce_paypal_payments.googlepay_enabled',
		getenv( 'PCP_GOOGLEPAY_ENABLED' ) === '1'
	) ) {
		$modules[] = ( require "$modules_dir/ppcp-googlepay/module.php" )();
	}

	if ( apply_filters(
		//phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		'woocommerce.deprecated_flags.woocommerce_paypal_payments.saved_payment_checker_enabled',
		getenv( 'PCP_SAVED_PAYMENT_CHECKER_ENABLED' ) === '1'
	) ) {
		$modules[] = ( require "$modules_dir/ppcp-saved-payment-checker/module.php" )();
	}

	return $modules;
};
