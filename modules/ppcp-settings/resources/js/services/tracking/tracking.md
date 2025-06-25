# Tracking System Architecture

## Overview

The tracking system provides comprehensive analytics for user interactions across onboarding, settings, and other flows. It features source-based field filtering, multi-funnel support, and extensible adapter architecture to prevent tracking loops and enable granular event control.

It monitors WordPress data stores rather than adding code to frontend components, ensuring comprehensive coverage of all state changes regardless of their source (user actions, API responses, system updates) while maintaining clean separation of concerns.

## File Organization

```
src/
├── services/
│   └── tracking/
│       ├── registry.js                    # Central funnel registration
│       ├── subscription-manager.js        # Store subscription management
│       ├── utils/
│       │   ├── field-config-helpers.js   # Field config helpers & utilities
│       │   └── utils.js                  # Core tracking utilities
│       ├── services/
│       │   └── funnel-tracking.js        # Funnel tracking service
│       ├── adapters/                     # Tracking destination adapters
│       │   ├── woocommerce-tracks.js     # WooCommerce Tracks integration
│       │   └── console-logger.js         # Console output
│       ├── funnels/                      # Funnel-specific configurations
│       │   └── onboarding.js             # Onboarding funnel config & translations
│       ├── index.js                      # Main exports
│       └── init.js                       # Initialization system
├── data/                                 # Redux stores
│   ├── tracking/                         # Dedicated tracking store
│   │   ├── actions.js                    # Field source tracking actions
│   │   ├── reducer.js                    # Field source state management
│   │   ├── selectors.js                  # Field source data access
│   │   └── index.js                      # Store initialization
│   ├── onboarding/                       # Clean business logic store
│   │   ├── actions.js                    # Pure business actions
│   │   ├── reducer.js                    # Clean business logic
│   │   ├── selectors.js                  # Business data access
│   │   └── hooks.js                      # Enhanced hooks with tracking
│   ├── common/                           # Clean business logic store
│   │   ├── actions.js                    # Pure business actions
│   │   ├── reducer.js                    # Clean business logic
│   │   ├── selectors.js                  # Business data access
│   │   └── hooks.js                      # Enhanced hooks with tracking
│   └── utils.js                          # Enhanced createHooksForStore
└── components/                           # Enhanced to pass tracking sources
    └── **/*.js                          # Form components updated with source attribution
```

## 1. Registry System (`registry.js`)

Manages funnel registration and coordinates multiple tracking concerns without conflicts.

### Store-to-Funnel Mapping

```javascript
// Registry maintains mapping of stores to multiple funnels
const trackingRegistry = {
 funnels: {},
 storeToFunnel: {}, // Store name -> array of funnel IDs
 instances: {},
};

// Example mapping:
{
 'wc/paypal/onboarding': ['ppcp_onboarding', 'settings_funnel'],
 'wc/paypal/common': ['ppcp_onboarding', 'other_funnel'],
}
```

### Usage

```javascript
import { registerFunnel } from '../services/tracking/registry';

registerFunnel('ppcp_onboarding', onboardingConfig);
```

## 2. Subscription Manager (`subscription-manager.js`)

Creates **unified subscriptions** to WordPress data stores and routes changes to **multiple relevant funnels**.

### Single Subscription, Multiple Funnels

```javascript
class SubscriptionManager {
 constructor() {
  this.storeSubscriptions = {};     // ONE subscription per store
  this.storeRegistrations = {};     // MULTIPLE funnel registrations per store
 }

 ensureStoreSubscription(storeName) {
  if (this.storeSubscriptions[storeName]) {
   return; // Skip if subscription already exists
  }

  // Create unified subscription for all funnels tracking this store
  const unsubscribe = wp.data.subscribe(() => {
   this.handleStoreChange(storeName);
  });
 }
}
```

### Benefits

- **One subscription per store** regardless of funnel count
- **Independent funnel logic** - each has its own rules and conditions
- **Isolated state** - each funnel tracks its own previous values

## 3. Tracking Store (`data/tracking/`)

Separate Redux store handles all field source information.

### State Structure

```javascript
// { storeName: { fieldName: { source, timestamp } } }
{
 'wc/paypal/onboarding': {
 'step': { source: 'user', timestamp: 1638360000000 },
 'isCasualSeller': { source: 'user', timestamp: 1638360000000 }
},
 'wc/paypal/common': {
 'useSandbox': { source: 'system', timestamp: 1638360000000 }
}
}
```

## 4. Universal Hook System (`data/utils.js`)

`createHooksForStore` makes **any Redux store** tracking-compatible.

```javascript
// Works with ANY store
const { usePersistent, useTransient } = createHooksForStore('wc/paypal/any-store');

// In components
const [ field, setField ] = usePersistent('fieldName');
setField(newValue, 'user'); // Automatically tracked if configured
```

## 5. Source-Based Field Filtering

Field-level rules define which change sources trigger tracking events.

