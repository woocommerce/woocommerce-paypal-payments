# AGENTS.md

Minimal instructions for coding agents working in this repository.

## CRITICAL Rules

- CRITICAL: Keep `CLAUDE.md` as a pointer to this file (`@AGENTS.md`).
- CRITICAL: Maintain PHP `7.4+` compatibility unless project requirements change.
- CRITICAL: Do not edit WordPress core, `vendor/`, or `node_modules/`.
- CRITICAL: For frontend work, edit `modules/*/resources/*`.
- CRITICAL: Never revert unrelated local changes.
- CRITICAL: Any signature change to public or externally exposed code is high-risk — state its backward-compatibility impact in the PR (see [Backward Compatibility](#backward-compatibility)).
- MUST: Run relevant lint/tests before claiming completion.

## Backward Compatibility

Any change to a **public or externally exposed** class, interface, function, method, or hook signature is **high-risk** and **must state its backward-compatibility impact in the PR description** — regardless of which module or namespace the symbol lives in. Being under `WooCommerce\PayPalCommerce\...`, an `Internal` sub-namespace, or a module's `src/` is not a guarantee that a symbol is safe to change: extensions, themes, and other plugins implement and consume some of these contracts in practice.

Treat a symbol as **externally exposed** when it is implemented or consumed outside this plugin - by extensions, other plugins, or themes. This includes:

- WordPress actions and filters this plugin fires or documents (e.g. `ppcp_*`, `woocommerce_paypal_payments_*`) - their names, argument counts, and argument types are a public contract.
- Public classes, interfaces, and methods reachable via the module container, `services.php`/`factories.php`/`extensions.php` wiring, or returned from public APIs.
- Any interface external code can implement, and any class external code can extend or instantiate.

When in doubt, assume it is exposed and state the BC impact.

**Adding a method to an interface that external code can implement is a backward-incompatible change** and must be flagged explicitly: existing implementers fatal on load because they no longer satisfy the contract. Removing a required interface method is likewise breaking for existing implementers. Prefer a non-breaking alternative - add the method to the concrete class rather than the interface, introduce a separate new interface, or supply a default via an abstract base class.

**Deprecate, don't rename.** For existing public symbols (classes, interfaces, methods, constants, hooks), never rename or remove them in place. Mark the old symbol `@deprecated`, introduce the replacement alongside it, and keep both working through a deprecation window so external consumers have time to migrate.

## Project Knowledge

- This is a modular WooCommerce/WordPress plugin using Syde Modularity.
- Key entry points:
  - `woocommerce-paypal-payments.php` (plugin bootstrap/constants)
  - `bootstrap.php` (container + module boot)
  - `modules.php` (module registration and feature-flag loading)
- Most feature code is in `modules/*`.
- Service wiring is module-based (`services.php`, `factories.php`, `extensions.php`).
- Architecture reference: `docs/plugin-architecture.md`.

## Commands

### Setup

- Preferred local env: DDEV.
- `npm run ddev:setup` (start + orchestrate).
- If WP is missing after startup, run `ddev orchestrate`.
- Use `ddev describe` for active URLs/ports.
- If `.ddev.site` routing fails, use the direct host mapping shown in `ddev describe` for `web:80` (for example `http://127.0.0.1:60792`).
- Admin defaults: `admin` / `admin` at `/wp-admin`.
- Common DDEV issues and fixes are documented in the README under "Troubleshooting DDEV setup" (vmnetd/port errors, Docker CLI path, SSL trust, Mutagen warnings).

### Build

- Non-DDEV setup: `composer install && npm ci && npm run build`.

### Quality

- `npm run lint` (PHPCS + PHPStan)
- `npm run lint-js` (currently unreliable for full-repo linting in this project)
- `npx wp-scripts lint-js <file-or-dir>` (recommended; works for targeted JS/TS paths)
- `npm run unit-tests`
- `npm run test:unit-js`
- `npm run integration-tests`
- `npm run test` (full suite)
- `npm run ddev:unit-tests:coverage` (coverage in DDEV)

## Conventions

- Add or extend behavior through module services/extensions, not ad-hoc globals.
- If adding/removing a module, update `modules.php` and validate feature-flag behavior.
- Keep i18n text domain as `woocommerce-paypal-payments`.
- PRs should include reproducible test steps and a changelog summary.

## Architectural Decisions

- The plugin is intentionally modular; do not collapse it into a monolith.
- Some modules are intentionally conditional via `PCP_*_ENABLED` and `woocommerce.feature-flags.*`.
- JS/SCSS build output is intentionally centralized in root `/assets` via webpack.
- WordPress hook-based integration and service registration are intentional patterns.

## Common Pitfalls

- Editing generated `/assets` files instead of module `resources`.
- Forgetting to rebuild after JS/SCSS source changes.
- Introducing PHP syntax that is not PHP 7.4 compatible.
- Accessing plugin container/services before initialization.
- Skipping integration tests for checkout, payment, onboarding, or webhook changes.
- Changing module load order/feature flags without validating side effects.
- Assuming `.ddev.site` URLs always resolve locally.

## Working in `modules/ppcp-abilities/`

When you change the code path behind a registered ability (the backing
REST controller, service method, or response shape), audit the
ability's registration for required updates — annotations, input/output
schema, description, and the redaction list on `GetConnectionStatus`.
Drift between the ability and its backing is the failure mode the
`woocommerce_paypal_payments_abilities_enabled` feature flag exists to
contain; surface the change in the PR rather than letting the
abilities surface go stale.

## Verification Matrix

- PHP-only change: `npm run unit-tests && npm run lint`
- JS-only change: `npm run test:unit-js && npx wp-scripts lint-js <changed-js-files-or-dir>`
- Checkout/payment/onboarding/webhook change: `npm run test`
