# Plugin Architecture Documentation

This document provides a comprehensive overview of the WooCommerce PayPal Payments plugin architecture, explaining its modular design and how the various components work together.

## Overview

The WooCommerce PayPal Payments plugin is built using a modular architecture powered by the [Syde Modularity](https://github.com/inpsyde/modularity) framework. This design provides:

- **Modular Structure**: Each feature is contained within its own module with clear boundaries
- **Dependency Injection**: PSR-11 container for service management and dependency resolution
- **Feature Flags**: Dynamic module loading based on environment variables and filters
- **Extensibility**: Well-defined extension points for customization and enhancement
- **Maintainability**: Clear separation of concerns and consistent patterns

## Core Components

### Main Plugin File

The plugin initialization begins in `woocommerce-paypal-payments.php`, which:
- Loads the Composer autoloader if needed (e.g. may be already loaded in some tests)
- Contains plugin metadata and constants definitions
- It starts the bootstrap process, in `plugins_loaded` hook.

### Bootstrap System

The bootstrap process is handled by `bootstrap.php`, which:

```php
return function (
    string $root_dir,
    array $additional_containers = array(),
    array $additional_modules = array()
): ContainerInterface {
    // Load modules from modules.php
    $modules = ( require "$root_dir/modules.php" )( $root_dir );
    
    // Apply filters for customization
    $modules = apply_filters( 'woocommerce_paypal_payments_modules', $modules );
    
    // Initialize plugin with Syde Modularity
    $properties = PluginProperties::new( "$root_dir/woocommerce-paypal-payments.php" );
    $bootstrap = Package::new( $properties );
    
    foreach ( $modules as $module ) {
        $bootstrap->addModule( $module );
    }
    
    $bootstrap->boot();
    return $bootstrap->container();
};
```

### PPCP Container

The global `PPCP` class (`src/PPCP.php`) provides access to the dependency injection container:

```php
class PPCP {
    private static $container = null;
    
    public static function container(): ContainerInterface {
        if ( ! self::$container ) {
            throw new LogicException( 'No PPCP container, probably called too early when the plugin is not initialized yet.' );
        }
        return self::$container;
    }
}
```

This allows third-party access services easily, such as in `api/order-functions.php`.

## Module System

### Module Definition

Modules are defined in `modules.php` with both core and conditional modules:

```php
$modules = array(
    new PluginModule(),
    ( require "$modules_dir/woocommerce-logging/module.php" )(),
    ( require "$modules_dir/ppcp-admin-notices/module.php" )(),
    ( require "$modules_dir/ppcp-api-client/module.php" )(),
    // ... more core modules
);
```

### Feature-Flag Controlled Modules

Conditional modules are loaded based on environment variables and filters (`modules.php`):

```php
if ( apply_filters(
    'woocommerce.feature-flags.woocommerce_paypal_payments.applepay_enabled',
    getenv( 'PCP_APPLEPAY_ENABLED' ) !== '0'
) ) {
    $modules[] = ( require "$modules_dir/ppcp-applepay/module.php" )();
}
```

This pattern allows for:
- **Environment-based control**: Use `PCP_*_ENABLED` environment variables
- **Runtime filtering**: Apply WordPress filters to override defaults
- **Graceful degradation**: Missing features don't break core functionality

### Module Structure

Each module follows a consistent directory structure:

```
modules/ppcp-example/
├── module.php           # Module factory function
├── composer.json        # PHP dependencies
├── package.json         # JavaScript dependencies
├── webpack.config.js    # Asset building configuration
├── services.php         # Service definitions
├── extensions.php       # Service extensions/modifications
├── src/                 # PHP source code
│   └── ExampleModule.php
├── resources/          # Source assets
│   ├── js/
│   └── css/
└── assets/            # Built assets
    ├── js/
    └── css/
```

### Module Interface Implementation

Most modules implement the Syde Modularity interfaces. For example in `modules/ppcp-api-client/src/ApiModule.php`:

```php
class ApiModule implements ServiceModule, FactoryModule, ExtendingModule, ExecutableModule {
    use ModuleClassNameIdTrait;
    
    public function services(): array {
        return require __DIR__ . '/../services.php';
    }
    
    public function factories(): array {
        return require __DIR__ . '/../factories.php';
    }
    
    public function extensions(): array {
        return require __DIR__ . '/../extensions.php';
    }
    
    public function run( ContainerInterface $c ): bool {
        // Module initialization logic
        return true;
    }
}
```

## Key Modules

### Core Infrastructure Modules

- **PluginModule** (`src/PluginModule.php`): Root module providing core services
- **woocommerce-logging**: Logging infrastructure integration
- **ppcp-api-client**: PayPal API integration, entities, and authentication
- **ppcp-session**: Session management for payment flows
- **ppcp-webhooks**: PayPal webhook handling

### Payment & Checkout Modules

- **ppcp-button**: PayPal Smart Payment Buttons and Advanced Credit and Debit Cards functionality
- **ppcp-blocks**: WooCommerce Blocks integration
- **ppcp-wc-gateway**: WooCommerce gateway integration
- **ppcp-axo**: PayPal Fastlane (Accelerated Checkout) implementation

### Feature Modules

- **ppcp-settings**: New React-based admin settings interface
- **ppcp-vaulting**: Saved payment methods functionality
- **ppcp-onboarding**: Merchant onboarding flow

### Alternative Payment Methods

- **ppcp-applepay/ppcp-googlepay**: Digital wallet integrations
- **ppcp-local-alternative-payment-methods**: Regional payment options

## Dependency Injection & Services

### Service Definition

Services are defined in each module's `services.php` file using factory functions:

```php
return array(
    'example.service' => static function ( ContainerInterface $container ): ExampleService {
        return new ExampleService(
            $container->get( 'dependency.service' )
        );
    },
    
    'example.config' => static function (): array {
        return array(
            'setting' => 'value',
        );
    },
);
```

### Service Extensions

The `extensions.php` files allow modules to modify or extend existing services:

```php
return array(
    'existing.service' => static function ( ContainerInterface $container, ExistingService $service ): ExistingService {
        // Modify or wrap the existing service
        return new EnhancedService( $service );
    },
);
```

### Container Access Patterns

Services can be accessed in multiple ways:

```php
// In our modules/services/extensions (also often passed to hook handlers via `use`)
$service = $container->get( 'service.id' );

// In third-party plugins etc. (if not adding a custom module via the `woocommerce_paypal_payments_modules` filter) 
$service = PPCP::container()->get( 'service.id' );

// Check for service availability
if ( $container->has( 'optional.service' ) ) {
    $service = $container->get( 'optional.service' );
}
```

## Asset Management

### Webpack Configuration

Each module with JavaScript assets includes a `webpack.config.js`:

```javascript
const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'boot': path.resolve(process.cwd(), 'resources/js', 'boot.js'),
    },
    output: {
        path: path.resolve(process.cwd(), 'assets/js'),
        filename: '[name].js',
    },
};
```

### Build Process

Assets are built using the shared configuration:

- **Individual builds**: `yarn run build:modules:ppcp-{module-name}`
- **Watch mode**: `yarn run watch:modules:ppcp-{module-name}`
- **All modules**: `yarn run build:modules` (parallel builds)

### Asset Registration

Built assets are registered through module services and enqueued conditionally:

```php
'asset.example-script' => static function( ContainerInterface $container ): Asset {
    return new Asset(
        'example-script',
        plugin_dir_url( __DIR__ ) . 'assets/js/example.js',
        array( 'wp-element' ), // dependencies
        '1.0.0'
    );
},
```

## Extension Points

### WordPress Hooks

The plugin provides numerous action and filter hooks:

```php
// Allow modification of order request data
apply_filters( 'ppcp_create_order_request_body_data', $data );

// PayPal order creation notification
do_action( 'woocommerce_paypal_payments_paypal_order_created', $order );

// API cache clearing
do_action( 'woocommerce_paypal_payments_flush_api_cache' );
```

### Module Filters

Modules can be modified via filters:

```php
// Add or remove modules
$modules = apply_filters( 'woocommerce_paypal_payments_modules', $modules );

// Feature flag overrides
apply_filters( 'woocommerce.feature-flags.woocommerce_paypal_payments.applepay_enabled', $default );
```
