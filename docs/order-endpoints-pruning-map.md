# ppcp-order-endpoints: pruning map (v5-only branches and residual coupling)

Written for PCP-6677, which relocated the shared WC-AJAX order endpoints
(`ppc-create-order`, `ppc-approve-order`, `ppc-change-cart`,
`ppc-update-shipping`) and their helpers from the v5 frontend modules
(`ppcp-button`, `ppcp-blocks`) into the neutral `ppcp-order-endpoints` module
as a pure move. This note maps what was intentionally NOT cleaned up, so a
future v5 sunset can prune it deliberately. Pruning itself was out of scope.

## Canonical service keys and aliases

The canonical definitions live in `modules/ppcp-order-endpoints/services.php`;
the old keys resolve via one-line aliases (same cached singleton):

| Old key (alias) | Canonical key |
|---|---|
| `button.request-data` | `order-endpoints.request-data` |
| `button.endpoint.create-order` | `order-endpoints.endpoint.create-order` |
| `button.endpoint.approve-order` | `order-endpoints.endpoint.approve-order` |
| `button.endpoint.change-cart` | `order-endpoints.endpoint.change-cart` |
| `blocks.endpoint.update-shipping` | `order-endpoints.endpoint.update-shipping` |
| `button.helper.cart-products` | `order-endpoints.helper.cart-products` |
| `button.helper.wc-order-creator` | `order-endpoints.helper.wc-order-creator` |
| `button.helper.early-order-handler` | `order-endpoints.helper.early-order-handler` |
| `button.handle-shipping-in-paypal` | `order-endpoints.handle-shipping-in-paypal` |
| `button.pay-now-contexts` | `order-endpoints.pay-now-contexts` |
| `button.early-wc-checkout-validation-enabled` | `order-endpoints.early-wc-checkout-validation-enabled` |
| `button.current-user-must-register` | `order-endpoints.current-user-must-register` |
| `button.is-logged-in` | `order-endpoints.is-logged-in` |
| `button.registration-required` | `order-endpoints.registration-required` |

The two `modules/ppcp-blocks/extensions.php` entries for `pay-now-contexts`
and `handle-shipping-in-paypal` were re-keyed to the canonical
`order-endpoints.*` keys — Modularity extensions apply per service id, so an
extension left on an alias key would not reach consumers of the canonical key.
Third parties extending the OLD keys (via modules injected through the
`woocommerce_paypal_payments_modules` filter) would extend only the alias.

## Classes intentionally left in ppcp-button (imported by the moved code)

- `Button\Endpoint\EndpointInterface` — implemented by the moved endpoints and
  by ~14 endpoints across other modules.
- `Button\Exception\{NonceValidationException, ValidationException,
  RuntimeException}` — thrown/caught by the moved code;
  `NonceValidationException` is also caught by ~15 endpoints in other modules,
  and extends the Button `RuntimeException`.
- `Button\Helper\{Context, ThreeDSecure}` — used by `ApproveOrderEndpoint`.
- `Button\Session\{CartData, CartDataFactory, CartDataTransientStorage}` —
  AppSwitch cross-browser cart recovery, used by `CreateOrderEndpoint` and
  `WooCommerceOrderCreator`.
- `Button\Validation\CheckoutFormValidator` — classic-checkout early
  validation, used by `CreateOrderEndpoint`.
- `SimulateCartEndpoint` (extends the moved `AbstractCartEndpoint`, depends on
  `SmartButton`) and `ApproveSubscriptionEndpoint` (subscription-domain
  dependencies) stay in `ppcp-button`.

## Old-module keys the new module still resolves

`button.session.factory.card-data`, `button.session.storage.card-data.transient`,
`button.helper.three-d-secure`, `button.helper.context`,
`blocks.settings.final_review_enabled`.

A v5 sunset that stops REGISTERING `ppcp-button`/`ppcp-blocks` services (not
just their frontends) must first re-home these keys and the classes above.

## v5-only branches inside the moved endpoints

- `CreateOrderEndpoint`: classic-checkout `form_encoded` parsing and
  `CheckoutFormValidator` early validation (`context: checkout`); the
  `pay-now` branches (order_id/order_key lookup, terms-page validation,
  purchase unit from WC_Order, payer from checkout form);
  `EarlyOrderHandler` (classic-checkout early WC-order creation);
  `createaccount` registration; CartData transient storage for AppSwitch.
- `ApproveOrderEndpoint`: card/3DS hosted-fields branches (`ThreeDSecure`,
  DCC disabled-cards), `Context` helper, final-review WC-order creation.
- `ChangeCartEndpoint`, `UpdateShippingEndpoint`: no v5-only branches.

## Registration

All four `wc_ajax_ppc-*` hooks are registered unconditionally in
`OrderEndpointsModule::run()`. Before PCP-6677, `ppc-update-shipping` was
registered by `BlocksModule` only when WC Blocks was available, while
`SmartButton` and the v6 SDK advertised it unconditionally.

Pinned by the contract tests in `tests/integration/PHPUnit/Button/Endpoint/`
(PCP-6675), which resolve the endpoints by the OLD service keys.
