#!/usr/bin/env bash

echo -e 'Deactivate storefront theme \n'
wp-env run tests-cli wp theme disable storefront

echo -e 'Uninstall PayPal \n'
wp-env run tests-cli -- wp plugin delete woocommerce-paypal-payments

echo -e 'Uninstall WooCommerce Payments \n'
wp-env run tests-cli -- wp plugin delete woocommerce-payments

echo -e 'Uninstall WooCommerce \n'
wp-env run tests-cli -- wp plugin delete woocommerce

