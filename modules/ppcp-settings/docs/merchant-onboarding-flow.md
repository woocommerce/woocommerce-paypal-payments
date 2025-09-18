# Merchant Onboarding Flow

The onboarding flow is a multi-step wizard that guides merchants through setting up their PayPal integration. The flow is dynamic and adapts based on merchant preferences, store configuration, and country-specific capabilities determined by configuration flags.

## Core Architecture

### Configuration Flags Value Determination

The onboarding configuration flags are determined during the `OnboardingProfile` instantiation in the dependency injection container. Each flag represents a specific capability or business rule that affects the onboarding flow.

**File:** `modules/ppcp-settings/services.php`

```php
'settings.data.onboarding' => static function (ContainerInterface $container): OnboardingProfile {
    $can_use_casual_selling = $container->get('settings.casual-selling.eligible');
    $can_use_vaulting       = $container->has('save-payment-methods.eligible') && $container->get('save-payment-methods.eligible');
    $can_use_card_payments  = $container->has('card-fields.eligible') && $container->get('card-fields.eligible');
    $can_use_subscriptions  = $container->has('wc-subscriptions.helper') && $container->get('wc-subscriptions.helper')->plugin_is_active();
    $should_skip_payment_methods = class_exists('\WC_Payments');
    $can_use_fastlane = $container->get('axo.eligible');
    $can_use_pay_later = $container->get('button.helper.messages-apply');

    return new OnboardingProfile(/* ...flags... */);
},
```

#### Flag Value Sources

**`can_use_casual_selling`** - Personal account support
- **Source:** `settings.casual-selling.eligible` service (`services.php`)
- **Logic:** Checks if store's country is in supported countries list
- **Countries:** Countries including US, CA, AU, GB, EU countries, etc. (`services.php`)
- **Determination:** `in_array($country, $eligible_countries, true)`

**`can_use_vaulting`** - Saved payment methods capability
- **Source:** `save-payment-methods.eligible` service from SavePaymentMethods module
- **File:** `modules/ppcp-save-payment-methods/services.php`
- **Logic:** `SavePaymentMethodsApplies::for_country() && SavePaymentMethodsApplies::for_merchant()`
- **Countries:** Countries including major markets (AU, AT, BE, CA, DE, FR, etc.)
- **Merchant Check:** PayPal account must support vaulting capabilities

**`can_use_card_payments`** - Credit card processing via PayPal
- **Source:** `card-fields.eligible` service from CardFields module
- **File:** `modules/ppcp-card-fields/services.php`
- **Logic:** `CardFieldsApplies::for_country() && CardFieldsApplies::for_merchant()`
- **Countries:** Countries with credit card processing support
- **Merchant Check:** PayPal account must have ACDC (Advanced Credit and Debit Cards) capability

**`can_use_subscriptions`** - WooCommerce Subscriptions integration
- **Source:** `wc-subscriptions.helper` service availability check
- **Logic:** Checks if WooCommerce Subscriptions plugin is active
- **Determination:** `$container->get('wc-subscriptions.helper')->plugin_is_active()`

**`should_skip_payment_methods`** - Skip payment methods selection screen
- **Source:** Direct class existence check
- **Logic:** `class_exists('\WC_Payments')`
- **Purpose:** Skip payment methods screen when WooCommerce Payments plugin is active (avoid conflicts)

**`can_use_fastlane`** - Fastlane/AXO checkout capability
- **Source:** `axo.eligible` service from AXO module
- **Dependencies:** Requires ACDC capability and specific country/merchant eligibility
- **Purpose:** Determines if Advanced Checkout Options (one-click checkout) can be offered

**`can_use_pay_later`** - Pay Later messaging availability
- **Source:** `button.helper.messages-apply` service from Button module
- **File:** `modules/ppcp-button/services.php` (MessagesApply class)
- **Logic:** `MessagesApply::for_country()` - checks country against PayPal's Pay Later supported regions
- **Countries:** Pay Later messaging supported in US, AU, DE, FR, GB, IT, ES and other select markets

#### Country-Based Eligibility Services

Multiple modules implement country-based eligibility patterns:

```php
// Common pattern across modules
'module.helpers.applies' => function(ContainerInterface $container): ModuleApplies {
    return new ModuleApplies(
        $container->get('module.supported-countries'),  // Country whitelist
        $container->get('api.shop.country')            // Current store country
    );
},

// Eligibility check
'module.eligible' => function(ContainerInterface $container): bool {
    $applies = $container->get('module.helpers.applies');
    return $applies->for_country() && $applies->for_merchant();
},
```

#### Casual Selling Country List

The casual selling feature (personal PayPal accounts) supported countries are defined in: `modules/ppcp-settings/services.php`

### API Endpoint: `wc/v3/wc_paypal/onboarding`

The central REST API endpoint manages all onboarding state and configuration:

**File:** `modules/ppcp-settings/src/Endpoint/OnboardingRestEndpoint.php`

#### Endpoints:
- **GET** `/wp-json/wc/v3/wc_paypal/onboarding` - Retrieves current onboarding state and flags
- **POST** `/wp-json/wc/v3/wc_paypal/onboarding` - Updates onboarding progress and settings

#### Key Data Structures:

