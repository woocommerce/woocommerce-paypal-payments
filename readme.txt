=== WooCommerce PayPal Payments ===
Contributors: woocommerce, automattic
Tags: woocommerce, paypal, payments, ecommerce, e-commerce, store, sales, sell, shop, shopping, cart, checkout
Requires at least: 5.3
Tested up to: 6.0
Requires PHP: 7.1
Stable tag: 1.8.1
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

= 1.8.1 =
* Fix - Manual orders return an error for guest users when paying with PayPal Card Processing #530
* Fix - "No PayPal order found in the current WooCommerce session" error for guests on Pay for Order page #605
* Fix - Error on order discount by third-party plugins #548
* Fix - Empty payer data may cause CITY_REQUIRED error for certain checkout countries #632
* Fix - Mini Cart smart buttons visible after adding subscription product to cart from "shop" page while Vaulting is disabled #624
* Fix - Smart buttons not loading when free product is in cart but shipping costs are available #606
* Fix - Smart button & Pay Later messaging disappear on the cart page after changing shipping method #288
* Fix - Disabling PayPal Checkout on the checkout page also removes the button from the Cart and Product Pages #577
* Fix - Partial refunds via PayPal are created twice/double in WooCommerce order #522
* Fix - Emoji in product description causing INVALID_STRING_LENGTH error #491
* Enhancement - Vaulting & Pay Later UI/UX #174
* Enhancement - Redirect after updating settings for DCC sends you to PPCP settings screen #392
* Enhancement - Add Fraud Processor Response as an order note #616
* Enhancement - Add the Paypal Fee to the Meta Custom Field for export purposes #591

= 1.8.0 =
* Add - Allow free trial subscriptions #580
* Fix - The Card Processing does not appear as an available payment method when manually creating an order #562
* Fix - Express buttons & Pay Later visible on variable Subscription products /w disabled vaulting #281
* Fix - Pay for order (guest) failing when no email address available #535
* Fix - Emoji in product description causing INVALID_STRING_LENGTH error #491
* Enhancement - Change cart total amount that is sent to PayPal gateway #486
* Enhancement - Include dark Visa and Mastercard gateway icon list for PayPal Card Processing #566
* Enhancement - Onboarding errors improvements #558
* Enhancement - "Place order" button visible during gateway load time when DCC gateway is selected as the default #560

= 1.7.1 =
* Fix - Hide smart buttons for free products and zero-sum carts #499
* Fix - Unprocessable Entity when paying with AMEX card #516
* Fix - Multisite path doubled in ajax URLs #528
* Fix - "Place order" button looking unstyled in the Twenty Twenty-Two theme #478
* Fix - PayPal options available on minicart when adding subscription to the cart from shop page without vaulting enabled #518
* Fix - Buttons not visible on products page #551
* Fix - Buttons not visible in mini-cart #553
* Fix - PayPal button missing on pay for order page #555
* Enhancement - PayPal buttons loading time #533
* Enhancement - Improve payment token checking for subscriptions #525
* Enhancement - Add Spain and Italy to messaging #497

= 1.7.0 =
* Fix - DCC orders randomly failing #503
* Fix - Multi-currency broke #481
* Fix - Address information from PayPal shortcut flow not loaded #451
* Fix - WooCommerce as mu-plugin is not detected as active #461
* Fix - Check if PayPal Payments is an available gateway before displaying it on Product/Cart pages #447
* Enhancement - Improve onboarding flow, allow no card processing #443 #508 #510
* Enhancement - Add Germany to supported ACDC countries #459
* Enhancement - Add filters to allow ACDC for countries #437
* Enhancement - Update 3D Secure #464
* Enhancement - Extend event, error logging & order notes #456
* Enhancement - Display API response errors in checkout page with user-friendly error message #457
* Enhancement - Pass address details to credit card fields #479
* Enhancement - Improve onboarding notice #465
* Enhancement - Add transaction ID to WC order and order note when refund is received #473
* Enhancement - Asset caching may cause bugs on upgrades #501
* Enhancement - Allow partial capture #483
* Enhancement - PayPal Payments doesn't set transaction fee metadata #467
* Enhancement - Show PayPal fee information in order #489

= 1.6.5 =
* Fix - Allow guest users to purchase subscription products from checkout page #422
* Fix - Transaction ID missing for renewal order #424
* Fix - Save your credit card checkbox should be removed in pay for order for subscriptions #420
* Fix - Null currency error when the Aelia currency switcher plugin is active #426
* Fix - Hide Reference Transactions check from logs #428
* Fix - Doubled plugin module URL path causing failure #438
* Fix - is_ajax deprecated #441
* Fix - Place order button from PayPal Card Processing does not get translated #290
* Fix - AMEX missing from supported cards for DCC Australia #432
* Fix - "Save your Credit Card" text not clickable to change checkbox state #430
* Fix - Improve DCC error notice when not available #435
* Enhancement - Add View Logs link #416

= 1.6.4 =
* Fix - Non admin user cannot save changes to the plugin settings #278
* Fix - Empty space in invoice prefix causes smart buttons to not load #390
* Fix - woocommerce_payment_complete action not triggered for payments completed via webhook #399
* Fix - Paying with Venmo - Change funding source on checkout page and receipt to Venmo  #394
* Fix - Internal server error on checkout when selected saved card but then switched to paypal #403
* Enhancement - Allow formatted text for the Description field #407
* Enhancement - Remove filter to prevent On-Hold emails #411

= 1.6.3 =
* Fix - Payments fail when using custom order numbers #354
* Fix - Do not display saved payments on PayPal buttons if vault option is disabled #358
* Fix - Double "Place Order" button #362
* Fix - Coupon causes TAX_TOTAL_MISMATCH #372
* Fix - Funding sources Mercado Pago and BLIK can't be disabled #383
* Fix - Customer details not available in order and name gets replaced by xxx@dcc2.paypal.com #378
* Fix - 3D Secure failing for certain credit card types with PayPal Card Processing #379
* Fix - Error messages are not cleared even when checkout is re-attempted (DCC) #366
* Add - New additions for system report status #377

= 1.6.2 =
* Fix - Order of WooCommerce checkout actions causing incompatibility with AvaTax address validation #335
* Fix - Can't checkout to certain countries with optional postcode #330
* Fix - Prevent subscription from being purchased when saving payment fails #308
* Fix - Guest users must checkout twice for subscriptions, no smart buttons loaded #342
* Fix - Failed PayPal API request causing strange error #347
* Fix - PayPal payments page empty after switching packages #350
* Fix - Could Not Validate Nonce Error #239
* Fix - Refund via PayPal dashboard does not set the WooCommerce order to "Refunded" #241
* Fix - Uncaught TypeError: round() #344
* Fix - Broken multi-level (nested) associative array values after getting submitted from checkout page #307
* Fix - Transaction id missing in some cases #328
* Fix - Payment not possible in pay for order form because of terms checkbox missing #294
* Fix - "Save your Credit Card" shouldn't be optional when paying for a subscription #368
* Fix - When paying for a subscription and vaulting fails, cart is cleared #367
* Fix - Fatal error when activating PayPal Checkout plugin #363

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
