# PCP Tests (legacy UI)

Depends on [`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) package.
## Project structure

- `resources` - files with test-data, images, project related installation packages, types, etc.

- `tests` - test specifications. For payment plugins contains following folders:
	
    - `01-plugin-foundation` - general tests for plugin installation, uninstallation, activation, deactivation, display of plugin in __WooCommerce -> Settings -> Payments__.

    - `02-merchant-setup` - tests for connection of current plugin instance to the payment system provider API via merchant (seller) credentials.

    - `03-plugin-settings` - tests for various plugin settings, may include assertions of settings effect on frontend.

    - `04-frontend-ui` - tests for plugin UI on frontend: display of payment buttons, display of payment methods depending on customer's country, etc.

    - `05-transaction` - tests of payment process. Typically include: adding products to cart as precondition, payment (transaction) process, assertions on order received page, dashboard order edit page, payment via payment system provider API.

    - `06-refund` - tests for refund transactions. Typically include: finished transaction as precondition, refund via payment system provider API on dashboard order edit page, assertion of refund statuses.

    - `07-vaulting` - tests for transactions with enabled vaulting (saved payment methods for registered customers). Ability to remember payment methods and use them for transactions.

    - `08-subscriptions` - tests for transactions for subscription products. Requires WooCommerce Subscriptions plugin. Usually available to registered customers and also includes vaulting and renewal of subscription (with automatic payment). WooCommerce Subscriptions plugin (can be [downloaded here](https://woocommerce.com/my-account/downloads/), login credentials in 1Password).

    - `09-compatibility` - tests for compatibility with other themes, plugins, etc.

	\* - folders are numerated on purpose, to force correct sequence of tests - from basic to advanced. Although each test should be independent and work separately, it is better to start testing from `plugin-foundation` and move to more complex tests.

	\*\* - folders and numeration can be different, based on project requirements.

- `utils` - project related utility files, built on top of `@inpsyde/playwright-utils`.

    - `admin` - functionality for operating dashboard pages.

    - `frontend` - functionality for operating frontend pages, hosted checkout pages (payment system provider's pages).

    - `test.ts` - declarations of project related test fixtures.

    - other project related functionality, like helpers, APIs, urls.

- `.env`, `playwright.config.ts`, `package.json` - see below.

## Setup @inpsyde/playwright-utils as a node package

> See also [@inpsyde/playwright-utils documentation](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#installation).

1. Make sure you're logged in the [Syde npm package registry](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3112894465/GitHub+Package+Registry+for+npm).

2. Make sure that `"workspaces": [ "playwright-utils" ]` node isn't present in `./tests/qa/package.json`.

3. In the terminal change directory to `./tests/qa` and run following command:

	```bash
	npm run setup:tests
	```

## Setup @inpsyde/playwright-utils for local development

> See also [@inpsyde/playwright-utils documentation](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#development).

1. Add `"workspaces": [ "playwright-utils" ]` to `./tests/qa/package.json`.

2. Delete `@inpsyde/playwright-utils` from `./tests/qa/node_modules`.

3. In the terminal change directory to `./tests/qa` and run following command:

	```bash
	git clone https://github.com/inpsyde/playwright-utils.git
	```

	[`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) repository should be cloned as `playwright-utils` right inside the root directory of project.

4. Restart VSCode editor. This will create `playwright-utils` instance in the source control tab of VSCode editor.

5. Run following command:

	```bash
	npm run setup:utils
	```

6. `@inpsyde/playwright-utils` should reappear in node_modules. Following message (coming from `tsc-watch`) should be displayed in the terminal:

	```bash
	10:00:00 - Found 0 errors. Watching for file changes.
	```

7. If you plan to make changes in `playwright-utils` keep current terminal window opened and create another instance of terminal for other commands.

## Project configuration

1. [SSE setup](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3175907370/Self+Service+WordPress+Environment) - will be deprecated in Q1 of 2025.

2. Configure `.env` file following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables). See also `/tests/qa/.env.example`. The [`.env` content with actual test users' credentials](https://start.1password.com/open/i?a=UL7QZZ6P6JDVBI422AOVJXMEGU&v=uthlbcp4jkori6w6rhgxvsvfoe&i=klejf7rgcip76c7auhsnhvxcbi&h=inpsyde.1password.eu) is in 1Password in Legacy UI section.

3. Configure `playwright.config.ts` of the project following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#playwright-configuration).

4. Additional website and WooCommerce configuration is done automatically via `setup-woocommerce` dependency project (see [`/tests/_setup/woocommerce.setup.ts`](./tests/_setup/woocommerce.setup.ts)).

5. To avoid conflicts make sure any other payment plugins are deleted.

6. Reporting to __Xray in Jira__ is configured [this way](https://github.com/inpsyde/playwright-utils/blob/main/docs/test-report-api/report-to-xray.md).

## Run tests

To execute tests, in the terminal, navigate to the __qa__ directory of the project (e.g. `cd tests/qa`) and run following command:

```bash
npx playwright test
```

### Additional options to run tests from command line

- Add scripts to `package.json` of the project (eligible for Windows, not tested on other OS):

	```json
	"scripts": {
		"test:smoke":  "npx playwright test --grep \"@Smoke\"",
		"test:critical": "npx playwright test --grep \"@Critical\"",
		"test:ui": "npx playwright test --grep \"UI\"",
		"test:functional": "npx playwright test --grep \"Functional\"",
		"test:all": "npm run test:ui & npm run test:functional"
	},
	```

	Run script with the following command:

	```bash
	npm run test:critical
	```

- Run several tests by test ID (on Windows, Powershell):

	```bash
	npx playwright test --grep --% "WOL-123^|WOL-124^|WOL-125"
	```

	It may be required additionally to specify the project (if tests relate to more then one project):

	```bash
	npx playwright test --project "project-name" --grep --% "WOL-123^|WOL-124^|WOL-125"
	```

## Autotest Execution workflow

1. Create Test Execution ticket in Jira, named after the tested plugin version, for example "Test Execution for v2.3.4-rc1, PHP8.1".

2. Link release ticket (via `tests: WOL-234`).

3. Set Test Execution ticket status `In progress`.

4. Add/update test execution ticket key in `.env` file of the project (`TEST_EXEC_KEY`).

5. Download tested plugin `.zip` package (usually attached to release ticket) and add it to `/project/<project-name>/resources/files`. You may need to remove version number from the file name.

6. Optional: delete previous version of tested plugin from the website if you don't execute __plugin foundation__ tests.

7. Start autotest execution from command line for the defined scope of tests (e.g. all, Critical, etc.). You should see `Test execution Jira key: WOL-234` in the terminal.

8. When finished test results should be exported to the specified test execution ticket in Jira.

9. Analyze failed tests (if any). Restart execution for failed tests, possibly in debug mode (for Windows):

	```bash
	npx playwright test --grep --% "WOL-123^|WOL-124^|WOL-125" --debug
	```

10. Report bugs (if any) and attach them to the test-runs of failed tests (Click "Create defect" or "Add defect" on test execution screen).

11. If needed fix failing tests in a new branch, create a PR and assign it for review.

12. Set Test execution ticket status to `Done`.

## Coding standards

Before commiting changes run following command:

```bash
npm run lint:js:fix
```