```php
// Onboarding state fields (OnboardingProfile)
'completed'            => bool    // Onboarding process completion status
'step'                 => int     // Current step index (0-based)
'is_casual_seller'     => bool    // Personal vs business account selection
'accept_card_payments' => bool    // Whether to enable card payment methods
'products'             => array   // Selected product types ['virtual', 'physical', 'subscriptions']
'gateways_synced'      => bool    // Payment gateway sync status
'gateways_refreshed'   => bool    // Payment gateway refresh status

// Configuration flags (read-only, determined by server)
'can_use_casual_selling'      => bool  // Country supports personal accounts
'can_use_vaulting'            => bool  // Vaulting/saved payment methods available
'can_use_card_payments'       => bool  // Credit card processing available
'can_use_subscriptions'       => bool  // WooCommerce Subscriptions detected
'should_skip_payment_methods' => bool  // Skip payment methods selection screen
'can_use_fastlane'            => bool  // Fastlane checkout available
'can_use_pay_later'           => bool  // Pay Later options available
```

### Data Layer - Redux Store

**Store Name:** `wc/paypal/onboarding`
**Files:**
- `modules/ppcp-settings/resources/js/data/onboarding/constants.js` - Store configuration
- `modules/ppcp-settings/resources/js/data/onboarding/hooks.js` - React hooks for data access

The frontend uses WordPress Data API (Redux-based) to manage state:

```javascript
// Key hooks for accessing onboarding data
OnboardingHooks.useSteps()         // Current step, completion status, flags
OnboardingHooks.useBusiness()      // Casual seller selection
OnboardingHooks.useProducts()      // Product types selection
OnboardingHooks.useOptionalPaymentMethods()  // Payment method preferences
OnboardingHooks.useFlags()         // Configuration flags from server
```

## Step Flow Control

### Step Definition and Filtering

**File:** `modules/ppcp-settings/resources/js/Components/Screens/Onboarding/Steps/index.js`

The onboarding consists of up to 5 steps that are dynamically filtered based on configuration flags:

```javascript
const ALL_STEPS = [
    { id: 'welcome', ... },      // Always shown
    { id: 'business', ... },     // Shown if canUseCasualSelling = true
    { id: 'products', ... },     // Always shown
    { id: 'methods', ... },      // Conditionally shown (see below)
    { id: 'complete', ... }      // Always shown
];
```

### Dynamic Step Filtering Logic

Steps are filtered using the `getSteps(flags)` function based on:

1. **Business Step Filtering:**
   ```javascript
   // Only show business step if casual selling is available in merchant's country
   flags.canUseCasualSelling || step.id !== 'business'
   ```

2. **Payment Methods Step Filtering:**
   ```javascript
   // Skip payment methods screen if:
   const isBrandedBCDC = ownBrandOnly && !flags.canUseCardPayments;
   const shouldSkip = flags.shouldSkipPaymentMethods ||
                     isCasualSeller ||
                     isBrandedBCDC;
   ```

   **Conditions for skipping payment methods step:**
   - `flags.shouldSkipPaymentMethods = true` (server-side business logic)
   - Merchant selected casual seller account
   - Store uses PayPal-only branding AND card payments unavailable

## Component Visibility Control

### Flag-Based Component Rendering

Components throughout the onboarding flow check flags to determine visibility:

**Example from Step Components:**
```javascript
// Business step - only renders if casual selling available
const { flags } = OnboardingHooks.useFlags();
if (!flags.canUseCasualSelling) {
    return null; // Component not rendered
}
```

### Screen Navigation Logic

**File:** `modules/ppcp-settings/resources/js/Components/Screens/Onboarding/Steps/index.js`

```javascript
export const getCurrentStep = (requestedStep, steps) => {
    // Ensures requested step exists in filtered steps array
    const safeCurrentStep = isValidStep(requestedStep) ? requestedStep : 0;
    return steps[safeCurrentStep];
};
```

Navigation between steps validates:
- Step index is within bounds of filtered steps
- `canProceed()` function returns true for current step
- Required data is collected before advancing

### Initial State Hydration and State Persistence

When the onboarding screen loads:

1. **Frontend** makes GET request to `/wc/v3/wc_paypal/onboarding`
2. **Backend** returns current state + flags via `OnboardingRestEndpoint::get_details()`
3. **Frontend** initializes Redux store with server data
4. **Components** render based on flags and current step

User interactions trigger:

1. **Frontend** updates local Redux state
2. **Frontend** makes POST request to `/wc/v3/wc_paypal/onboarding` with changes
3. **Backend** validates and persists via `OnboardingProfile::save()`
4. **Backend** returns updated state (including any derived flag changes)

## Key Integration Points

### Gateway Synchronization

**Files:**
- `modules/ppcp-settings/src/Data/OnboardingProfile.php`
- WordPress action: `woocommerce_paypal_payments_sync_gateways`

```php
public function set_gateways_synced(bool $synced): void {
    $this->data['gateways_synced'] = $synced;
    if ($synced) {
        do_action('woocommerce_paypal_payments_sync_gateways');
    }
}
```

### Connection Flow Integration

The onboarding integrates with PayPal's OAuth connection flow:
- Final step provides connection buttons/forms
- Connection success updates onboarding completion status
- Failed connections allow retry without losing progress

### Settings Application

Upon onboarding completion, the collected preferences are applied:
- Payment method enablement based on `accept_card_payments`
- Account type configuration based on `is_casual_seller`
- Product-specific features based on `products` array
