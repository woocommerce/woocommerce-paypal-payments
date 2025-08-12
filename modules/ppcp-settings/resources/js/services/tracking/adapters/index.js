/**
 * Adapters Index: Centralized exports for all tracking adapters.
 *
 * Re-exports all available tracking adapters and provides adapter metadata.
 * Serves as the main entry point for importing adapters into the tracking system.
 *
 * @file
 */

export { WooCommerceTracksAdapter } from './woocommerce-tracks';
export { ConsoleLoggerAdapter } from './console-logger';

/**
 * Get info about available adapters.
 */
export const getAvailableAdapters = () => {
	return [
		{
			type: 'woocommerce',
			name: 'WooCommerce Tracks',
			description: 'Send events to WooCommerce Tracks API',
			requiredOptions: [ 'eventPrefix' ],
		},
		{
			type: 'console',
			name: 'Console Logger',
			description: 'Log events to browser console',
			requiredOptions: [],
		},
	];
};
