/**
 * Settings UI Extension System
 *
 * Allows modules to register settings components that render in specific locations.
 */

export { SLOTS } from './slots';
export {
	registerSetting,
	unregisterSetting,
	getRegisteredSettings,
	clearRegistry,
	getRegistryState,
} from './registry';
export { useRegisteredSettings } from './useRegisteredSettings';
