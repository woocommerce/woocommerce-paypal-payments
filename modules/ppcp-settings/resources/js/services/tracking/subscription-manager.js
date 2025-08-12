/**
 * Subscription Manager: Coordinates multiple funnels tracking the same store.
 *
 * Manages unified subscriptions to prevent conflicts when multiple tracking funnels
 * monitor the same WordPress data store. Handles condition evaluation and state coordination.
 *
 * @file
 */

import { getFieldValue } from './utils';

/**
 * Manages unified subscriptions for stores that are tracked by multiple funnels.
 */
export class SubscriptionManager {
	constructor() {
		// Store name -> subscription info.
		this.storeSubscriptions = {};

		// Store name -> array of funnel registrations.
		this.storeRegistrations = {};

		this.debugMode = false;
	}

	/**
	 * Register a funnel's interest in tracking a store.
	 * @param {string}      storeName         - Name of the store.
	 * @param {string}      funnelId          - ID of the funnel.
	 * @param {Object}      trackingService   - Funnel's tracking service.
	 * @param {Object}      fieldRules        - Field rules for this funnel.
	 * @param {Array}       fieldConfigs      - Field configurations for this funnel.
	 * @param {boolean}     debugMode         - Debug mode for this funnel.
	 * @param {Object|null} trackingCondition - Tracking condition for this funnel.
	 * @param {Object}      stepInfo          - Step information for this funnel.
	 */
	registerFunnelForStore(
		storeName,
		funnelId,
		trackingService,
		fieldRules,
		fieldConfigs,
		debugMode,
		trackingCondition = null,
		stepInfo = {}
	) {
		// Initialize store registrations if needed.
		if ( ! this.storeRegistrations[ storeName ] ) {
			this.storeRegistrations[ storeName ] = [];
		}

		// Check if already registered.
		const existingIndex = this.storeRegistrations[ storeName ].findIndex(
			( reg ) => reg.funnelId === funnelId
		);

		const registration = {
			funnelId,
			trackingService,
			fieldRules,
			fieldConfigs,
			debugMode,
			trackingCondition,
			stepInfo,
			isActive: false,
			previousValues: {},
			hasTrackedPageLoad: false,
			initializationAttempts: 0,
			lastConditionResult: null,
			conditionCheckCount: 0,
		};

		if ( existingIndex >= 0 ) {
			// Update existing registration.
			this.storeRegistrations[ storeName ][ existingIndex ] =
				registration;
		} else {
			// Add new registration.
			this.storeRegistrations[ storeName ].push( registration );
		}

		// Enable debug if any funnel has it enabled.
		if ( debugMode ) {
			this.debugMode = true;
		}

		// Create or update the unified subscription for this store.
		this.ensureStoreSubscription( storeName );

		if ( this.debugMode ) {
			console.log(
				`[SubscriptionManager] Registered funnel ${ funnelId } for store ${ storeName }. ` +
					`Total funnels for this store: ${ this.storeRegistrations[ storeName ].length }`
			);
		}

		return registration;
	}

	/**
	 * Ensure a unified subscription exists for a store.
	 * @param {string} storeName - Name of the store.
	 */
	ensureStoreSubscription( storeName ) {
		// Skip if subscription already exists.
		if ( this.storeSubscriptions[ storeName ] ) {
			return;
		}

		// Create unified subscription.
		const unsubscribe = wp.data.subscribe( () => {
			this.handleStoreChange( storeName );
		} );

		this.storeSubscriptions[ storeName ] = {
			unsubscribe,
			isActive: true,
		};

		if ( this.debugMode ) {
			console.log(
				`[SubscriptionManager] Created unified subscription for store ${ storeName }`
			);
		}
	}

	/**
	 * Handle store changes and route to appropriate funnels.
	 * @param {string} storeName - Name of the store that changed.
	 */
	handleStoreChange( storeName ) {
		try {
			const select = wp.data.select;
			const store = select( storeName );

			if ( ! store ) {
				return;
			}

			// Get all funnel registrations for this store.
			const registrations = this.storeRegistrations[ storeName ] || [];

			// Process each funnel registration.
			registrations.forEach( ( registration ) => {
				try {
					this.processFunnelForStore(
						storeName,
						registration,
						select,
						store
					);
				} catch ( error ) {
					console.error(
						`[SubscriptionManager] Error processing funnel ${ registration.funnelId } for store ${ storeName }:`,
						error
					);
				}
			} );
		} catch ( error ) {
			console.error(
				`[SubscriptionManager] Error handling store change for ${ storeName }:`,
				error
			);
		}
	}

