# Payment Method Title Filters

## Overview

The plugin enriches the payment method title of an order with the contextual payment details it
received from PayPal, so an order shows `PayPal (buyer@example.com)` or
`Debit & Credit Cards (Visa ending in 1234)` instead of just the gateway name.

Enrichment happens in `PaymentMethodTitleEnricher::enrich()`, hooked onto WooCommerce's
`woocommerce_order_get_payment_method_title` filter at priority `10`. It applies to four gateways —
`ppcp-gateway`, `ppcp-credit-card-gateway`, `ppcp-applepay` and `ppcp-googlepay` — and builds the
detail from order meta written at capture time:

- Payment source `paypal` → the payer email (`_ppcp_paypal_payer_email`).
- Payment sources `card`, `apple_pay`, `google_pay` → card brand plus last four digits
  (`_ppcp_paypal_card_brand`, `_ppcp_paypal_card_last_digits`). Both must be present.
- Any other payment source produces no detail, and the title is left unchanged.

Four filters let you control the result. They are useful when you render orders yourself — custom
order views, emails, PDF invoices or export integrations — and need a different format.

## Filters

### `woocommerce_paypal_payments_enrich_payment_method_title`

Kill-switch for the whole feature. Return `false` to leave every payment method title untouched. It is
evaluated first, so neither of the other two filters runs when it returns
`false`.

```php
add_filter(
    'woocommerce_paypal_payments_enrich_payment_method_title',
    function ( bool $enrich, WC_Order $order ): bool {
        // Keep the plain gateway name on orders synced to the ERP.
        return $order->get_meta( '_my_plugin_erp_synced' ) ? false : $enrich;
    },
    10,
    2
);
```

### `woocommerce_paypal_payments_payment_method_title_detail`

Filters just the detail string, before it is wrapped in parentheses and appended. Return an empty
string to suppress the append for this order without disabling enrichment globally.

```php
add_filter(
    'woocommerce_paypal_payments_payment_method_title_detail',
    function ( string $detail, WC_Order $order ): string {
        // Mask the payer email: "j***@example.com".
        if ( is_email( $detail ) ) {
            return preg_replace( '/^(.).*(@.*)$/', '$1***$2', $detail );
        }

        return $detail;
    },
    10,
    2
);
```

Notable behavior:

- It runs once per `enrich()` call, only for the four supported gateways. It cannot bring an
  unsupported gateway into enrichment.
- It also runs when the plugin found no detail, receiving an empty string. That is the hook to use
  when you want to add a detail the plugin does not produce — Venmo or other alternative payment
  method sources, or card orders whose brand/last-digits meta is missing.

### `woocommerce_paypal_payments_payment_method_title_icon`

Opts into an icon in front of the detail. It defaults to an empty string, so no icon is rendered
unless you return markup. The second argument is the URL of the bundled SVG for this order's payment
source and card brand, already resolved for you.

```php
add_filter(
    'woocommerce_paypal_payments_payment_method_title_icon',
    function ( string $icon_html, string $icon_url ): string {
        // Only render markup where it is safe to do so.
        if ( ! is_order_received_page() || '' === $icon_url ) {
            return $icon_html;
        }

        return '<img class="my-pm-icon" src="' . esc_url( $icon_url ) . '" alt="">';
    },
    10,
    2
);

add_action(
    'wp_head',
    function (): void {
        if ( ! is_order_received_page() ) {
            return;
        }

        echo '<style>.my-pm-icon{display:inline;height:1em;vertical-align:middle}</style>';
    }
);
```

**The payment method title may be used in contexts where markup is unsafe** — plain-text emails, PDF
invoices, REST responses, CSV exports etc. That is why the default is no icon: your
callback is responsible for the context check, as in the `is_order_received_page()` gate above.

Notable behavior:

- Front-end order views print the title through `wp_kses_post()`, which filters the `style`
  attribute against a fixed CSS property allow-list, which removes some properties, such as `display`.
  To avoid this, style the icon with a `class` rather than inline CSS.
- The markup is prepended inside the parentheses, separated by a space, and becomes part of the
  `$detail` argument that `woocommerce_paypal_payments_enriched_payment_method_title` receives.
- `$icon_url` is empty unless the brand has a bundled icon. Covered are Visa, Mastercard, American
  Express, Discover, JCB, Elo, Hiper, PayPal and Venmo.
- Apple Pay and Google Pay orders resolve to the icon of the underlying card brand.
- `PaymentMethodTitleEnricher::get_icon_url( string $source, string $brand ): string` is public, so
  you can resolve a different brand than the order's if you need to.

### `woocommerce_paypal_payments_enriched_payment_method_title`

Filters the fully assembled title, so you can change the separator, drop the parentheses or add
markup.

```php
add_filter(
    'woocommerce_paypal_payments_enriched_payment_method_title',
    function ( string $enriched, string $title, string $detail, WC_Order $order ): string {
        // Use an en dash instead of parentheses.
        return sprintf( '%1$s &ndash; %2$s', $title, $detail );
    },
    10,
    4
);
```

Notable behavior:

- `$title` is the original gateway title and `$detail` is the detail after
  `woocommerce_paypal_payments_payment_method_title_detail` and any icon markup from
  `woocommerce_paypal_payments_payment_method_title_icon` have been applied.
- It runs only when a detail is actually appended — never when enrichment was switched off, the
  gateway is unsupported, there is no detail, or the title already contains the detail.

## Notes

- The plugin does not escape the filtered values. The consumers escape the payment method title at output (`esc_html()` in templates, `wp_kses_post()` in HTML emails), so
  escaping here would double-escape. If you interpolate untrusted data such as customer-supplied
  meta, escape it yourself.
- `woocommerce_order_get_payment_method_title` fires on every
  `WC_Order::get_payment_method_title()` call — order list and edit screens, each email, the REST API,
  exports. Keep your callbacks cheap and free of side effects; do not run queries or remote requests
  in them.
- WooCommerce Subscriptions copies the resulting title onto the subscription, so changes here also
  affect subscription and renewal order views.
- To change the title on paths these filters intentionally skip, hook
  `woocommerce_order_get_payment_method_title` directly at a priority above `10`.
