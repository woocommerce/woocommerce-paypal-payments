# Code Quality

Shared code-quality rules for Claude skills and agents this repo. Only the `php-review` agent keeps a tuned, line-level subset of these rules inline by design and doesn't read this file.

## Table of Contents

- [Design Principles](#design-principles)
- [Code Smells](#code-smells)
- [Best Practices](#best-practices)

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
