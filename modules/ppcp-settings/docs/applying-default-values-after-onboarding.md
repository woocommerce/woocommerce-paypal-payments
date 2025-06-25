# Applying Default Configuration After Onboarding

The `OnboardingProfile` has a property named `setup_done`, which indicated whether the default
configuration was set up.

### `OnboardingProfile::is_setup_done()`

This flag indicated, whether the default plugin configuration was applied or not.
It's set to true after the merchant's authentication attempt was successful, and settings were
adjusted.

The only way to reset this flag, is to enable the "**Start Over**" toggle and disconnecting the
merchant:
https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=settings#disconnect-merchant

### `class SettingsDataManager`

The `SettingsDataManager` service is responsible for applying all defaults options at the end of the
onboarding process.

### `SettingsDataManager::set_defaults_for_new_merchant()`

This method expects a DTO argument (`ConfigurationFlagsDTO`) that provides relevant details about
the merchant and onboarding choices.

It verifies, if default settings were already applied (by checking the
`OnboardingProfile::is_setup_done()` state). If not done yet, the DTO object is inspected to
initialize the plugin's configuration, before marking the `setup_done` flag as completed.

## Default Settings Matrix

### Decision Flags

- **Country**: The merchant country.
	- According to PayPal settings, not the WooCommerce country
	- Test case: Set Woo country to Germany and sign in with a US merchant account; this should
	  trigger the "Country: US" branches below.
- **Seller Type**: Business or Casual.
	- According to PayPal, not the onboarding choice
	- Test case: Choose "Personal" during onboarding but log in with a business account; this should
	  trigger the "Account: Business" branches below.
- **Subscriptions**: An onboarding choice on the "Products" screen.
- **Cards**: An onboarding choice, on the "Checkout Options" screen.
	- Refers to the first option on the checkout options screen ("Custom Card Fields", etc.)

### Payment Methods

By default, all payment methods are turned off after onboarding, unless the conditions specified in
the following table are met.

| Payment Method | Country | Seller Type | Subscriptions | Cards | Notes                         |
|----------------|---------|-------------|---------------|-------|-------------------------------|
| Venmo          | US      | *any*       | *any*         | *any* | Always                        |
| Pay Later      | US      | *any*       | *any*         | *any* | Always                        |
| ACDC           | US      | Business    | *any*         | ✅     | Greyed out for Casual Sellers |
| BCDC           | US      | *any*       | *any*         | ✅     |                               |
| Apple Pay      | US      | Business    | *any*         | ✅     | Based on feature eligibility  |
| Google Pay     | US      | Business    | *any*         | ✅     | Based on feature eligibility  |
| All APMs       | US      | Business    | *any*         | ✅     | Based on feature eligibility  |

### Settings

| Feature                     | Country | Seller-Type | Subscriptions | Cards | Notes                      |
|-----------------------------|---------|-------------|---------------|-------|----------------------------|
| Pay Now Experience          | US      | _any_       | _any_         | _any_ |                            |
| Save PayPal and Venmo       | US      | Business    | ✅             | _any_ |                            |
| Save Credit and Debit Cards | US      | Business    | ✅             | ✅     | Requires ACDC eligibility* |

- `*` If merchant has no ACDC eligibility, the setting should be disabled (not toggleable).

### Styling

All US merchants use the same settings, regardless of onboarding choices.

| Button Location  | Enabled | Displayed Payment Methods                       |
|------------------|---------|-------------------------------------------------|
| Cart             | ✅       | PayPal, Venmo, Pay Later, Google Pay, Apple Pay |
| Classic Checkout | ✅       | PayPal, Venmo, Pay Later, Google Pay, Apple Pay |
| Express Checkout | ✅       | PayPal, Venmo, Pay Later, Google Pay, Apple Pay |
| Mini Cart        | ✅       | PayPal, Venmo, Pay Later, Google Pay, Apple Pay |
| Product Page     | ✅       | PayPal, Venmo, Pay Later                        |

### Pay Later Messaging

All US merchants use the same settings, regardless of onboarding choices.

| Location          | Enabled |
|-------------------|---------|
| Product           | ✅       |
| Cart              | ✅       |
| Checkout          | ✅       |
| Home              | ❌       |
| Shop              | ❌       |
| WooCommerce Block | ❌       |
