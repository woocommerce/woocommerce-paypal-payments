# WooCommerce PayPal Payments

PayPal's latest complete payments processing solution. Accept PayPal, Pay Later, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.

## Dependencies

* PHP >= 7.4
* WordPress >= 6.3
* WooCommerce >= 6.9

## Development

### Install dependencies & build

- `$ composer install`
- `$ yarn install`

Optionally, change the `PAYPAL_INTEGRATION_DATE` constant to `gmdate( 'Y-m-d' )` to run the latest PayPal JavaScript SDK

### Unit tests and code style

1. `$ composer install`
2. `$ ./vendor/bin/phpunit`
3. `$ ./vendor/bin/phpcs`
4. `$ ./vendor/bin/psalm`
5. `$ wp-scripts lint-js`
6. `$ yarn run test:unit-js` - Ensure node version is `18` or above

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
or if using the DDEV setup:

```
$ yarn run ddev:build-package
```

## Setup

You can install WooCommerce PayPal Payments locally using the dev environment of your preference, or you can use the DDEV setup provided in this repository which includes WP, WC and all developments tools.

To set up the DDEV environment, follow these steps:

0. Install Docker and [DDEV](https://ddev.readthedocs.io/en/stable/).
1. Edit the configuration in the [`.ddev/config.yml`](.ddev/config.yaml) file if needed.
2. `$ ddev start`
3. `$ ddev orchestrate` to install WP/WC.
4. Open https://wc-pp.ddev.site

Use `$ ddev orchestrate -f` for reinstalattion (will destroy all site data).
You may also need `$ ddev restart` to apply the config changes.

### Running tests and other tasks in the DDEV environment

Tests and code style:
- `$ yarn ddev:test`
- `$ yarn ddev:lint`
- `$ yarn ddev:fix-lint`
- `$ yarn ddev:lint-js`

See [package.json](/package.json) for other useful commands.

For debugging, see [the DDEV docs](https://ddev.readthedocs.io/en/stable/users/step-debugging/).
Enable xdebug via `$ ddev xdebug`, and press `Start Listening for PHP Debug Connections` in PHPStorm.
After creating the server in the PHPStorm dialog, you need to set the local project path for the server plugin path.
It should look [like this](https://i.imgur.com/ofsF1Mc.png).

See [tests/playwright](tests/playwright) for e2e (browser-based) tests.

## Test account setup

You will need a PayPal sandbox merchant and customer accounts to configure the plugin and make test purchases with it.

For setting up test accounts follow [these instructions](https://github.com/woocommerce/woocommerce-paypal-payments/wiki/Testing-Setup).

## Webhooks

For testing webhooks locally, follow these steps to set up ngrok:

0. Install [ngrok](https://ngrok.com/).

1.
  - If using DDEV, run our wrapper Bash script which will start `ddev share` and replace the URLs in the WP database:
    ```
    $ .ddev/bin/share
    ```

  - For other environments, run
    ```
    $ ngrok http -host-header=rewrite wc-pp.myhost
    ```
    and in your environment variables (accessible to the web server) add `NGROK_HOST` with the host that you got from `ngrok`, like `abcd1234.ngrok.io`. ngrok will be used only for the webhook listening URL.
The URLs displayed on the WordPress pages, used in redirects, etc. will still remain local.

2. Complete onboarding or resubscribe webhooks on the Webhooks Status page.

## License

[GPL-2.0 License](LICENSE)

## Contributing

All feedback / bug reports / pull requests are welcome.