	/**
	 * Process a specific funnel's tracking for a store change.
	 * @param {string}   storeName    - Name of the store.
	 * @param {Object}   registration - Funnel registration object.
	 * @param {Function} select       - WordPress data select function.
	 * @param {Object}   store        - Store object.
	 */
	processFunnelForStore( storeName, registration, select, store ) {
		const { trackingService, fieldRules, fieldConfigs, trackingCondition } =
			registration;

		// Step 1: Evaluate tracking condition for this funnel.
		const conditionMet = this.evaluateTrackingCondition(
			select,
			trackingCondition,
			registration
		);

		const shouldBeActive = this.handleConditionChange(
			registration,
			conditionMet
		);

		if ( ! shouldBeActive ) {
			return; // Skip this funnel.
		}

		// Step 2: Check initialization for this funnel.
		if ( ! registration.isActive ) {
			registration.initializationAttempts++;

			if ( this.isStoreReadyForTracking( store, registration ) ) {
				registration.isActive = true;
				this.initializePreviousValues(
					select,
					storeName,
					fieldConfigs,
					registration.previousValues
				);

				// Track initial page load if appropriate.
				if (
					! registration.hasTrackedPageLoad &&
					this.shouldTrackPageLoad( storeName ) &&
					conditionMet // Double-check condition.
				) {
					this.trackInitialPageLoad(
						select,
						storeName,
						trackingService,
						registration
					);
					registration.hasTrackedPageLoad = true;
				}
			} else {
				return; // Still waiting for initialization.
			}
		}

		// Step 3: Process field changes for this funnel.
		this.processFieldChangesForFunnel(
			select,
			store,
			storeName,
			registration,
			fieldConfigs,
			fieldRules,
			trackingService
		);
	}

	/**
	 * Process field changes for a specific funnel.
	 * Retrieves field sources from the tracking store to determine tracking eligibility.
	 * @param {Function} select          - WordPress data select function.
	 * @param {Object}   store           - Store object.
	 * @param {string}   storeName       - Store name.
	 * @param {Object}   registration    - Funnel registration.
	 * @param {Array}    fieldConfigs    - Field configurations.
	 * @param {Object}   fieldRules      - Field rules.
	 * @param {Object}   trackingService - Tracking service.
	 */
	processFieldChangesForFunnel(
		select,
		store,
		storeName,
		registration,
		fieldConfigs,
		fieldRules,
		trackingService
	) {
		fieldConfigs.forEach( ( fieldConfig ) => {
			try {
				const currentValue = getFieldValue(
					select,
					storeName,
					fieldConfig
				);
				const previousValue =
					registration.previousValues[ fieldConfig.fieldName ];

				// Skip if no change.
				if ( currentValue === previousValue ) {
					return;
				}

				// Get field source from the tracking store.
				const trackingStore = select( 'wc/paypal/tracking' );
				const fieldSource =
					trackingStore?.getFieldSource?.(
						storeName,
						fieldConfig.fieldName
					)?.source || '';

				// Check if this source should be tracked for this funnel.
				const shouldTrack = trackingService.shouldTrackFieldSource(
					fieldConfig.fieldName,
					fieldSource
				);

				if ( ! shouldTrack ) {
					// Update previous value but don't track.
					registration.previousValues[ fieldConfig.fieldName ] =
						currentValue;
					return;
				}

				// Process tracked change for this funnel.
				this.processTrackedChangeForFunnel(
					fieldConfig,
					previousValue,
					currentValue,
					fieldSource,
					trackingService,
					select,
					storeName,
					registration
				);

				// Update previous value.
				registration.previousValues[ fieldConfig.fieldName ] =
					currentValue;
			} catch ( error ) {
				console.error(
					`[SubscriptionManager] Error processing field ${ fieldConfig.fieldName } for funnel ${ registration.funnelId }:`,
					error
				);
			}
		} );
	}

