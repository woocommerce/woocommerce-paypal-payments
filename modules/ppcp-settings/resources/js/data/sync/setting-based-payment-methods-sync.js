import { subscribe, select } from '@wordpress/data';

// Store names
const PAYMENT_STORE = 'wc/paypal/payment';
const SETTINGS_STORE = 'wc/paypal/settings';

// Track original states of methods affected by settings
const settingDependentStates = {};

/**
 * Initialize setting dependency synchronization
 */
export const initSettingBasedPaymentMethodsSync = () => {
	let previousSettingsState = null;
	let isProcessing = false;

	const unsubscribe = subscribe( () => {
		if ( isProcessing ) {
			return;
		}

		isProcessing = true;

		try {
			// Get both settings and payment stores
			const settingsHooks = select( SETTINGS_STORE );
			const paymentHooks = select( PAYMENT_STORE );

			if ( ! settingsHooks || ! paymentHooks ) {
				isProcessing = false;
				return;
			}

			const settings = settingsHooks.persistentData();
			const methods = paymentHooks.persistentData();

			if ( ! settings || ! methods ) {
				isProcessing = false;
				return;
			}

			if ( ! previousSettingsState ) {
				previousSettingsState = { ...settings };
				isProcessing = false;
				return;
			}

			// Find which settings changed
			const changedSettings = Object.keys( settings ).filter(
				( key ) =>
					previousSettingsState[ key ] !== undefined &&
					settings[ key ] !== previousSettingsState[ key ]
			);

			if ( changedSettings.length > 0 ) {
				// Process affected payment methods for each changed setting
				for ( const methodId in methods ) {
					if ( methodId === '__meta' || ! methods[ methodId ] ) {
						continue;
					}

					const method = methods[ methodId ];

					// Skip methods without setting dependencies
					if ( ! method.depends_on_settings?.settings ) {
						continue;
					}

					const { settings: dependencySettings } =
						method.depends_on_settings;

					// Check if any of the changed settings affects this method
					const relevantSettings = Object.values(
						dependencySettings
					).filter( ( setting ) =>
						changedSettings.includes( setting.id )
					);

					if ( relevantSettings.length > 0 ) {
						// Determine if method should be disabled based on new setting values
						const shouldBeDisabled = relevantSettings.some(
							( setting ) =>
								settings[ setting.id ] !== setting.value
						);

						if ( shouldBeDisabled ) {
							// Store original state before disabling
							if ( ! ( methodId in settingDependentStates ) ) {
								settingDependentStates[ methodId ] =
									method.enabled;
							}

							// Disable the method
							methods[ methodId ].enabled = false;
							methods[ methodId ].isDisabled = true;
						} else {
							// Check if all setting dependencies are now satisfied
							const allSettingsSatisfied = Object.values(
								dependencySettings
							).every(
								( setting ) =>
									settings[ setting.id ] === setting.value
							);

							// Also check payment method dependencies
							const paymentDependenciesSatisfied =
								checkPaymentDependenciesSatisfied(
									methodId,
									methods
								);

							// If all dependencies are satisfied, restore the original state
							if (
								allSettingsSatisfied &&
								paymentDependenciesSatisfied &&
								methodId in settingDependentStates
							) {
								methods[ methodId ].enabled =
									settingDependentStates[ methodId ];
								methods[ methodId ].isDisabled = false;
								delete settingDependentStates[ methodId ];
							}
						}
					}
				}
			}

			previousSettingsState = { ...settings };
		} catch ( error ) {
			// Silent error handling
		} finally {
			isProcessing = false;
		}
	} );

	return unsubscribe;
};

/**
 * Check if all payment method dependencies are satisfied for a method
 *
 * @param {string} methodId - ID of the method to check
 * @param {Object} methods  - All payment methods
 * @return {boolean} True if all dependencies are satisfied
 */
const checkPaymentDependenciesSatisfied = ( methodId, methods ) => {
	const method = methods[ methodId ];
	if ( ! method || ! method.depends_on_payment_methods ) {
		return true;
	}

	return ! method.depends_on_payment_methods.some( ( parentId ) => {
		const parent = methods[ parentId ];
		return ! parent || parent.enabled === false;
	} );
};

export default initSettingBasedPaymentMethodsSync;
