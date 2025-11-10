# Settings Extension

Extend the settings UI from other modules.

## JavaScript

```javascript
import { registerSetting, createExtensionStore, SLOTS } from '@settings/extensions';

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

## PHP Implementation

### 1. Data Model

```php
use WooCommerce\PayPalCommerce\Settings\Data\ExtensionDataModel;

class SettingsDataModel extends ExtensionDataModel {
    
    protected const OPTION_KEY = 'woocommerce-ppcp-my-extension-store';
    
    protected function get_defaults(): array {
        return array(
            'active'    => false,
            'max_items' => 10,
        );
    }
    
    public function get_active(): bool {}
    public function get_max_items(): int {}
    public function set_active(): void {}
    public function set_max_items(): void {}
}
```

### 2. REST Endpoint

```php
use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\Settings\Endpoint\ExtensionRestEndpoint;

class SettingsRestEndpoint extends ExtensionRestEndpoint {
    
    protected $rest_base = 'js-extension-store'; // Must match JS store name
    
    public function __construct( AbstractDataModel $data_model ) {
        parent::__construct( $data_model );
    }
    
    protected function sanitize_rest_data( array $data ): ?array {
        // Return NULL to reject the request
        if ( ! isset( $data['active'] ) ) {
            return null;
        }
        
        // Return sanitized data with data types compatible with model setters
        return array(
            'active'    => (bool) $data['active'],
            'max_items' => isset( $data['maxItems'] ) ? (int) $data['maxItems'] : 10,
        );
    }
}
```

### 3. DI Container

```php
use WooCommerce\PayPalCommerce\MyModule\Data\SettingsDataModel;
use WooCommerce\PayPalCommerce\MyModule\Endpoint\SettingsRestEndpoint;

return array(
    'my-module.settings.data-model' => static function (): SettingsDataModel {
        return new SettingsDataModel();
    },
    'my-module.settings.endpoint'   => static function ( $container ): SettingsRestEndpoint {
        return new SettingsRestEndpoint(
            $container->get( 'my-module.settings.data-model' )
        );
    },
);
```

### 4. Register Endpoint

```php
add_action( 'rest_api_init', static function () use ( $container ) {
    $endpoint = $container->get( 'my-module.settings.endpoint' );
    $endpoint->register_routes();
} );
```
