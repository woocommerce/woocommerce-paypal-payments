# Playwright Testing

## Set Environment Variables

Duplicate [.env.e2e.example](/.env.e2e.example) and rename it to `.env.e2e`, set the values needed for the tests, like account credentials, card numbers.

## Install Playwright dependencies (browsers)

```
$ yarn ddev:pw-install
```

## Run Tests
```
$ yarn ddev:pw-tests
```

You can also choose which tests to run filtering by name
```
$ yarn ddev:pw-tests --grep "Test name or part of the name"
```

Or run without the headless mode (show the browser)
```
$ yarn pw-tests-headed
```

Or run with [the test debugger](https://playwright.dev/docs/debug)
```
$ yarn playwright test --debug
```

For the headed/debug mode (currently works only outside DDEV) you may need to re-install the deps if you are not on Linux.

```
$ yarn pw-install
```

---
See [Playwright docs](https://playwright.dev/docs/intro) for more info.
