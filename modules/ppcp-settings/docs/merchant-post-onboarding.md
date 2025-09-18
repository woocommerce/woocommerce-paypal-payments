# Post-Onboarding: Merchant Connected State

Once onboarding is complete, the plugin transitions from "onboarding mode" to "connected mode" where the merchant can process payments. The system gains access to PayPal merchant information and enables payment processing capabilities based on the merchant's PayPal account features.

## Connection Process Flow

```mermaid
sequenceDiagram
    participant M as Merchant
    participant PP as PayPal OAuth
    participant CL as ConnectionListener
    participant AM as AuthenticationManager
    participant GS as GeneralSettings
    participant PG as Payment Gateways

    M->>PP: Complete PayPal OAuth Flow
    PP->>CL: Return with merchant data
    Note over CL: merchantIdInPayPal, merchantId (email), accountStatus
    CL->>AM: handle_oauth_authentication()
    AM->>GS: Store connection details
    AM->>PG: Trigger gateway sync
    Note over PG: woocommerce_paypal_payments_sync_gateways
    PG->>M: Redirect to settings page
```

## Merchant Data Sources

### PayPal OAuth Response

When the merchant completes PayPal OAuth authentication, PayPal returns these key data points:

**File:** `modules/ppcp-settings/src/Handler/ConnectionListener.php`

```php
// OAuth response parameters
'merchantIdInPayPal' => 'ANONYMOUS_MERCHANT_ID',  // PayPal's internal merchant ID
'merchantId'         => 'merchant@email.com',     // Merchant's PayPal email address
'accountStatus'      => 'BUSINESS_ACCOUNT',       // Account type indicator
'ppcpToken'          => 'VALIDATION_TOKEN'        // Security token
```

#### Data Extraction Process

The `ConnectionListener` extracts and sanitizes merchant information:

1. **Merchant ID**: Anonymous PayPal merchant identifier (`merchantIdInPayPal`)
2. **Merchant Email**: PayPal account email address (`merchantId` parameter)
3. **Seller Type**: Determined from `accountStatus` parameter
   - `'BUSINESS_ACCOUNT'` → `SellerTypeEnum::BUSINESS`
   - Empty/missing → `SellerTypeEnum::PERSONAL`
   - Unknown values → `SellerTypeEnum::UNKNOWN`

### Merchant Country Determination

The merchant's operating country comes from PayPal's API after successful authentication:

**File:** `modules/ppcp-wc-gateway/src/Helper/MerchantDetails.php`

```php
/**
 * The merchant's country according to PayPal, which might be different from
 * the WooCommerce country.
 */
private string $merchant_country;

/**
 * Returns the merchant's country. This country is used by PayPal to decide
 * which features the merchant can access.
 */
public function get_merchant_country(): string {
    return $this->merchant_country;
}
```

#### Country Sources Priority

1. **PayPal API Response**: Primary source - merchant's registered country with PayPal
2. **Store Country Fallback**: WooCommerce store country setting if PayPal data unavailable
3. **Feature Determination**: PayPal merchant country determines available features

**Service Registration:**
```php
// modules/ppcp-settings/services.php
'settings.merchant-details' => function(ContainerInterface $container): MerchantDetails {
    $data = $container->get('settings.data.general');
    $merchant_country = $data->get_merchant_country();  // From PayPal API
    $woo_data = $data->get_woo_settings();

    return new MerchantDetails($merchant_country, $woo_data['country'], $eligibility_checks);
},
```

## Post-Connection System Changes

### 1. Connection State Management

**File:** `modules/ppcp-wc-gateway/src/Helper/ConnectionState.php`

The system maintains connection state that affects all plugin behavior:

```php
class ConnectionState {
    private bool $is_connected;
    private Environment $environment;

    public function is_connected(): bool {
        return $this->is_connected;
    }

    public function is_sandbox(): bool {
        return $this->environment->is_sandbox();
    }
}
```

### 2. Payment Gateway Synchronization

After connection, the system triggers payment gateway updates:

**Action Hook:** `woocommerce_paypal_payments_sync_gateways`

**File:** `modules/ppcp-settings/src/Data/OnboardingProfile.php`

```php
public function set_gateways_synced(bool $synced): void {
    $this->data['gateways_synced'] = $synced;
    if ($synced) {
        do_action('woocommerce_paypal_payments_sync_gateways');
    }
}
```

This action enables payment gateways based on:
- Merchant's PayPal account capabilities
- Country-specific feature availability
- Onboarding preferences (card payments, etc.)

