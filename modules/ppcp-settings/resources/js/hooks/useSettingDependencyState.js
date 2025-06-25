/**
 * Custom hook to handle setting-based payment method dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Check setting dependencies for methods
 *
 * @param {Array} methods - Array of methods to check
 * @return {Object} Setting dependency states mapped by method ID
 */
const useSettingDependencyState = ( methods ) => {
	const dependencyState = useSelect(
		( select ) => {
			const settingsStore = select( 'wc/paypal/settings' );

			if ( ! settingsStore || ! methods?.length ) {
				return null;
			}

			// Get settings data
			const persistentData = settingsStore.persistentData();
			const result = {};

			// Process each method
			methods.forEach( ( method ) => {
				if ( ! method?.id || ! method.depends_on_settings ) {
					return;
				}

				// Handle the settings object structure
				if ( method.depends_on_settings.settings ) {
					const settingsObj = method.depends_on_settings.settings;

					for ( const [ settingId, settingData ] of Object.entries(
						settingsObj
					) ) {
						const requiredId = settingData.id;
						const requiredValue = settingData.value;

						const actualValue = persistentData[ requiredId ];

						// Check if dependency is satisfied
						if ( actualValue !== requiredValue ) {
							result[ method.id ] = {
								isDisabled: true,
								settingId: requiredId,
								requiredValue,
							};
							break; // Stop checking once we find a failed dependency
						}
					}
				}
			} );

			return result;
		},
		[ methods ]
	);

	return dependencyState;
};

export default useSettingDependencyState;
