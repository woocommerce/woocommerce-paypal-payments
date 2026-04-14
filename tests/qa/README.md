# WooCommerce PayPal Payments Plugin Tests

Depends on [`@inpsyde/playwright-utils`](https://github.com/inpsyde/playwright-utils) package.

Detailed information about current test project can be found in [docs](./docs/README.md).

## Table of Content

- [Local installation](#local-installation)
- [Installation of `node_modules`](#installation-of-node_modules)
- [Installation of `playwright-utils` for local development](#installation-of-playwright-utils-for-local-development)
- [Project configuration](#project-configuration)
- [Run tests](#run-tests)
    - [Additional options to run tests from command line](#additional-options-to-run-tests-from-command-line)
- [Autotest Execution workflow](#autotest-execution-workflow)
- [Coding standards](#coding-standards)
- [Automated env setup scripts](#automated-env-setup-scripts)


## Local installation

1. Clone repository locally:

	```bash
	git clone https://github.com/woocommerce/woocommerce-paypal-payments.git
	```

## Installation of `wp env`, PlayWright and PayPal plugin

> See also [@inpsyde/playwright-utils documentation](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#installation).

1. Make sure you're logged in the [Syde npm package registry](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3112894465/GitHub+Package+Registry+for+npm).

2. In the terminal change directory to `./tests/qa` and run following command:

	```bash
	npm run setup:all
	```

This will run the next scripts:

- `setup:env` -- Setup `wp env` and required plugins for running tests in http://localhost:8889
- `setup:plugin` -- Compile WooCommerce PayPal Payments plugin, generates a ZIP and moves it into resources/files to be used in tests
- `setup:tests` -- Setup required PlayWright libraries and utils

## Project configuration (Devs)

1. In the test project directory (`./tests/qa/`) create and configure `.env` file:

2. Set general variables following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables).
	
3.  Set PayPal API keys and test credentials. See `.env.example`. The `.env` content with actual test users' credentials is [stored in 1Password](https://start.1password.com/open/i?a=UL7QZZ6P6JDVBI422AOVJXMEGU&v=uthlbcp4jkori6w6rhgxvsvfoe&i=klejf7rgcip76c7auhsnhvxcbi&h=inpsyde.1password.eu).

## Project configuration (QA team)

1. [SSE setup](https://inpsyde.atlassian.net/wiki/spaces/AT/pages/3175907370/Self+Service+WordPress+Environment) - will be deprecated in Q1 of 2025.

2. In the test project directory (`./tests/qa/`) create and configure `.env` file:

	2.1 Set general variables following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#env-variables).
	
	2.2 Set PayPal API keys and test credentials. See `.env.example`. The `.env` content with actual test users' credentials is [stored in 1Password](https://start.1password.com/open/i?a=UL7QZZ6P6JDVBI422AOVJXMEGU&v=uthlbcp4jkori6w6rhgxvsvfoe&i=klejf7rgcip76c7auhsnhvxcbi&h=inpsyde.1password.eu).

3. Configure `playwright.config.ts` of the project following [these steps](https://github.com/inpsyde/playwright-utils?tab=readme-ov-file#playwright-configuration).

4. Configure reporting to __Xray in Jira__ following [these steps](https://github.com/inpsyde/playwright-utils/blob/main/docs/test-report-api/report-to-xray.md).

5. To avoid conflicts make sure any other payment plugins are deleted.

6. Additional website and WooCommerce configuration is done automatically via `setup-woocommerce` dependency project (see [`/tests/_setup/woocommerce.setup.ts`](./tests/_setup/woocommerce.setup.ts)).

## Running tests

- `npm run tests:all` -- Runs all the tests
- `npm run tests:critical` -- Runs all the critical tests
- `npm run tests:onboarding` -- Runs all the onboarding tests

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

## Reset Kinsta env

> **Note:** the staging env on Kinsta should be created and the script to reset env [provided by devops](https://inpsyde.atlassian.net/wiki/spaces/ENG/pages/6240338010/WordPress+hosting+FAQs#How-can-QA-reset-a-test-environment%3F) (if not - create a ticket on [SDO board](https://inpsyde.atlassian.net/jira/software/c/projects/SDO/boards/395)).

Find SSH data in [Kinsta dashboard](https://my.kinsta.com/sites/details) for your tested env. Replace data in the following one-line command and run it in the terminal to reset the env:

```bash
ssh <your-ssh-username>@<your-ssh-host> -p <your-ssh-port> '${HOME}/bin/reset-wp.sh --wp-version=6.9 --wp-type=single && exit'
```

## Automated env setup scripts for migration

### Preconditions

Local usage of _automated env setup scripts_ assumes that the following steps are fulfilled:

1. Clone PayPal repo to your local machine:

	```bash
	git clone https://github.com/woocommerce/woocommerce-paypal-payments.git
	```

	> **Note:** temporary, for migration testing change branch to `dev/qa/migration-tests`: `git checkout dev/qa/migration-tests`.

2. Copy following packages into `/tests/qa/resources/files`:

	* Configured PayPal plugin package (e.g. v3.4.1) named as `woocommerce-paypal-payments.zip`

	* Optional: Paypal plugin version to upgrade/downgrade to (e.g. v3.0.0 or v4.0.0) as `woocommerce-paypal-payments-update.zip`.
	
	* WooCommerce Subscriptions package named as `woocommerce-subscriptions.zip`

3. In the terminal open the cloned repo and navigate to `/tests/qa` dir:

	```bash
	cd tests/qa
	```

4. Install Node dependencies and Playwright:

	```bash
	npm run setup:tests
	```

5.  In the `/tests/qa` directory create a `.env` file and copy-paste content from `PCP .env` vault of 1Password replacing all the data for your test env. Alternatively use `.env.example`.

6. Run the scripts described below.

### Script naming convention

Scripts follow a three-tier naming pattern:

| Prefix | Meaning | Example |
|---|---|---|
| `env:reset` | Reset only env + WooCommerce | `npm run env:reset` |
| `env:reset:pcp:*` | Reset env + PCP setup (single run) | `npm run env:reset:pcp:usa` |
| `env:setup:*` | PCP/config setup only, no reset | `npm run env:setup:pcp:usa` |

### Reset env and WooCommerce setup

- Resets the env
- Configures website permalinks (`%postname%`)
- Installs plugins and themes:
	- WooCommerce
	- Storefront theme
	- Additional plugins (Disable Nonce, WC Subscriptions, etc.)
- Configures WooCommerce for default country (USA):
	- API keys
	- Country/currency: USA/USD
	- Taxes: included, 10% rate
	- Shipping: flat rate/10 USD and free
	- Emails: disabled
- Creates test entities:
	- Classic cart and checkout
	- Tested products
	- Coupons
	- Registered US customer

```bash
npm run env:reset
```

**WooCommerce setup only (no SSH reset):**

```bash
npm run env:setup:wc
```

### Setup PCP for specific country

- Installs PCP plugin (`woocommerce-paypal-payments.zip`).
- Connects merchant from specified country.

**For USA** — PCP + connected US merchant + ACDC enabled (PayPal and other PMs disabled).

| With reset | Without reset |
|---|---|
| `npm run env:reset:pcp:usa` | `npm run env:setup:pcp:usa` |

**For Germany & PUI (disabled by default)**

| With reset | Without reset |
|---|---|
| `npm run env:reset:pcp:germany` | `npm run env:setup:pcp:germany` |

**For Mexico & OXXO (disabled by default)**

| With reset | Without reset |
|---|---|
| `npm run env:reset:pcp:mexico` | `npm run env:setup:pcp:mexico` |

### Upgrade PCP (without env reload)

Installs `woocommerce-paypal-payments-update.zip` over the existing PCP installation.

```bash
npm run env:setup:pcp:update
```

### Switch checkout layout (without env reload)

**Enable classic cart/checkout**

```bash
npm run env:setup:checkout:classic
```

**Enable block cart/checkout**

```bash
npm run env:setup:checkout:block
```

### Switch tax configuration (without env reload)

**Tax included in prices**

```bash
npm run env:setup:tax:inc
```

**Tax excluded from prices**

```bash
npm run env:setup:tax:exc
```
