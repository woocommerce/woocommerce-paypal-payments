# Store template

This template contains all files for a Redux store.

## New Store: Redux integration

1. Copy this folder, give it a correct name.
2. Check each file for `<UNKNOWN>` placeholders and `TODO` remarks.
3. Edit the main store-index file and add the relevant store integration there.
4. Check the debug-module, and add relevant debug code.
   - Register the store in the `reset()` method.

---

Main store-index:
`modules/ppcp-settings/resources/js/data/index.js`

Sample store integration:
```js
import * as YourStore from './yourStore';
// ...
YourStore.initStore();
// ...
export const YourStoreHooks = YourStore.hooks;
// ...
export const YourStoreName = YourStore.STORE_NAME;
// ...
addDebugTools( window.ppcpSettings, [ ..., YourStoreName ] );
```

---

### New Store: PHP integration

1. Create the **REST endpoint** for hydrating and persisting data.
   - `modules/ppcp-settings/src/Endpoint/YourStoreRestEndpoint.php`
   - Extend from base class `RestEndpoint`
2. Create the **data model** class to manage the DB interaction.
   - `modules/ppcp-settings/src/Data/YourStoreSettings.php`
   - Extend from base class `AbstractDataModel`
3. Create relevant **DI services** for both files.
   - `modules/ppcp-settings/services.php`
4. Register the REST endpoint in the **service module**.
   - `modules/ppcp-settings/src/SettingsModule.php`
   - Find the action `rest_api_init`
