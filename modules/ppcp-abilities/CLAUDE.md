# Abilities module

Each ability in `src/Domain/` wraps a code path from another module (settings, order tracking, the API client). When you change an ability or the code path behind it (the REST controller, service method, or response shape), check the ability's registration: annotations, input and output schema, description, and the redaction list in `GetConnectionStatus`.

The `woocommerce_paypal_payments_abilities_enabled` feature flag exists to contain drift between an ability and the code behind it. State such a change in the PR, so the abilities do not go stale.
