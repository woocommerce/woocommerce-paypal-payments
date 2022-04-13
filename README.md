# WooCommerce PayPal Payments

PayPal's latest complete payments processing solution. Accept PayPal, Pay Later, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.

## Dependencies

* PHP >= 7.1
* WordPress >=5.3
* WooCommerce >=4.5

## Development

### Install dependencies & build

- `$ composer install`
- `$ yarn run build:dev`

Optionally, change the `PAYPAL_INTEGRATION_DATE` constant to `gmdate( 'Y-m-d' )` to run the latest PayPal JavaScript SDK

### Unit tests and code style

1. `$ composer install`
2. `$ ./vendor/bin/phpunit`
3. `$ ./vendor/bin/phpcs`
4. `$ ./vendor/bin/psalm`

### Building a release package

If you want to build a release package
(that can be used for deploying a new version on wordpress.org or manual installation on a WP website via ZIP uploading),
follow these steps:

1. Clone the repository and `cd` into it.
2. Make sure you have the version in the plugin root file updated.
3. Update the PayPal JavaScript SDK integration date by using the current date for the `PAYPAL_INTEGRATION_DATE` constant.
4. The following command should get you a ZIP file ready to be used on a WordPress site:

```
$ yarn run build
```
or if using the Docker setup:

```
$ yarn run docker:build-package
```

## Setup

You can install WooCommerce PayPal Payments locally using the dev environment of your preference, or you can use the Docker environment which includes WP, WC and all developments tools.

To set up the Docker environment, follow these steps:

0. Install Docker and Docker Compose.
1. `$ cp .env.example .env` and edit the configuration in the `.env` file if needed.
2. `$ yarn run docker:build` (or copy the commands from [package.json](/package.json) if you do not have `yarn`).
3. `$ yarn run docker:install`
4. `$ yarn run docker:start`
5. Add `127.0.0.1 wc-pp.myhost` to your `hosts` file and open http://wc-pp.myhost (the default value of `WP_DOMAIN` in `.env`).

### Running tests in the Docker environment

Tests and code style:
- `$ yarn run docker:test`
- `$ yarn run docker:lint`

After some changes in `.env` (such as PHP, WP versions) you may need to rebuild the Docker image:

1. `$ yarn run docker:destroy` (all data will be lost)
2. `$ yarn run docker:build`

See [package.json](/package.json) for other useful commands.

## Test account setup

You will need a PayPal sandbox merchant and customer accounts to configure the plugin and make test purchases with it.

For setting up test accounts follow [these instructions](https://github.com/woocommerce/woocommerce-paypal-payments/wiki/Testing-Setup).

## Webhooks

For testing webhooks locally, follow these steps to set up ngrok:

0. Install [ngrok](https://ngrok.com/).

1. Run
```
ngrok http -host-header=rewrite wc-pp.myhost
```

2. In your environment variables (accessible to the web server), add `NGROK_HOST` with the host that you got from `ngrok`, like `abcd1234.ngrok.io`.

	- For the Docker environment: set `NGROK_HOST` in the `.env` file and restart the web server. (`yarn run docker:stop && yarn run docker:start`)

3. Complete onboarding or resubscribe webhooks on the Webhooks Status page.

Currently, ngrok is used only for the webhook listening URL.
The URLs displayed on the WordPress pages, used in redirects, etc. will still remain local.

## License

[GPL-2.0 License](LICENSE)

## Contributing

All feedback / bug reports / pull requests are welcome.
