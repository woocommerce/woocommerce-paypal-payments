import { useMemo } from '@wordpress/element';

/**
 * Custom hook returning the allowed shipping locations based on configuration.
 *
 * @param {Object}          axoConfig                            - The AXO configuration object.
 * @param {Array|undefined} axoConfig.enabled_shipping_locations - The list of enabled shipping locations.
 * @return {Array} The final list of allowed shipping locations.
 */
const useAllowedLocations = ( axoConfig ) => {
	return useMemo( () => {
		const enabledShippingLocations =
			axoConfig.enabled_shipping_locations || [];

		return Array.isArray( enabledShippingLocations )
			? enabledShippingLocations
			: [];
	}, [ axoConfig.enabled_shipping_locations ] );
};

export default useAllowedLocations;
