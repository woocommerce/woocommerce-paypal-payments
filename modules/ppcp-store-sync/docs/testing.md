# Testing

Unit tests for this module are located in the `tests/PHPUnit/AgenticCommerce` directory.

To run them, use the command:

```sh
# Run regular unit tests, stop on first failure.
yarn ddev:tdd Agentic

# Run integration tests, stop on first failure.
yarn ddev:tdd:integration Agentic
```

Note that the integration tests require a basic setup. See the [Integration-Test Readme](/tests/integration/README.md)
