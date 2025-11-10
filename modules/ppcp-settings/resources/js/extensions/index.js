/**
 * Settings UI Extension System
 *
 * Allows modules to register settings components that render in specific locations.
 */

export { SLOTS } from './slots';
export { registerSetting, unregisterSetting } from './registry';
export { useRegisteredSettings, useExtensionStores } from './hooks';
export { STORE_NAME } from './store';
export { createExtensionStore } from './createExtensionStore';

// Initialize store on import
import './store';
