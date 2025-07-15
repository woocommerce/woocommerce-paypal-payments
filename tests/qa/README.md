# WooCommerce PayPal Payments Plugin Tests

Depends on [`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) package.

## Table of Content

- [Test project structure](#test-project-structure)
- [Local repo installation](#local-repo-installation)
- [Installation of `node_modules`](#installation-of-node_modules)
- [Installation of `playwright-utils` for local development](#installation-of-playwright-utils-for-local-development)
- [Project configuration](#project-configuration)
- [Run tests](#run-tests)
    - [Additional options to run tests from command line](#additional-options-to-run-tests-from-command-line)
- [Autotest Execution workflow](#autotest-execution-workflow)
- [Coding standards](#coding-standards)
- [Automated env setup scripts](#automated-env-setup-scripts)

## Test project structure

- `resources` - files with test-data, images, project related installation packages, types, etc.

- `tests` - test specifications. For payment plugins contains following folders:
	
    - `01-plugin-foundation` - general tests for plugin installation, uninstallation, activation, deactivation, display of plugin in WooCommerce -> Settings -> Payments.

    - `02-onboarding` - tests for connection of current plugin instance to the payment system provider API via merchant (seller) credentials.

    - `03-plugin-settings` - tests for various plugin settings, may include assertions of settings effect on frontend.

    - `04-frontend-ui` - tests for plugin UI on frontend: display of payment buttons, display of payment methods depending on customer's country, etc.

    - `05-transaction` - tests of payment process. Typically include: adding products to cart as precondition, payment (transaction) process, assertions on order received page, dashboard order edit page, payment via payment system provider API.

    - `06-refund` - tests for refund transactions. Typically include: finished transaction as precondition, refund via payment system provider API on dashboard order edit page, assertion of refund statuses.

    - `07-vaulting` - tests for transactions with enabled vaulting (saved payment methods for registered customers). Ability to remember payment methods and use them for transactions.

    - `08-subscriptions` - tests for transactions for subscription products. Requires WooCommerce Subscriptions plugin. Usually available to registered customers and also includes vaulting and renewal of subscription (with automatic payment). WooCommerce Subscriptions plugin (can be downloaded here, login credentials in 1Password).

    - `09-compatibility` - tests for compatibility with other themes, plugins, etc.

	> Note 1: Folders are numerated on purpose, to force correct sequence of tests - from basic to advanced. Although each test should be independent and work separately, it is better to start testing from `plugin-foundation` and move to more complex tests.

	> Note 2: Folders and numeration can be different, based on project requirements.

- `utils` - project related utility files, built on top of `@inpsyde/playwright-utils`.

    - `admin` - functionality for operating dashboard pages.

    - `frontend` - functionality for operating frontend pages, hosted checkout pages (payment system provider's pages).

    - `test.ts` - declarations of project related test fixtures.

    - other project related functionality, like helpers, APIs, urls.

- `.env`, `playwright.config.ts`, `package.json` - see below.


## Local repo installation

1. In VSCode open the terminal and clone PCP repository to your local PC:

	```bash
	git clone https://github.com/woocommerce/woocommerce-paypal-payments.git
	```

2. Change directory to newly cloned repo:

	```bash
    cd woocommerce-paypal-payments
    ```

3. (Temporary, till autotests are not yet merged into main branch) Switch to `qa` branch:

	```bash
 	git fetch origin
	git checkout qa
	```

4.  Change directory to `./tests/qa/`:

	```bash
	cd tests/qa
	```

## Installation of `node_modules`

1. Remove `"workspaces": [ "playwright-utils" ]` from `package.json`.

2. In the test project (`./tests/qa/`) run following command:

```bash
npm run setup:tests
```

## Installation of `playwright-utils` for local development

> Note: skip this section if you're not going to update code in `playwright-utils`.

> See also @inpsyde/playwright-utils [documentation](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#installation).

1. Add `"workspaces": [ "playwright-utils" ]` to `package.json`.

2. Delete `@inpsyde/playwright-utils` from `/node_modules`.

3. In the test project directory (`./tests/qa/`) run following command:

	```bash
	git clone https://github.com/inpsyde/playwright-utils.git
	```

	[`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) repository should be cloned as `playwright-utils` right inside the root directory of monorepo.

4. Restart VSCode editor. This will create `playwright-utils` instance in the source control tab of VSCode editor.

5. Run following command:

	```bash
	npm run setup:utils
	```

6. `@inpsyde/playwright-utils` should reappear in node_modules. Following message (coming from `tsc-watch`) should be displayed in the terminal:

	```bash
	10:00:00 - Found 0 errors. Watching for file changes.
	```

7. If you plan to make changes in `playwright-utils` keep current terminal window opened and create another instance of terminal.


## Project configuration

1. [SSE setup](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3175907370/Self+Service+WordPress+Environment) - will be deprecated in Q1 of 2025.

2. In the test project directory (`./tests/qa/`) create and configure `.env` file:

	2.1 Set general variables following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables).
	
	2.2 Set PayPal API keys and test credentials. See `.env.example`. The `.env` content with actual test users' credentials is [stored in 1Password](https://start.1password.com/open/i?a=UL7QZZ6P6JDVBI422AOVJXMEGU&v=qrw2rcy27xpwiedd5gifyga2pa&i=iddsl6wdm62lwwqbzz474jodme&h=inpsyde.1password.eu).

3. Configure `playwright.config.ts` of the project following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#playwright-configuration).

