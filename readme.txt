=== WooCommerce PayPal Payments ===
Contributors: woocommerce, automattic
Tags: woocommerce, paypal, payments, ecommerce, e-commerce, store, sales, sell, shop, shopping, cart, checkout
Requires at least: 5.3
Tested up to: 5.8
Requires PHP: 7.1
Stable tag: 1.6.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

PayPal's latest payments processing solution. Accept PayPal, Pay Later, credit/debit cards, alternative digital wallets and bank accounts.

== Description ==

PayPal's latest, most complete payment processing solution. Accept PayPal exclusives, credit/debit cards and local payment methods. Turn on only PayPal options or process a full suite of payment methods. Enable global transactions with extensive currency and country coverage.
Built and supported by [WooCommerce](https://woocommerce.com) and [PayPal](https://paypal.com).

= Give your customers their preferred ways to pay with one checkout solution =

Streamline your business with one simple, powerful solution.

With the latest PayPal extension, your customers can pay with PayPal, Pay Later options, credit & debit cards, and country-specific, local payment methods on any device — all with one seamless checkout experience.

= Reach more customers in 200+ markets worldwide =

Expand your business by connecting with over 370+ million active PayPal accounts around the globe. With PayPal, you can:

- Sell in 200+ markets and accept 100+ currencies
- Identify customer locations and offer country-specific, local payment methods

= Offer subscription payments to help drive repeat business =

Create stable, predictable income by offering subscription plans.

With the vaulting feature on PayPal, you can offer flexible plans with fixed or quantity pricing, set billing cycles for the time period you want, and offer subscriptions with discounted trial periods.

It’s easy for shoppers, simple for you, and great for your business–with no monthly or setup fees.

PayPal is also compatible with [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).

== Installation ==

= Requirements =

To install WooCommerce PayPal Payments, you need:

* WordPress Version 5.3 or newer (installed)
* WooCommerce Version 3.9 or newer (installed and activated)
* PHP Version 7.1 or newer
* PayPal business account

= Instructions =

1. Log in to WordPress admin.
2. Go to **Plugins > Add New**.
3. Search for the **WooCommerce PayPal Payments** plugin.
4. Click on **Install Now** and wait until the plugin is installed successfully.
5. You can activate the plugin immediately by clicking on **Activate** now on the success page. If you want to activate it later, you can do so via **Plugins > Installed Plugins**.

= Setup and Configuration =

Follow the steps below to connect the plugin to your PayPal account:

1. After you have activated the WooCommerce PayPal Payments plugin, go to **WooCommerce  > Settings**.
2. Click the **Payments** tab.
3. The Payment methods list will include two PayPal options. Click on **PayPal** (not PayPal Standard).
4. Click the **PayPal Checkout** tab.
5. Click on the **Connect to PayPal** button.
6. Sign in to your PayPal account. If you do not have a PayPal account yet, sign up for a new PayPal business account.
7. After you have successfully connected your PayPal account, click on the **Enable the PayPal Gateway** checkbox to enable PayPal.
8. Click **Save changes**.

== Screenshots ==

1. PayPal buttons on a single product page.
2. Cart page.
3. Checkout page.
4. Enable "PayPal" on the Payment methods tab in WooCommerce.
5. Click "Connect to PayPal" to link your site to your PayPal account.
6. Main settings screen.

== Changelog ==

= 1.6.1 =
* Fix - Handle authorization capture failures #312
* Fix - Handle denied payment authorization #302
* Fix - Handle failed authorizations when capturing order #303
* Fix - Transactions cannot be voided #293
* Fix - Fatal error: get_3ds_contingency() #310

= 1.6.0 =
* Add - Webhook status. #246 #273
* Add - Show CC gateway in admin payments list. #236
* Add - Add 3d secure contingency settings. #230
* Add - Improve logging. #252 #275
* Add - Do not send payee email. #231
* Add - Allow customers to see and delete their saved payments in My Account. #274
* Fix - PayPal Payments generates multiple orders. #244
* Fix - Saved credit card does not auto fill. #242
* Fix - Incorrect webhooks registration. #254
* Fix - Disable funding credit cards affecting hosted fields, unset for GB. #249
* Fix - REFUND_CAPTURE_CURRENCY_MISMATCH on multicurrency sites. #225
* Fix - Can't checkout to certain countries with optional postcode. #224

= 1.5.1 =
* Fix - Set 3DS contingencies to "SCA_WHEN_REQUIRED". #178
* Fix - Plugin conflict blocking line item details. #221
* Fix - WooCommerce orders left in "Pending Payment" after a decline. #222
* Fix - Do not send decimals when currency does not support them. #202
* Fix - Gateway can be activated without a connected PayPal account. #205

= 1.5.0 =
* Add - Filter to modify plugin modules list. #203
* Add - Filters to move PayPal buttons and Pay Later messages. #203
* Fix - Remove redirection when enabling payment gateway with setup already done. #206
* Add - PayPal Express Checkout compatibility layer. #207
* Fix - Use correct API to obtain credit card icons. #210
* Fix - Hide mini cart height field when mini cart is disabled. #213
* Fix - Address possible error on frontend pages due to an empty gateway description. #214

= 1.4.0 =
* Add - Venmo update #169
* Add - Pay Later Button –Global Expansion #182
* Add - Add Canada to advanced credit and debit card #180
* Add - Add button height setting for mini cart #181
* Add - Add BN Code to Pay Later Messaging #183
* Add - Add 30 seconds timeout by default to all API requests #184
* Fix - ACDC checkout error: "Card Details not valid"; but payment completes #193
* Fix - Incorrect API credentials cause fatal error #187
* Fix - PayPal payment fails if a new user account is created during the checkout process #177
* Fix - Disabled PayPal button appears when another button is loaded on the same page #192
* Fix - [UNPROCESSABLE_ENTITY] error during checkout #172
* Fix - Do not send customer email when order status is on hold #173
* Fix - Remove merchant-id query parameter in JSSDK #179
* Fix - Error on Plugin activation with Zettle POS Integration for WooCommerce #195

= 1.3.2 =
* Fix - Improve Subscription plugin support. #161
* Fix - Disable vault setting if vaulting feature is not available. #150
* Fix - Cast item get_quantity into int. #168
* Fix - Fix Credit Card form fields placeholder and label. #146
* Fix - Filter PayPal-supported language codes. #154
* Fix - Wrong order status for orders with contain only products which are both virtual and downloadable. #145
* Fix - Use order_number instead of internal id when creating invoice Id. #163
* Fix - Fix pay later messaging options. #141
* Fix - UI/UX for vaulting settings. #166

= 1.3.1 =
* Fix - Fix Credit Card fields for non logged-in users. #152

= 1.3.0 =
* Add - Client-side vaulting and allow WooCommerce Subscriptions product renewals through payment tokens. #134
* Add - Send transaction ids to woocommerce. #125
* Fix - Validate checkout form before sending request to PayPal #137
* Fix - Duplicate Invoice Id error. #143
* Fix - Unblock UI if Credit Card payment failed. #122
* Fix - Detected container element removed from DOM. #123
* Fix - Remove disabling credit for UK. #127
* Fix - Show WC message on account creating error. #136

= 1.2.1 =
* Fix - Address compatibility issue with Jetpack.

= 1.2.0 =
* Add - Rework onboarding code and add REST controller for integration with the OBW. #121
* Fix - Remove spinner on click, on cancel and on error. #124

= 1.1.0 =
* Add - Buy Now Pay Later for UK. #104
* Add - DE now has 12 month installments. #106
* Fix - Check phone for empty string. #102

= 1.0.4 =
* Fix - Check if WooCommerce is active before initialize. #99
* Fix - Payment buttons only visible on order-pay site when Mini Cart is enabled; payment fails. #96
* Fix - High volume of failed calls to /v1/notifications/webhooks #93
* Fix - GB country has ACDC blocked. #91

= 1.0.3 =
* Fix - Order with Payment received when Hosted Fields transaction is declined. #88

= 1.0.2 =
* Fix - Purchases over 1.000 USD fail. #84

= 1.0.1 =
* Fix - PayPal Smart buttons don't load when using a production/live account and `WP_Debug` is turned on/true. #66
* Fix - [Card Processing] SCA/Visa Verification form loads underneath the Checkout blockUI element. #63
* Fix - Attempting to checkout without country selected results in unexpected error message. #67
* Fix - Remove ability to change shipping address on PayPal from checkout page. #72
* Fix - Amount value should be a string when send to the api. #76
* Fix - "The value of a field does not conform to the expected format" error when using certain e-mail addresses. #56
* Fix - HTML tags in Product description. #79

= 1.0.0 =
* Initial release.
