# Playwright Testing

## Local Environment Variables
Allows using environment variables inside the tests.

- Duplicate `.env.e2e.example` and rename it as `.env.e2e`, set values and add new variables if needed.

## Run Tests
```
$ npx playwright test
$ npx playwright test --grep @ci
$ npx playwright test example.spec.js --headed
$ npx playwright test example.spec.js --debug
$ npx playwright test -g "Test name here"
```