4. Configure reporting to __Xray in Jira__ following [these steps](https://github.com/inpsyde/playwright-utils/blob/main/docs/test-report-api/report-to-xray.md).

5. To avoid conflicts make sure any other payment plugins are deleted.

6. Additional website and WooCommerce configuration is done automatically via `setup-woocommerce` dependency project (see [`/tests/_setup/woocommerce.setup.ts`](./tests/_setup/woocommerce.setup.ts)).

## Run tests

To execute tests, in the terminal, navigate to the __qa__ directory of the project (e.g. `cd tests/qa`) and run following command:

```bash
npx playwright test --project=all
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
	npx playwright test --grep --% "PCP-123^|PCP-124^|PCP-125"
	```

	It may be required additionally to specify the project (if tests relate to more then one project):

	```bash
	npx playwright test --project "project-name" --grep --% "PCP-123^|PCP-124^|PCP-125"
	```

## Autotest Execution workflow

1. Create Test Execution ticket in Jira, named after the tested plugin version, for example "Test Execution for v2.3.4-rc1, PHP8.1".

	> Note: For autotest execution there's no need to manually add tests cases to the execution - the executed tests will be imported automatically after execution.

2. Link release ticket (via `tests: PCP-234`) to test execution ticket.

3. Set Test Execution ticket status `In progress`.

4. In `.env` file of the test project (`/tests/qa/`) add/update test execution ticket key (`TEST_EXEC_KEY='PCP-234'`).

5. Download tested plugin `.zip` package (usually attached to release ticket) and add it to `/tests/qa/resources/files`. You may need to remove version number from the file name (expected name: `woocommerce-paypal-payments.zip`).

6. Optional: delete previous version of tested plugin from the website if you don't execute __plugin foundation__ tests.

7. Start autotest execution from command line for the defined scope of tests (see [this section](#run-tests)). You should see `Test execution Jira key: PCP-234` in the terminal.

8. When finished test results will be imported to the specified test execution ticket in Jira.

9. Analyze failed tests (if any). Restart execution for failed tests, possibly in debug mode (for Windows):

	```bash
	npx playwright test --grep --% "PCP-123^|PCP-124^|PCP-125" --debug
	```

	> Note: command for restarting failed/skipped tests is posted to the terminal after the execution.

10. Report bugs (if any) and attach them to the test-runs of failed tests (Click "Create defect" or "Add defect" on test execution screen).

11. If needed fix failing tests in a new branch, create a PR and assign it for review.

12. Set Test execution ticket status to `Done`.

## Coding standards

Before commiting changes run following command:

```bash
npm run lint:js:fix
```

## Automated env setup scripts

Local usage of _automated env setup scripts_ assumes that the following steps are fulfilled:

1. Your current terminal dir to run scripts is `./tests/qa`.

2. Dependencies and `node_modules` are installed (see [this section](#installation-of-node_modules)).

	> Note: for now the storage of .zip files is restricted in .gitignore. Please ask someone of QA to provide the content of `./tests/qa/resources/files` dir.

3. `.env` file is configured as per step 2 of [this section](#project-configuration) (simply copy-paste it from `PCP .env` vault of 1Password).

4. (Optional) For DDEV setup add `IGNORE_HTTPS_ERRORS=true` to `.env` and remove the Basic Auth credentials:

	```bash
	# playwright-utils config
	IGNORE_HTTPS_ERRORS=true
	WP_BASE_URL='https://woocommerce-paypal-payments.ddev.site'
	WP_USERNAME=admin
	WP_PASSWORD=admin
	WP_BASIC_AUTH_USER=
	WP_BASIC_AUTH_PASS=
	STORAGE_STATE_PATH='./storage-states'
	STORAGE_STATE_PATH_ADMIN='./storage-states/admin.json'
	```

### Reset SSE env

> Note: see [SSE setup](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3175907370/Self+Service+WordPress+Environment) don in Confluence (will be deprecated in 2025).

1. Connect via `ssh`:

	```bash
	ssh -l fname php_version.emp.pluginpsyde.com
	```

2. Reset SSE website:

	```bash
	rm -rf /var/www/html/* 2>/dev/null; wp core download --version=X.Y.Z && wp config create && mariadb -e "DROP DATABASE fname; CREATE DATABASE fname;" && wp core install
	```

### Setup store

- Installs WooCommerce, Storefront theme, additional plugins (WP Debugging, Disable Nonce, Subscriptions, etc.).
- Configures website permalinks (`%postname%`).
- Configures WooCommerce default settings (country, currency, taxes, shipping, API keys, emails).
- Creates classic pages, products, coupons, registered customer.

```bash
npm run setup:store:default
```

### Setup block pages

```bash
npm run setup:checkout:block
```

### Setup classic pages

```bash
npm run setup:checkout:classic
```

### Setup taxes included

```bash
npm run setup:tax:inc
```

### Setup taxes excluded

```bash
npm run setup:tax:exc
```

### Setup US store and merchant (block pages)

```bash
npm run setup:pcp:usa
```

### Setup German store and merchant (block pages)

```bash
npm run setup:pcp:germany
```

### Setup Mexican store and merchant (block pages)

```bash
npm run setup:pcp:mexico
```

### Setup US store and merchant with vaulting (PayPal, ACDC) enabled (block pages)

```bash
npm run setup:pcp:usa:vaulting
```

### Setup US store and merchant with vaulting (PayPal, ACDC) enabled (classic pages)

```bash
npm run setup:pcp:usa:vaulting:classic
```

### Setup US store and merchant for Vaulting subscription (WC Subscriptions plugin, products, block pages)

```bash
npm run setup:pcp:usa:vaulting:subscription
```

### Setup US store and merchant for PayPal subscriptions (WC Subscriptions plugin, products, block pages)

```bash
npm run setup:pcp:usa:paypal:subscription
```