	/**
	 * Evaluate tracking condition for a specific funnel.
	 * @param {Function}    select            - WordPress data select function.
	 * @param {Object|null} trackingCondition - Tracking condition.
	 * @param {Object}      registration      - Funnel registration.
	 * @return {boolean} Whether condition is met.
	 */
	evaluateTrackingCondition( select, trackingCondition, registration ) {
		if ( ! trackingCondition ) {
			return true; // No condition = always track.
		}

		registration.conditionCheckCount++;

		try {
			const conditionStore = select( trackingCondition.store );
			if ( ! conditionStore ) {
				return false;
			}

			// Check store readiness.
			const storeReadiness = conditionStore.transientData?.()?.isReady;
			if ( ! storeReadiness ) {
				return false;
			}

			// Execute selector.
			const selectorFn = conditionStore[ trackingCondition.selector ];
			if ( typeof selectorFn !== 'function' ) {
				return false;
			}

			const selectorResult = selectorFn();
			if ( ! selectorResult || typeof selectorResult !== 'object' ) {
				return false;
			}

			// Evaluate condition.
			let conditionMet;
			if ( trackingCondition.field ) {
				const actualValue = selectorResult[ trackingCondition.field ];
				conditionMet = actualValue === trackingCondition.expectedValue;
			} else {
				const boolResult = !! selectorResult;
				const expectedBool = !! trackingCondition.expectedValue;
				conditionMet = boolResult === expectedBool;
			}

			registration.lastConditionResult = conditionMet;
			return conditionMet;
		} catch ( error ) {
			return false;
		}
	}

	/**
	 * Handle condition state changes for a funnel.
	 * @param {Object}  registration - Funnel registration.
	 * @param {boolean} conditionMet - Whether condition is currently met.
	 * @return {boolean} Whether funnel should be active.
	 */
	handleConditionChange( registration, conditionMet ) {
		// Handle deactivation.
		if ( ! conditionMet && registration.isActive ) {
			this.resetFunnelState( registration );
			return false;
		}

		// Handle reactivation.
		if ( conditionMet && ! registration.isActive ) {
			this.resetFunnelState( registration );
		}

		return conditionMet;
	}

	/**
	 * Reset funnel state for clean reinitialization.
	 * @param {Object} registration - Funnel registration.
	 */
	resetFunnelState( registration ) {
		registration.isActive = false;
		registration.hasTrackedPageLoad = false;
		registration.initializationAttempts = 0;
		registration.previousValues = {};
	}

	/**
	 * Check if store is ready for tracking.
	 * @param {Object} store        - Store object.
	 * @param {Object} registration - Funnel registration.
	 * @return {boolean} Whether store is ready.
	 */
	isStoreReadyForTracking( store, registration ) {
		const isReady = store.transientData?.()?.isReady;

		if ( isReady ) {
			return true;
		}

		// Fallback for stores that take time to initialize.
		return registration.initializationAttempts > 50;
	}

	/**
	 * Initialize previous values for a funnel.
	 * @param {Function} select         - WordPress data select function.
	 * @param {string}   storeName      - Store name.
	 * @param {Array}    fieldConfigs   - Field configurations.
	 * @param {Object}   previousValues - Previous values object to populate.
	 */
	initializePreviousValues(
		select,
		storeName,
		fieldConfigs,
		previousValues
	) {
		fieldConfigs.forEach( ( fieldConfig ) => {
			try {
				const currentValue = getFieldValue(
					select,
					storeName,
					fieldConfig
				);
				previousValues[ fieldConfig.fieldName ] = currentValue;
			} catch ( error ) {
				console.error(
					`[SubscriptionManager] Error initializing ${ fieldConfig.fieldName }:`,
					error
				);
			}
		} );
	}

	/**
	 * Track initial page load.
	 * @param {Function} select          - WordPress data select function.
	 * @param {string}   storeName       - Store name.
	 * @param {Object}   trackingService - Tracking service.
	 * @param {Object}   registration    - Funnel registration object.
	 */
	trackInitialPageLoad( select, storeName, trackingService, registration ) {
		try {
			const persistentData = select( storeName ).persistentData?.();
			const currentStep = persistentData?.step;

			if ( typeof currentStep === 'number' ) {
				const metadata = this.createFunnelMetadata(
					select,
					registration
				);

				trackingService.processStateChange( {
					field: 'step',
					oldValue: null,
					newValue: currentStep,
					action: {
						type: 'PAGE_LOAD',
						payload: { step: currentStep },
						source: 'system',
					},
					metadata,
				} );
			}
		} catch ( error ) {
			console.error(
				`[SubscriptionManager] Error tracking page load for ${ storeName }:`,
				error
			);
		}
	}

