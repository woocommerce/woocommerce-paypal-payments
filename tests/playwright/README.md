# Playwright Testing

## Local Environment Variables
Allows using environment variables inside the tests.

- Duplicate `.env.sample` and rename it as `.env`, set values and add new variables if needed.

## Run Tests
```
$ npx playwright test
$ npx playwright test example.spec.js --headed
$ npx playwright test example.spec.js --debug
$ npx playwright test -g "Test name here"
```
