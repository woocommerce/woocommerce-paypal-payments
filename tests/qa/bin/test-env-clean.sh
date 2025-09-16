#!/usr/bin/env bash

echo -e 'Deactivate storefront theme \n'
wp-env run tests-cli wp theme disable storefront

echo -e 'Uninstall PayPal \n'
wp-env run tests-cli -- wp plugin delete woocommerce-paypal-payments

echo -e 'Uninstall Subscriptions \n'
wp-env run tests-cli -- wp plugin delete woocommerce-subscriptions

echo -e 'Uninstall WooCommerce \n'
wp-env run tests-cli -- wp plugin delete woocommerce

