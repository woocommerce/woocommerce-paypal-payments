/**
 * Settings UI Extension System
 *
 * Allows modules to register settings components that render in specific locations.
 */

export { SLOTS } from './slots';
export { registerSetting, unregisterSetting } from './registry';
export { useRegisteredSettings } from './useRegisteredSettings';
export { STORE_NAME } from './store';

// Initialize store on import
import './store';
