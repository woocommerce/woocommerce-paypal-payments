# Integration Tests
PHPUnit tests that runs against a working WP site. Useful to test modules (ex. classes) together without having the use test doubles but the real infrastructure.

### Setup test environment

- Setup DDEV (if not already): `ddev start && ddev orchestrate`
- Install and activate WooCommerce Subscriptions plugin.
- Run `ddev npm run integration-tests`
- Edit `.env.integration` in the root if needed, set your DDEV url.

### How to run the tests

- Run all the tests: `ddev npm run integration-tests`
- Run a single test: `ddev npm run tdd:integration testSomeTestName` or `ddev npm run tdd:integration SomeTestClassName`

### How to debug tests

- Run `ddev xdebug enable`
- Setup your IDE to listen to new DEBUG connections
- Run tests adding --debug flag: `ddev exec phpunit -c tests/integration/phpunit.xml.dist --debug`