	/**
	 * Check if should track page loads for a store.
	 * @param {string} storeName - Store name.
	 * @return {boolean} Whether to track page loads.
	 */
	shouldTrackPageLoad( storeName ) {
		return (
			storeName.includes( 'onboarding' ) || storeName.includes( 'wizard' )
		);
	}

	/**
	 * Process a tracked change for a specific funnel.
	 * @param {Object}   fieldConfig     - Field configuration.
	 * @param {*}        oldValue        - Previous value.
	 * @param {*}        newValue        - New value.
	 * @param {string}   source          - Field source.
	 * @param {Object}   trackingService - Tracking service.
	 * @param {Function} select          - WordPress data select function.
	 * @param {string}   storeName       - Store name.
	 * @param {Object}   registration    - Funnel registration object.
	 */
	processTrackedChangeForFunnel(
		fieldConfig,
		oldValue,
		newValue,
		source,
		trackingService,
		select,
		storeName,
		registration
	) {
		const metadata = this.createFunnelMetadata( select, registration );

		const action = {
			type:
				fieldConfig.type === 'transient'
					? 'SET_TRANSIENT'
					: 'SET_PERSISTENT',
			payload: { [ fieldConfig.fieldName ]: newValue },
			source,
		};

		trackingService.processStateChange( {
			field: fieldConfig.fieldName,
			oldValue,
			newValue,
			action,
			metadata: {
				...metadata,
				detectedSource: source,
			},
		} );
	}

	/**
	 * Create aggregated metadata from all stores tracked by a funnel.
	 * @param {Function} select       - WordPress data select function.
	 * @param {Object}   registration - Funnel registration object.
	 * @return {Object} Aggregated metadata from all funnel stores.
	 */
	createFunnelMetadata( select, registration ) {
		try {
			// Start with base metadata.
			const metadata = {
				action: 'SUBSCRIBER_CHANGE',
				timestamp: Date.now(),
				funnelId: registration.funnelId,
			};

			// Get all stores this funnel tracks from the registration.
			const funnelStores = this.getFunnelStores( registration.funnelId );

			// Aggregate data from each store.
			funnelStores.forEach( ( storeName ) => {
				try {
					const store = select( storeName );
					if ( ! store ) {
						return;
					}

					// Get store data safely.
					const flags = this.safeStoreCall( store, 'flags', {} );
					const persistentData = this.safeStoreCall(
						store,
						'persistentData',
						{}
					);
					const transientData = this.safeStoreCall(
						store,
						'transientData',
						{}
					);

					// Add store-specific metadata with store prefix to avoid conflicts.
					const storeKey = storeName.replace( 'wc/paypal/', '' );
					metadata[ `${ storeKey }_flags` ] = flags;
					metadata[ `${ storeKey }_isReady` ] = transientData.isReady;

					// Spread all store data, with later stores potentially overriding earlier ones.
					// This maintains backward compatibility with existing translation functions.
					Object.assign( metadata, persistentData, transientData );

					// Keep track of which stores contributed data.
					if ( ! metadata.contributingStores ) {
						metadata.contributingStores = [];
					}
					metadata.contributingStores.push( storeName );
				} catch ( error ) {
					console.warn(
						`[SubscriptionManager] Error getting metadata from store ${ storeName }:`,
						error
					);
				}
			} );

			// Add step information after aggregation.
			this.enhanceMetadataWithStepInfo( metadata, registration );

			return metadata;
		} catch ( error ) {
			console.error(
				`[SubscriptionManager] Error creating funnel metadata for ${ registration.funnelId }:`,
				error
			);
			return {
				error: 'funnel_metadata_creation_failed',
				errorMessage: error.message,
				timestamp: Date.now(),
				funnelId: registration.funnelId,
			};
		}
	}

