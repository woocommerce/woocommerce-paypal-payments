# Store sync (agentic commerce)

Lets PayPal's AI agents discover products in the store and buy them on a shopper's behalf.

Three flows connect the store to PayPal. Two are outbound, driven by the store; one is inbound, driven by PayPal.

```
   ┌───────────────┐                                ┌────────────────────┐
   │               │  1. register the store  ─────► │                    │
   │               │  2. push product feed  ──────► │   joinhoney.com    │
   │  WooCommerce  │                                └────────────────────┘
   │     store     │                                ┌────────────────────┐
   │               │                                │                    │
   │               │ ◄────────  3. cart & checkout  │    PayPal agent    │
   └───────────────┘                                └────────────────────┘
```

**1. Onboarding** happens once. The store posts its identity to PayPal and stores the token it gets back. Until this succeeds, nothing else runs. See [architecture](architecture.md).

**2. The product feed** is a cron job. Every few minutes the store selects a batch of products and pushes them, so agents know what is for sale. PayPal drops products it has not seen recently, so this repeats forever rather than completing once. See [product catalog](product-catalog.md).

**3. Cart and checkout** is a REST API under `wc/v3/agentic`. An agent creates a cart, updates it, and completes checkout by calling the store. Every request carries a JWT and is validated against the store's business rules. See [REST API](rest-api.md).

Flows 1 and 2 both target `joinhoney.com`, which is worth knowing when a firewall or an outbound proxy is in play. Flow 3 arrives from an unspecified host: the store verifies that the token was issued by `paypal.com` and signed by a key published at `www.paypal.ai`. Fetching those public verification keys is a fourth, incidental outbound call.

## Documentation

| Document                                          | Read it for                                      |
|---------------------------------------------------|--------------------------------------------------|
| [Architecture](architecture.md)                   | How store sync works internally                  |
| [Setup and development](setup-and-development.md) | Turning the feature on, local development, logs  |
| [Product catalog](product-catalog.md)             | What agents see, and how to change or exclude it |
| [Cart validation](cart-validation.md)             | Enforcing your own rules on agent carts          |
| [REST endpoints](rest-api.md)                     | The cart and checkout endpoints                  |
| [JWT authentication](authentication-via-jwt.md)   | How agent requests are authenticated             |
| [Testing](testing.md)                             | Running this module's tests                      |

## Integrating from a third-party plugin

WordPress hooks are the entire integration surface. The module registers no public functions, and its container services cannot be decorated.

The most common starting points:

- Keep products out of the feed: see [product catalog](product-catalog.md)
- Add a business rule to carts: see [cart validation](cart-validation.md)
- React to a completed sync batch: see [product catalog](product-catalog.md)
