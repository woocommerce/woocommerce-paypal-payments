# Code Quality

Shared code-quality rules for Claude skills and agents this repo. Only the `review-php-code-cleanliness` agent keeps a tuned, line-level subset of these rules inline by design and doesn't read this file.

## Table of Contents

- [Design Principles](#design-principles)
- [Code Smells](#code-smells)
- [Best Practices](#best-practices)
- [Data Integrity](#data-integrity)

## Design Principles

### Single Responsible Principle

Classes and methods should handle one single concept or responsibility.

### Open Close Principle

The Software should be open to extension and closed to modification.

### Command Query Separation principle

A method should either return a value or perform a task but not both.

## Code Smells

### Long classes and methods

Long classes and methods that handles multiple concepts and responsibilities.

### Primitive Obsession

Instead of relying on primitive types (strings, arrays…) use dedicated objects that encapsulates the
primitives and allows adding validation like Value Objects which allows:

- Object integrity restrictions and validations that are not spread across the code base but in the
  object itself.
- Attracts related logic to the object
- Adds semantics

## Best Practices

### Modularity

Code inside `ExecutableModule::run` method should be added into WordPress hooks callbacks:

Good:

```php
public function run( ContainerInterface $container ): bool {
   add_action( 'admin_init', function () {
      wp_enqueue_script( 'wc-store-ai' );
```

Bad:

```php
public function run( ContainerInterface $container ): bool {
   wp_enqueue_script( 'wc-store-ai' );
```

### Hook documentation

Every `do_action()` and `apply_filters()` gets a docblock: one line on what the hook is for, plus a
`@param` for each argument passed. Hook consumers are third-party developers whose only reference is
this docblock. This is easy to forget and worth flagging. One concise line is ideal; do not pad it.

## Data Integrity

This is a payments plugin: destructive or state-changing operations act on money, orders, and
saved payment data. Treat them with more care than ordinary code.

### State-changing operations

Before code deletes or mutates persistent data (orders, refunds, vaulted tokens, subscriptions,
session or checkout data), confirm:

- The entity exists before acting on it.
- Its current state permits the operation (only delete a draft order, only refund a captured
  payment). Do not act on an assumed state.
- Ownership, for user or session scoped data (the current user or session owns the entity), to
  avoid acting on another party's data under a race.
- The return value is checked, not assumed to have succeeded.

### Request-triggered handlers

Any handler reachable from a request (AJAX, REST, webhook, `admin-post`) verifies capability
(`current_user_can()`) and nonce where applicable before doing work.

### Untrusted input

Sanitize and validate request data (`$_POST`, `$_GET`, webhook payloads) before use. Never pass a
raw request value into a data-store or gateway operation.