	/**
	 * Enhance metadata with step information from funnel configuration.
	 * @param {Object} metadata     - The metadata object to enhance.
	 * @param {Object} registration - Funnel registration object.
	 */
	enhanceMetadataWithStepInfo( metadata, registration ) {
		try {
			// Get the step number from aggregated data.
			const stepNumber = metadata.step;

			// Get step info directly from registration.
			const stepInfo = registration.stepInfo || {};

			// Add step name if we can map it.
			if ( typeof stepNumber === 'number' && stepInfo[ stepNumber ] ) {
				const stepData = stepInfo[ stepNumber ];
				metadata.stepName =
					typeof stepData === 'string' ? stepData : stepData.name;
			}

			// Add currentStep alias for backward compatibility with existing translations.
			metadata.currentStep = stepNumber;

			// Ensure step fields are properly typed (not string 'null').
			if ( stepNumber === null || stepNumber === undefined ) {
				metadata.step = null;
				metadata.currentStep = null;
			}
		} catch ( error ) {
			console.warn(
				`[SubscriptionManager] Error enhancing metadata with step info:`,
				error
			);
		}
	}

	/**
	 * Get all stores tracked by a specific funnel.
	 * @param {string} funnelId - The funnel ID.
	 * @return {Array} Array of store names.
	 */
	getFunnelStores( funnelId ) {
		const stores = [];
		Object.entries( this.storeRegistrations ).forEach(
			( [ storeName, registrations ] ) => {
				if (
					registrations.some( ( reg ) => reg.funnelId === funnelId )
				) {
					stores.push( storeName );
				}
			}
		);
		return stores;
	}

	/**
	 * Safely call a store method with fallback.
	 * @param {Object} store    - The store object.
	 * @param {string} method   - The method name to call.
	 * @param {*}      fallback - Fallback value if method fails.
	 * @return {*} Method result or fallback.
	 */
	safeStoreCall( store, method, fallback = null ) {
		try {
			if ( typeof store[ method ] === 'function' ) {
				const result = store[ method ]();
				return result !== undefined ? result : fallback;
			}
			return fallback;
		} catch ( error ) {
			return fallback;
		}
	}

	/**
	 * Unregister a funnel from a store.
	 * @param {string} storeName - Store name.
	 * @param {string} funnelId  - Funnel ID.
	 */
	unregisterFunnelForStore( storeName, funnelId ) {
		const registrations = this.storeRegistrations[ storeName ];
		if ( ! registrations ) {
			return;
		}

		const index = registrations.findIndex(
			( reg ) => reg.funnelId === funnelId
		);
		if ( index >= 0 ) {
			registrations.splice( index, 1 );

			if ( this.debugMode ) {
				console.log(
					`[SubscriptionManager] Unregistered funnel ${ funnelId } from store ${ storeName }. ` +
						`Remaining funnels: ${ registrations.length }`
				);
			}

			// If no more funnels for this store, clean up subscription.
			if ( registrations.length === 0 ) {
				this.cleanupStoreSubscription( storeName );
			}
		}
	}

	/**
	 * Clean up subscription for a store.
	 * @param {string} storeName - Store name.
	 */
	cleanupStoreSubscription( storeName ) {
		const subscription = this.storeSubscriptions[ storeName ];
		if ( subscription ) {
			subscription.unsubscribe();
			delete this.storeSubscriptions[ storeName ];
			delete this.storeRegistrations[ storeName ];

			if ( this.debugMode ) {
				console.log(
					`[SubscriptionManager] Cleaned up subscription for store ${ storeName }`
				);
			}
		}
	}

	/**
	 * Get status information for debugging.
	 * @return {Object} Status information.
	 */
	getStatus() {
		const status = {
			storesTracked: Object.keys( this.storeSubscriptions ).length,
			activeSubscriptions: Object.keys( this.storeSubscriptions ).filter(
				( storeName ) => this.storeSubscriptions[ storeName ].isActive
			).length,
			totalFunnelRegistrations: 0,
			storeDetails: {},
		};

		Object.entries( this.storeRegistrations ).forEach(
			( [ storeName, registrations ] ) => {
				status.totalFunnelRegistrations += registrations.length;
				status.storeDetails[ storeName ] = {
					funnelCount: registrations.length,
					funnels: registrations.map( ( reg ) => ( {
						funnelId: reg.funnelId,
						isActive: reg.isActive,
						conditionMet: reg.lastConditionResult,
						conditionChecks: reg.conditionCheckCount,
					} ) ),
				};
			}
		);

		return status;
	}
}

export const subscriptionManager = new SubscriptionManager();
