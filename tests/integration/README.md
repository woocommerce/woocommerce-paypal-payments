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

> **`setup.php` must have run first.** Only `npm run integration-tests` runs it. It sets the
> tax-related WooCommerce options and imports `data/tax_rates.csv`, which several tests depend
> on. Invoking PHPUnit directly — `tdd:integration`, or a bare `phpunit -c
> tests/integration/phpunit.xml.dist` — skips it, and against an unseeded (or drifted) database
> that produces failures unrelated to your change: `PurchaseUnitTest` alone reports 11, with
> wrong tax and discount totals. If a run fails that way, re-seed and try again:
>
> ```
> ddev exec php tests/integration/PHPUnit/setup.php
> ```
>
> Tests that mutate global WooCommerce options or the shipping/tax caches must restore them in
> `tearDown()`, otherwise they corrupt this seeded state for every test that runs after them in
> the same process.

### How to debug tests

- Run `ddev xdebug enable`
- Setup your IDE to listen to new DEBUG connections
- Run tests adding --debug flag: `ddev exec phpunit -c tests/integration/phpunit.xml.dist --debug`
  (this bypasses `setup.php` — see the note above if you get unexpected tax-related failures)
