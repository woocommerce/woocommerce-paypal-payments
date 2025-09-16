#!/usr/bin/env bash

echo -e 'Activate storefront theme \n'
wp-env run tests-cli wp theme activate storefront

echo -e 'Install WooCommerce \n'
wp-env run tests-cli -- wp plugin install woocommerce --activate

echo -e 'Install WooCommerce Payments \n'
wp-env run tests-cli -- wp plugin install woocommerce-payments

echo -e 'Update URL structure \n'
wp-env run tests-cli -- wp rewrite structure '/%postname%/' --hard

echo -e 'Update Blog Name \n'
wp-env run tests-cli -- wp option update blogname 'WooCommerce PayPal Payments E2E Test Suite'

echo -e 'Set the store as live \n'
wp-env run tests-cli -- wp option update woocommerce_coming_soon 'no'

echo -e 'PayPal - Set new UI \n'
wp-env run tests-cli -- wp option update woocommerce-ppcp-is-new-merchant 'yes'
wp-env run tests-cli -- wp option update woocommerce_ppcp-settings-should-use-old-ui 'no'
