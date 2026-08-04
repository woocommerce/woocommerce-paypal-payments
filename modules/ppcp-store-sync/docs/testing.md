# Testing

| Suite       | Location                              |
|-------------|---------------------------------------|
| Unit        | `tests/PHPUnit/StoreSync`             |
| Integration | `tests/integration/PHPUnit/StoreSync` |

To run them, use the command:

```sh
# Run regular unit tests, stop on first failure.
ddev npm run tdd StoreSync

# Run integration tests, stop on first failure.
ddev npm run tdd:integration StoreSync
```

Note that the integration tests require a basic setup. See the [Integration-Test Readme](/tests/integration/README.md)

## Helpers

`StoreSyncTestCase` is the base class for this module's unit tests. Its `assertMoneyValue()` checks the full money schema at once, including the two-decimal string format that catches float conversion errors.

`CartPayloadBuilder` builds agent cart payloads fluently, with fixtures for items and for US and German shipping. Use it instead of hand-writing payload arrays.

## Live network calls

`ProductIngestionWebhookTest` posts to PayPal's staging ingestion endpoint for real, and it has no skip guard. It fails without outbound network access, which looks like a code fault but is not one.
