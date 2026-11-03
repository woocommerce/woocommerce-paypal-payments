# AGENTS.md

WooCommerce PayPal Payments: a WooCommerce plugin for PayPal smart buttons, card fields, Apple Pay, Google Pay, Pay Later, Fastlane, vaulting, subscriptions, and local payment methods.

## Critical rules

- PHP 7.4 compatible. Typed properties are fine; PHP 8 syntax (union types, enums, named arguments, constructor promotion) is not.
- `assets/` and `lib/packages/` are generated. Edit the sources: `modules/*/resources/` for frontend code.
- A change to an external surface (hook, `api/` function, stored data, runtime assumption) needs a backward-compatibility statement in the PR. See below.

## Backward compatibility

The contracts with third-party code are hooks, the functions in `api/`, stored data, and assumptions about the runtime. PHP classes, interfaces, and methods are not contracts, even when the container exposes them: change or remove them freely.

Assume that unseen code uses every external surface. If you cannot establish the impact of a change, stop and ask.

- **Hooks:** never remove or rename a hook, or remove or reorder its arguments. Add new arguments at the end. A change to when or whether a hook fires can also break consumers. To retire a hook, use `do_action_deprecated()` / `apply_filters_deprecated()`.
- **Hook data:** keep callback parameters untyped, and validate the value before it reaches typed code. Validate the final return value of every filter this plugin fires.
- **Global state:** code runs in admin, REST, WP-CLI, cron, webhook, and front-end contexts. Only front-end requests set `$post`, `$wp_query`, the session, and the cart. Guard each dependency: `function_exists`/`class_exists`, `isset`, `did_action`, and a check that `WC()` and its component exist before `WC()->…`. Webhook and cron paths are the most exposed. Do not use container services before the module has booted.
- **Multisite:** site and network data live in different places (`get_option` vs `get_site_option`, per-site tables, roles, upload paths). A PR that reads or writes site state (credentials, onboarding, gateway settings) states whether it works on multisite, and says so when it was not tested there.
- **Install layout:** WordPress can run in a subdirectory, with a moved `wp-content`, or behind a proxy. Derive paths and URLs (`plugins_url()`, `plugin_dir_path()`, `wp_upload_dir()`; `home_url()` is not `site_url()`), never concatenate them. This is most important for the return, callback, and webhook URLs sent to PayPal.

### Database migrations

Migrations run from `CompatModule` (`modules/ppcp-compat`) on `woocommerce_paypal_payments_gateway_migrate_on_update`. A marker option guards each one (e.g. `woocommerce_ppcp-is_pay_later_settings_migrated`).

- A migration runs once per site. Never edit a shipped migration or reuse its marker: add a new migration with a new marker.
- The previous release must still work on migrated data. If old code cannot read the new format, the change needs a deprecation window.

## Project structure

The plugin uses Syde Modularity: each feature is a module in `modules/`, wired through `services.php`, `factories.php`, and `extensions.php` into a PSR-11 container. Architecture reference: `docs/plugin-architecture.md`.

```
woocommerce-paypal-payments.php  # Plugin entry point, constants, version
bootstrap.php                    # Container init, module loading
modules.php                      # Module registration and feature flags
src/                             # Core: PluginModule, PPCP container, Http
api/                             # Public API functions
modules/                         # Feature modules: src/, resources/, services.php
assets/                          # Built JS/CSS (webpack output)
lib/                             # Vendored packages, namespace-scoped by Mozart under WooCommerce\PayPalCommerce\Vendor
tests/PHPUnit/                   # PHP unit tests
tests/integration/               # Integration tests, run against the DDEV site
tests/js/                        # Jest config; Jest tests sit next to their sources
stubs/                           # PHPStan stubs
```

- Add behavior through module services and extensions, not globals. `ExecutableModule::run()` only registers hook callbacks.
- Some modules load only when their flag is on (`PCP_*_ENABLED`, `woocommerce.feature-flags.*`). When you add or remove a module, update `modules.php` and test both flag states.
- The admin settings UI is React (`@wordpress/element` + WooCommerce components) in `modules/ppcp-settings`.

## Conventions

- Text domain: `woocommerce-paypal-payments`.
- American English in code, comments, docs, and commits ("canceled", "behavior").
- `dev/develop` is the source branch and the PR base. The bare `develop` branch is a generated build artifact; never diff or branch from it.
- Tabs for PHP and JS, spaces for JSON and YAML.
- PHPCS (WooCommerce-Core), PHPStan level 8, ESLint (`@wordpress/eslint-plugin/recommended`).
- A PR has reproducible test steps and a changelog line.

## Commands

Run every command through DDEV: `ddev npm`, `ddev npx`, `ddev composer`, `ddev php`, and `ddev exec` for anything else.

| Task                   | Command                                                          |
|------------------------|------------------------------------------------------------------|
| Start the site         | `ddev start && ddev orchestrate` (again if WP is missing)        |
| Build JS/SCSS          | `ddev npm run build`, or `ddev npm run watch-js`                 |
| Reinstall dependencies | `ddev composer install && ddev npm ci && ddev npm run build`     |
| PHPCS + PHPStan        | `ddev npm run lint`; auto-fix with `ddev npm run fix-lint`       |
| JS lint                | `ddev npx wp-scripts lint-js <path>` (full-repo `lint-js` fails) |
| PHP unit tests         | `ddev npm run unit-tests`, or `ddev npm run tdd [<filter>]`      |
| JS unit tests          | `ddev npm run test:unit-js`                                      |
| Integration tests      | `ddev npm run integration-tests`                                 |
| PHP unit + integration | `ddev npm run test`                                              |

Site: URLs in `ddev describe`; admin login `admin` / `admin`. If `.ddev.site` does not resolve, use the `web:80` host mapping from `ddev describe`. For DDEV problems, see "Troubleshooting DDEV setup" in `README.md`.