### 3. Feature Availability Updates

The connected merchant's capabilities are determined by:

**File:** `modules/ppcp-settings/services.php`

```php
// Merchant capabilities from PayPal API
$features = apply_filters(
    'woocommerce_paypal_payments_rest_common_merchant_features',
    array()
);

$capabilities = array(
    'apple_pay'    => $features['apple_pay']['enabled'] ?? false,
    'google_pay'   => $features['google_pay']['enabled'] ?? false,
    'acdc'         => $features['advanced_credit_and_debit_cards']['enabled'] ?? false,
    'save_paypal'  => $features['save_paypal_and_venmo']['enabled'] ?? false,
    'apm'          => $features['alternative_payment_methods']['enabled'] ?? false,
);
```

## Persistent Data Storage

### Connection Details Storage

**File:** `modules/ppcp-settings/src/Data/GeneralSettings.php`

After successful authentication, these details are persisted:

- **Merchant ID**: PayPal's internal merchant identifier
- **Merchant Email**: PayPal account email address
- **Environment**: Production vs Sandbox determination
- **Connection Status**: Boolean flag indicating active connection
- **Account Type**: Business vs Personal seller classification

### Settings Applied from Onboarding

The onboarding preferences are applied as permanent settings:

1. **Payment Method Enablement**: Based on `accept_card_payments` choice
2. **Account Configuration**: Business vs personal account setup
3. **Feature Activation**: Subscription support, vaulting, etc.
4. **Gateway Registration**: WooCommerce payment gateway activation

## Operational Differences: Connected vs Onboarding

### During Onboarding
- Limited to configuration flags based on country/plugin detection
- No PayPal API access for merchant-specific data
- Uses estimated capabilities for flow determination
- Stores temporary preferences in `OnboardingProfile`

### After Connection
- Full PayPal API access with merchant authentication
- Real-time capability detection from PayPal account
- Access to merchant-specific features and limitations
- Persistent settings in `GeneralSettings` and gateway configuration

## API Integration Post-Connection

### Merchant Information Retrieval

Connected merchants can access PayPal's merchant APIs:

1. **Account Information**: Real-time merchant details and capabilities
2. **Transaction Processing**: Order creation, payment capture, refunds
3. **Webhook Management**: Event notifications for payment updates
4. **Feature Detection**: Dynamic capability queries

### Settings Management

The settings interface transitions from onboarding wizard to full configuration:

- **Connection Tab**: Shows connected account details
- **Payment Methods**: Enable/disable based on merchant capabilities
- **Advanced Settings**: Country and account-specific options
- **Troubleshooting**: Connection status and diagnostic tools

### Post-Onboarding Capability Detection

After successful merchant connection, the system can determine actual save payment capabilities through multiple approaches:

#### Centralized Capability Service (Recommended)

**File:** `modules/ppcp-settings/src/Service/MerchantCapabilities.php`

The `MerchantCapabilities` service provides a unified, connection-aware approach to checking merchant capabilities:

```php
class MerchantCapabilities {
    private ConnectionState $connection_state;
    private DCCProductStatus $dcc_product_status;
    private ReferenceTransactionStatus $reference_transaction_status;

    public function can_save_paypal_methods(): bool {
        if ($this->connection_state->is_connected()) {
            return $this->reference_transaction_status->reference_transaction_enabled();
        }
        return false;
    }

    public function can_save_credit_cards(): bool {
        if ($this->connection_state->is_connected()) {
            return $this->dcc_product_status->is_active();
        }
        return false;
    }
}
```

**Usage:**
```php
$merchant_capabilities = $container->get('settings.service.merchant-capabilities');

// Clean, connection-aware capability checks
$can_save_paypal = $merchant_capabilities->can_save_paypal_methods();
$can_save_cards = $merchant_capabilities->can_save_credit_cards();
```

#### Direct API Approach (Legacy)

**PayPal Eligibility Check:**
```php
// Real-time API call to PayPal
$reference_status = $container->get('api.reference-transaction-status');
$can_save_paypal = $reference_status->reference_transaction_enabled();
```

**Credit Card Eligibility Check:**
```php
// Product status check via PayPal API
$dcc_product_status = $container->get('wcgateway.helper.dcc-product-status');
$can_save_cards = $dcc_product_status->is_active();
```

#### Country-Based Fallback (Pre-Connection)

