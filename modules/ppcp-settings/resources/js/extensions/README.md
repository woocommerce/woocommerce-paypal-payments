# Settings Extension

Extend the settings UI from other modules.

## JavaScript

```javascript
import { registerSetting, createExtensionStore, SLOTS } from '@ppcp-settings/extensions';

const useSettings = createExtensionStore( {
    name: 'js-extension-store',
    defaults: {
        active: false,
    },
} );

const MyExtension = () => {
    const { active, setActive } = useSettings();

    return (
        <SettingsBlock title="My Extension">
            <ControlToggleButton label="Active" value={ active } onChange={ setActive } />
        </SettingsBlock>
    );
};

registerSetting(
    SLOTS.PAYPAL_SETTINGS_END,
    'extension-id', // unique value, not used anywhere else 
    MyExtension
);
```

---

## PHP

**Steps:**

1. Data model - `extends ExtensionDataModel`
2. REST endpoint - `extends ExtensionRestEndpoint`
3. Settings module - `extends ExtensionSettingsModule`
4. Create three DI services - `services.php`
5. Call the settings `init()` method

### 1. Data Model

```php
use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionDataModel;

class MyExtensionDataModel extends ExtensionDataModel {
    
    protected const NAME = 'my-extension'; // Becomes 'woocommerce-ppcp-ext-my-extension'
    
    protected function get_defaults(): array {
        return array(
            'active' => false,
        );
    }
    
    public function is_active(): bool {
        return (bool) $this->data['active'];
    }
    
    public function set_active( bool $state ): void {
        $this->data['active'] = $state;
    }
}
```

### 2. REST Endpoint

```php
use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionRestEndpoint;

class MyExtensionEndpoint extends ExtensionRestEndpoint {
    
    protected const PATH = 'my-extension-settings';
    
    protected function sanitize_rest_data( array $data ): ?array {
        // Return null to reject
        if ( ! isset( $data['active'] ) ) {
            return null;
        }
        
        return array(
            'active' => (bool) $data['active'],
        );
    }
}
```

### 3. Settings Module

```php
use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionSettingsModule;

class MyExtensionSettingsModule extends ExtensionSettingsModule {
    
    protected const ASSETS_DIR    = 'modules/ppcp-my-module/assets/';
    protected const SCRIPT_HANDLE = 'ppcp-my-extension-settings';
}
```

### 4. DI Container

```php
return array(
    'my-module.settings.model'    => static function (): MyExtensionDataModel {
        return new MyExtensionDataModel();
    },
    'my-module.settings.endpoint' => static function ( $container ): MyExtensionEndpoint {
        return new MyExtensionEndpoint(
            $container->get( 'my-module.settings.model' )
        );
    },
    'my-module.settings.module'   => static function ( $container ): MyExtensionSettingsModule {
        return new MyExtensionSettingsModule(
            $container->get( 'ppcp.path-to-plugin-folder' ),     // static dependency.
            $container->get( 'ppcp.path-to-plugin-main-file' ),  // static dependency.
            $container->get( 'my-module.settings.endpoint' )
        );
    },
);
```

### 5. Initialize

```php
$settings_module = $container->get( 'my-module.settings.module' );
$settings_module->init();
```
