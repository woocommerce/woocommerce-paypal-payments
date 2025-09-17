#!/usr/bin/env node
const { execSync } = require('child_process');

const commands = [
    {
        description: 'Activate storefront theme',
        command: 'wp-env run tests-cli wp theme activate storefront'
    },
    {
        description: 'Install WooCommerce',
        command: 'wp-env run tests-cli -- wp plugin install woocommerce --activate'
    },
    {
        description: 'Install WooCommerce Payments',
        command: 'wp-env run tests-cli -- wp plugin install woocommerce-payments'
    },
    {
        description: 'Update URL structure',
        command: 'wp-env run tests-cli -- wp rewrite structure "/%postname%/" --hard'
    },
    {
        description: 'Update Blog Name',
        command: 'wp-env run tests-cli -- wp option update blogname "WooCommerce PayPal Payments E2E Test Suite"'
    },
    {
        description: 'Set the store as live',
        command: 'wp-env run tests-cli -- wp option update woocommerce_coming_soon "no"'
    },
    {
        description: 'PayPal - Set new UI (merchant flag)',
        command: 'wp-env run tests-cli -- wp option update woocommerce-ppcp-is-new-merchant "yes"'
    },
    {
        description: 'PayPal - Set new UI (disable old UI)',
        command: 'wp-env run tests-cli -- wp option update woocommerce_ppcp-settings-should-use-old-ui "no"'
    }
];

console.log('Starting test environment setup...\n');

commands.forEach((item, index) => {
    console.log(`${index + 1}. ${item.description}`);
    execSync(item.command, { stdio: 'inherit' });
});

console.log('🎉 Test environment setup complete!');