```php
// Static country-based check (used during onboarding)
$card_applies = $container->get('card-fields.helpers.save-payment-methods-applies');
$can_save_cards = $card_applies->for_country() && $card_applies->for_merchant();
```

### Merchant Feature Response

Currently connected merchants receive capability information via the `woocommerce_paypal_payments_rest_common_merchant_features` filter:

```php
$features = [
    'save_paypal_and_venmo' => [
        'enabled' => $reference_transaction_status->reference_transaction_enabled()
    ],
    'advanced_credit_and_debit_cards' => [
        'enabled' => $card_fields_applies->for_country() && $card_fields_applies->for_merchant()
    ]
];
```
> Note: `advanced_credit_and_debit_cards.enabled` could be migrated to use new `MerchantCapabilities::can_save_credit_cards` instead.

### Practical Implications

**Scenario 1**: Merchant in supported country with PayPal business account
- Credit card save methods: ✅ Available (country-based)
- PayPal save methods: ❓ Depends on PayPal account vaulting capability

**Scenario 2**: Merchant with personal PayPal account
- Credit card save methods: ✅ Available if ACDC enabled
- PayPal save methods: ❌ Not available (requires advanced vaulting)

**Scenario 3**: Merchant in country with limited PayPal features
- Credit card save methods: ✅ Available (broader country support)
- PayPal save methods: ❌ Not available (country limitations)

This dual-eligibility system ensures that merchants can offer the most appropriate save payment options based on their PayPal account capabilities and regional availability.

## Centralized Capability Management

### MerchantCapabilities Service Benefits

The `MerchantCapabilities` service addresses several issues with the mixed hardcoded/API approach:

#### 1. **Connection State Awareness**
```php
// Automatic connection checking - no manual verification needed
$can_save_paypal = $merchant_capabilities->can_save_paypal_methods();

// Instead of manual connection checks:
if ($connection_state->is_connected()) {
    $can_save_paypal = $reference_status->reference_transaction_enabled();
} else {
    $can_save_paypal = false;
}
```

#### 2. **API-First Approach**
The service prioritizes PayPal's authoritative responses over hardcoded country restrictions:

```php
// For connected merchants: Uses PayPal API exclusively
public function can_save_credit_cards(): bool {
    if ($this->connection_state->is_connected()) {
        return $this->dcc_product_status->is_active();  // PayPal API response
    }
    return false;  // Not connected = no capabilities
}
```

#### 3. **Simplified Integration**
Developers can use clean, semantic method calls instead of complex eligibility chains:

```php
// Simple and clear
if ($merchant_capabilities->can_save_paypal_methods()) {
    // Show save PayPal option
}

// Instead of complex eligibility logic
$eligible = $save_applies->for_country() &&
           $save_applies->for_merchant() &&
           $reference_status->reference_transaction_enabled() &&
           !$branded_only_mode;
```

#### 4. **Future-Proof Architecture**
The service can be extended to include additional capabilities without changing existing code:

```php
class MerchantCapabilities {
    // Existing methods...

    public function can_use_apple_pay(): bool { /* ... */ }
    public function can_use_google_pay(): bool { /* ... */ }
    public function can_use_fastlane(): bool { /* ... */ }
}
```

### Migration from Legacy Approaches

**Before (Scattered Checks):**
```php
// Multiple services and complex logic
$is_connected = $container->get('settings.flag.is-connected');
if ($is_connected) {
    $reference_status = $container->get('api.reference-transaction-status');
    $can_save_paypal = $reference_status->reference_transaction_enabled();

    $dcc_status = $container->get('wcgateway.helper.dcc-product-status');
    $can_save_cards = $dcc_status->is_active();
} else {
    $country_check = $container->get('save-payment-methods.helpers.save-payment-methods-applies');
    $can_save_paypal = $country_check->for_country();
    // ... more fallback logic
}
```

**After (Centralized Service):**
```php
// Single service, clean API
$capabilities = $container->get('settings.service.merchant-capabilities');
$can_save_paypal = $capabilities->can_save_paypal_methods();
$can_save_cards = $capabilities->can_save_credit_cards();
```

### Service Registration

**File:** `modules/ppcp-settings/services.php`

```php
'settings.service.merchant-capabilities' => static function(ContainerInterface $container): MerchantCapabilities {
    return new MerchantCapabilities(
        $container->get('wcgateway.helper.connection-state'),
        $container->get('api.reference-transaction-status'),
        $container->get('wcgateway.helper.dcc-product-status')
    );
},
```
