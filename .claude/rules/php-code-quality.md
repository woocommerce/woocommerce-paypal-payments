---
description: PHP code quality rules for plugin code
paths:
  - "src/**/*.php"
  - "modules/**/*.php"
  - "*.php"
---

# PHP code quality

Tests follow the test writer conventions instead.

## Clean code is the role model, not existing code

Much existing code breaks these rules. Never copy a pattern because nearby code uses it (verbose comments, docblocks that repeat the signature, a docblock instead of a type). Match the module's naming and structure only.

## Comments and docblocks

- No comment by default. Comment only what PHP cannot express: why non-obvious code exists, or a contract the types cannot state.
- Use native types (parameter, return, property), not docblock tags. A tag is only for what the signature cannot say: an array shape (`array<string, Foo>`), `iterable<T>`, `@throws`, a callable contract.
- No notes about the current task, earlier bugs, or the conversation.
- No docblock above a service in `services.php`. A comment that a service needs goes on the first line inside its factory closure.
- Every `do_action()` and `apply_filters()` gets a docblock: one line on its purpose, plus a `@param` per argument. It is the only reference for third-party developers.

## Design

- **Single responsibility:** one concept per class or method. Split methods that mix fetch, transform, persist, and log.
- **Open/closed:** extend through services, extensions, and hooks, not by changing what callers depend on.
- **Command query separation:** a method returns a value or performs a task, not both.
- **Cohesion:** keep related logic together. No reaching across module boundaries, no globals, no dependence on another object's internals.
- **One source of truth:** a value that PHP shapes (interval, delay, threshold, limit) is computed in PHP and sent to JS as the final value. No JS constant for the same behavior.

## Simplicity

Clean code is readable code, not short code. Prefer an early `if` and a named variable over chained `?:`/`??` inside a condition, and plain string functions over a cryptic regex.

Write the minimum that solves the change. Before you hand it back, check whether any part can be removed. Do not add:

- An interface or abstract class with a single implementation.
- A factory, builder, or wrapper for a single, simple construction.
- A configuration option, parameter, or hook with only one caller or one possible value.
- A generalized mechanism (switch, strategy, registry) that handles a single case.
- A non-public method that only forwards to another method.

Stop and ask before you build a heavier mechanism than planned: raw `$wpdb` access, a new stored option, a retry or wait loop, or more than about two new helpers in one class. Name the simpler variant and its trade-off (a small, rare race is often acceptable).

Use a value object only on a real signal: the same primitives passed together through several signatures, or a primitive with implicit structure (a currency code, an array with a fixed key schema) that crosses a boundary.

## Modularity

`ExecutableModule::run()` only registers hook callbacks; it runs no logic itself.

```php
// Good
public function run( ContainerInterface $container ): bool {
	add_action( 'admin_init', function () {
		wp_enqueue_script( 'wc-store-ai' );
	} );

// Bad
public function run( ContainerInterface $container ): bool {
	wp_enqueue_script( 'wc-store-ai' );
```

## Data integrity

State changes here act on money, orders, and saved payment data.

- **State changes:** before code deletes or changes persistent data (orders, refunds, vaulted tokens, subscriptions, session or checkout data), check that the entity exists, that its state permits the operation (only refund a captured payment), and that the current user or session owns it. Check the return value.
- **Request handlers:** AJAX, REST, webhook, and `admin-post` handlers check capability (`current_user_can()`) and, where it applies, the nonce before they do work.
- **Untrusted input:** sanitize and validate request data (`$_POST`, `$_GET`, webhook payloads). Never pass a raw request value to a data store or gateway.
