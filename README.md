# PayPal Payments for WooCommerce

PayPal's latest complete payments processing solution. Accept PayPal, PayPal Credit, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.

## Requirements

* PHP >= 7.0
* WordPress >=5.3
* WooCommerce >=4.5

## Development

1. Clone repository
2. `$ cd paypal-for-woocommerce`
3. `$ composer install`
4. `$ yarn run dev`
5. Change the `PAYPAL_INTEGRATION_DATE` constant to `gmdate( 'Y-m-d' )` to run the latest PayPal JavaScript SDK

Note: PHPUnit needs at least PHP 7.3.

### Unit tests and code style

1. `$ composer install`
2. `$ ./vendor/bin/phpunit`
3. `$ ./vendor/bin/phpcs src modules woocommerce-paypal-commerce-gateway.php --extensions=php`

## Preparation for wordpress.org release

If you want to deploy a new version, you need to do some preparation:

### Clone

Clone the repository and `cd` into it

### Build

Build the plugin and remove unnecessary files:
```
composer install --no-dev
yarn run build
rm ./tests -rf
rm ./.git -rf
rm ./.github -rf
rm ./.gitignore
rm ./.phpunit.result.cache
rm ./.travis
rm ./composer.json
rm ./composer.lock
rm ./package.json
rm ./phpcs.xml.dist
rm ./phpunit.xml.dist
rm ./yarn.lock
rm ./modules/ppcp-button/node_modules/ -rf
rm ./modules/ppcp-button/.babelrc
rm ./modules/ppcp-button/package.json
rm ./modules/ppcp-button/webpack.config.js
rm ./modules/ppcp-button/yarn.lock
```

### Update version

Make sure you have the version in the plugin root file updated.

### Fixate integration date

Fix the PayPal JavaScript SDK integration date by using the current date for the `PAYPAL_INTEGRATION_DATE` constant.

## License

[GPL-2.0 License](LICENSE)

## Contributing

All feedback / bug reports / pull requests are welcome.