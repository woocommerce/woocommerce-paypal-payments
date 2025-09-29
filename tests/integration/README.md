# Integration Tests
PHPUnit tests that runs against a working WP site. Useful to test modules (ex. classes) together without having the use test doubles but the real infrastructure.

### Setup test environment

- Start ddev `ddev start` && `ddev orchestrate -f`
- Copy and rename `.env.integration.example` to  `.env.integration` from the root of the plugin pointing to your DDEv url, set other configurations if needed.
- Install required WooCommerce Subscriptions using ddev `ddev wp plugin install tests/qa/resources/files/woocommerce-subscriptions.zip --activate`
- Run extra setup configuration: `ddev php tests/integration/PHPUnit/setup.php`

### How to run the tests

- Run all the tests: `ddev exec phpunit -c tests/integration/phpunit.xml.dist`
- Run a single test: `ddev exec phpunit --filter testSomeTestName -c tests/integration/phpunit.xml.dist`

### How to debug tests

- Run `ddev xdebug enable`
- Setup your IDE to listen to new DEBUG connections
- Run tests adding --debug flag: `ddev exec phpunit -c tests/integration/phpunit.xml.dist --debug`
