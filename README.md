# WooCommerce PayPal Payments

PayPal's latest complete payments processing solution. Accept PayPal, Pay Later, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.

## Requirements

* PHP >= 7.0
* WordPress >=5.3
* WooCommerce >=4.5

## Development

1. Clone repository
2. `$ cd woocommerce-paypal-payments`
3. `$ composer install`
4. `$ yarn run build:dev`
5. Change the `PAYPAL_INTEGRATION_DATE` constant to `gmdate( 'Y-m-d' )` to run the latest PayPal JavaScript SDK

### Unit tests and code style

1. `$ composer install`
2. `$ ./vendor/bin/phpunit`
3. `$ ./vendor/bin/phpcs`

## Preparation for wordpress.org release

If you want to deploy a new version, you need to do some preparation:

### Clone

Clone the repository and `cd` into it

### Build

The following command should get you a ZIP file ready to be used on a WordPress site.

```
npm run build
```

### Update version

Make sure you have the version in the plugin root file updated.

### Fixate integration date

Fix the PayPal JavaScript SDK integration date by using the current date for the `PAYPAL_INTEGRATION_DATE` constant.

## License

[GPL-2.0 License](LICENSE)

## Contributing

All feedback / bug reports / pull requests are welcome.