```javascript
// Configuration
fieldRules: {
 step: { allowedSources: ['user', 'system'] },        // Track all changes
 isCasualSeller: { allowedSources: ['user'] },        // Only user changes
}

// Usage
setIsCasualSeller(true, 'user');  // Tracked
setIsCasualSeller(false);         // Filtered out (no source)
```

**Source Types:**

- `'user'` - Direct user interactions
- `'system'` - System-initiated changes

## 6. Funnel Configuration

Uses `FunnelConfigBuilder` pattern:

```javascript
// src/services/tracking/funnels/onboarding.js
export const config = FunnelConfigBuilder.createBasicFunnel(FUNNEL_ID, {
 debug: false,
 adapters: ['woocommerce-tracks'],
 eventPrefix: 'ppcp_onboarding',
 trackingCondition: {
  store: 'wc/paypal/common',
  selector: 'merchant',
  field: 'isConnected',
  expectedValue: false
 }
})
 .addEvents(EVENTS)
 .addTranslations(TRANSLATIONS)
 .addStore('wc/paypal/onboarding', [
  createFieldTrackingConfig('step', 'persistent', {
   rules: { allowedSources: ['user', 'system'] }
  })
 ])
 .build();
```

## 7. Initialization (`init.js`)

Required before store registration:

```javascript
import { registerFunnel } from './registry';

export function initializeTrackingFunnels() {
 if (initialized) return;

 registerFunnel(ONBOARDING_FUNNEL_ID, onboardingConfig);
 initialized = true;
}

// Auto-initialize
initializeTrackingFunnels();
```

## 8. Store Registration

Stores register with funnels in their index files:

```javascript
// src/data/onboarding/index.js
import { addStoreToFunnel } from '../../services/tracking';

export const initStore = () => {
 const store = createReduxStore(STORE_NAME, { reducer, actions, selectors });
 register(store);

 addStoreToFunnel(STORE_NAME, ONBOARDING_FUNNEL_ID);

 return Boolean(wp.data.select(STORE_NAME));
};
```

## Adding Tracking to New Stores

### 1. Create Clean Business Store

```javascript
// actions.js
export const setPersistent = (prop, value) => ({
 type: ACTION_TYPES.SET_PERSISTENT,
 payload: { [prop]: value },
});

// reducer.js
const reducer = createReducer(defaultTransient, defaultPersistent, {
 [ACTION_TYPES.SET_PERSISTENT]: (state, payload) => changePersistent(state, payload),
});
```

### 2. Create Tracking-Enabled Hooks

```javascript
// hooks.js
import { createHooksForStore } from '../utils';
export const { usePersistent, useTransient } = createHooksForStore('wc/paypal/your-store');
```

### 3. Register Store

```javascript
// index.js
addStoreToFunnel(STORE_NAME, 'your-funnel-id');
```

### 4. Configure Funnel

```javascript
// funnels/your-funnel.js
export const config = FunnelConfigBuilder.createBasicFunnel('your-funnel', {
 debug: false,
 adapters: ['console'],
})
 .addStore('wc/paypal/your-store', [
  createFieldTrackingConfig('yourField', 'persistent', {
   rules: { allowedSources: ['user'] }
  })
 ])
 .addTranslations({
  yourField: (oldValue, newValue, metadata, trackingService) => {
   trackingService.sendToAdapters('your_event_name', {
    new_value: newValue,
    old_value: oldValue
   });
  }
 })
 .build();
```

## Multi-Funnel Example

Multiple funnels tracking the same store:

```javascript
// Both register interest in same store
addStoreToFunnel('wc/paypal/onboarding', 'ppcp_onboarding');
addStoreToFunnel('wc/paypal/onboarding', 'settings_funnel');

// Results in ONE subscription, TWO registrations with different rules:
storeRegistrations = {
 'wc/paypal/onboarding': [
  {
   funnelId: 'ppcp_onboarding',
   fieldRules: { step: {allowedSources: ['user', 'system']} },
   trackingCondition: { field: 'isConnected', expectedValue: false },
   previousValues: {} // Separate per funnel
  },
  {
   funnelId: 'settings_funnel',
   fieldRules: { step: {allowedSources: ['user']} },
   trackingCondition: { field: 'isConnected', expectedValue: true },
   previousValues: {} // Separate per funnel
  }
 ]
}
```

## Debugging

### Enable Debug Mode

```javascript
export const config = FunnelConfigBuilder.createBasicFunnel('funnel', {
 debug: true,
});
```

### Inspect Tracking Store

```javascript
const trackingStore = wp.data.select('wc/paypal/tracking');
console.log('All sources:', trackingStore.getAllFieldSources());
console.log('Store sources:', trackingStore.getStoreFieldSources('wc/paypal/onboarding'));
```

### Check Registry Status

```javascript
import { getTrackingStatus, getMultiFunnelStores } from '../services/tracking';
console.log('Status:', getTrackingStatus());
console.log('Multi-funnel stores:', getMultiFunnelStores());
```

## Event Schema

Events follow pattern: `ppcp_{funnel}_{action}_{object}`

Examples:

- `ppcp_onboarding_account_type_select`
- `ppcp_onboarding_step_forward`
- `ppcp_settings_payment_method_toggle`
