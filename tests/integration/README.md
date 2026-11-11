# Integration tests

PHPUnit tests that run against a working WP site. Useful to test modules (ex. classes) together without test doubles, on the real infrastructure.

### Set up the test environment

- Set up DDEV (if not already): `ddev start && ddev orchestrate`
- Install the WooCommerce Subscriptions plugin. You do not have to activate it on your DDEV site.
- Edit `.env.integration` in the root if needed.

### Data isolation

The npm scripts run the tests in a dedicated database, `wp_integration_tests`, on the DDEV database server. They do not change the data of your DDEV site. The WordPress files and plugins are the same as on the DDEV site.

Each run of `integration-tests` creates a new test database. `tests/integration/bin/create-database.sh` installs WordPress in it and activates WooCommerce, WooCommerce Subscriptions, and this plugin. Then `setup.php` sets the tax-related WooCommerce options and imports `data/tax_rates.csv`, which several tests depend on.

The bootstrap selects the test database through the `PPCP_INTEGRATION_DB_NAME` environment variable, which the npm scripts set. Without it, the tests use the DDEV site database. The CI workflow does this, because it runs on a new DDEV site each time.

### How to run the tests

- Run all tests: `ddev npm run integration-tests`
- Run selected tests: `ddev npm run integration-tests -- --filter SomeTestOrFileName`
- Stop on the first error: `ddev npm run integration-tests -- --stop-on-failure`
- Create or reset the test database without a test run: `ddev npm run integration-tests:reset-db`

> Tests that mutate global WooCommerce options or the shipping/tax caches must restore them in `tearDown()`, otherwise they corrupt this seeded state for every test that runs after them in the same process.

### How to debug tests

- Run `ddev xdebug enable`
- Set up your IDE to listen to new DEBUG connections
- Run tests with the `--debug` flag: `ddev exec PPCP_INTEGRATION_DB_NAME=wp_integration_tests phpunit -c tests/integration/phpunit.xml.dist --debug`

This command uses the existing test database. If it does not exist (for example, after `ddev snapshot restore`) or contains data from earlier runs, run `ddev npm run integration-tests:reset-db` first.
