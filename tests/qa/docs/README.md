# Test Project for WooCommerce PayPal Payments Plugin

Basic information about current test project installation, execution and automated scripts can be found in the [main README file](../README.md).

## Test project structure

- `resources` - files with test-data, images, project related installation packages, types, etc.

- [`tests`](./tests/) - test specifications. For payment plugins contains following folders:
	
    - `01-plugin-foundation` - general tests for plugin installation, uninstallation, activation, deactivation, display of plugin in WooCommerce -> Settings -> Payments.

    - `02-onboarding` - tests for connection of current plugin instance to the payment system provider API via merchant (seller) credentials.

    - `03-plugin-settings` - tests for various plugin settings, may include assertions of settings effect on frontend.

    - `04-frontend-ui` - tests for plugin UI on frontend: display of payment buttons, display of payment methods depending on customer's country, etc.

    - `05-transaction` - tests of payment process. Typically include: adding products to cart as precondition, payment (transaction) process, assertions on order received page, dashboard order edit page, payment via payment system provider API.

    - `06-refund` - tests for refund transactions. Typically include: finished transaction as precondition, refund via payment system provider API on dashboard order edit page, assertion of refund statuses.

    - [`07-vaulting`](./tests/07-vaulting.md) - tests for transactions with enabled vaulting (saved payment methods for registered customers). Ability to remember payment methods and use them for transactions.

    - [`08-subscriptions`](./tests/08-subscription.md) - tests for transactions with subscription products. Requires WooCommerce Subscriptions plugin. Usually available to registered customers and also includes vaulting and renewal of subscription (with automatic payment). WooCommerce Subscriptions plugin (can be downloaded here, login credentials in 1Password).

    - `09-compatibility` - tests for compatibility with other themes, plugins, etc.

	> Note 1: Folders are numerated on purpose, to force correct sequence of tests - from basic to advanced. Although each test should be independent and work separately, it is better to start testing from `plugin-foundation` and move to more complex tests.

	> Note 2: Folders and numeration can be different, based on project requirements.

- [`utils`](./utils/) - project related utility files, built on top of `@inpsyde/playwright-utils`.

    - `admin` - functionality for operating dashboard pages.

    - `frontend` - functionality for operating frontend pages, hosted checkout pages (payment system provider's pages).

    - `test.ts` - declarations of project related test fixtures.

    - other project related functionality, like helpers, APIs, urls.

- `.env`, `playwright.config.ts`, `package.json` - environment and Playwright configuration (see [main README](../README.md)).