# Integration Tests
PHPUnit tests that runs against a working WP site. Useful to test modules (ex. classes) together without having the use test doubles but the real infrastructure.

### How to run tests
- Copy and rename `.env.integration.example` to  `.env.integration` from the root of the plugin, set configurations if needed.
- Run configuration: `ddev php tests/integration/PHPUnit/setup.php`
- Run tests: `ddev exec phpunit -c tests/integration/phpunit.xml.dist`
- Run a single test: `ddev exec phpunit --filter testSomeTestName -c tests/integration/phpunit.xml.dist`
