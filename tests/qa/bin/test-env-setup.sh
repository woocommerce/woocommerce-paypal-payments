#!/usr/bin/env bash

echo -e "Compiling WooCommerce PayPal Payments Plugin"
wp dist-archive ../.. ./resources/files/woocommerce-paypal-payments.zip --plugin-dirname=woocommerce-paypal-payments > dev/null

echo -e 'Activate storefront theme \n'
wp-env run tests-cli wp theme activate storefront

echo -e 'Install Disable Nonce \n'
wp-env run tests-cli -- wp plugin install disable-nonce --activate

echo -e 'Install Disable New UI \n'
wp-env run tests-cli -- wp plugin install disable-new-ui --activate

echo -e 'Install Disable Webhook Verification \n'
wp-env run tests-cli -- wp plugin install disable-wc-setup-wizard --activate

echo -e 'Install Disable WC Setup Wizard \n'
wp-env run tests-cli -- wp plugin install disable-wc-setup-wizard --activate

echo -e 'Install WooCommerce \n'
wp-env run tests-cli -- wp plugin install woocommerce --activate

echo -e 'Install WooCommerce Subscriptions \n'
wp-env run tests-cli -- wp plugin install woocommerce-subscriptions --activate

echo -e 'Update URL structure \n'
wp-env run tests-cli -- wp rewrite structure '/%postname%/' --hard

echo -e 'Update Blog Name \n'
wp-env run tests-cli wp option update blogname 'WooCommerce E2E Test Suite'

echo -e 'Set the store as live \n'
wp-env run tests-cli wp option update woocommerce_coming_soon 'no'
