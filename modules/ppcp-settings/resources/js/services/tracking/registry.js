/**
 * Registry: Manages tracking funnel registration and store coordination.
 *
 * Handles registration of tracking funnels with support for multiple funnels per store.
 * Coordinates with SubscriptionManager for unified tracking across all registered funnels.
 *
 * @file
 */

import { WooCommerceTracksAdapter, ConsoleLoggerAdapter } from './adapters';
import { FunnelTrackingService } from './services/funnel-tracking';
import { subscriptionManager } from './subscription-manager';

// Registry to track funnels and their configurations.
const trackingRegistry = {
	funnels: {},
	storeToFunnel: {}, // Store name -> array of funnel IDs
	instances: {},
};

/**
 * Register a tracking funnel with its configuration.
 * @param {string} funnelId - Unique identifier for the funnel.
 * @param {Object} config   - Funnel configuration including tracking conditions.
 */
export function registerFunnel( funnelId, config ) {
	const fullConfig = {
		debug: false,
		adapters: [ 'console' ],
		eventPrefix: 'ppcp_general',
		fieldConfigs: {},
		events: {},
		translations: {},
		stepInfo: {},
		trackingCondition: null,
		...config,
		funnelId,
	};

	// Validate tracking condition if provided.
	if ( fullConfig.trackingCondition ) {
		const validation = validateTrackingCondition(
			fullConfig.trackingCondition
		);
		if ( ! validation.valid ) {
			console.error(
				`[REGISTRY] Invalid tracking condition for funnel ${ funnelId }:`,
				validation.errors
			);
		}
	}

	// Store the funnel registration.
	trackingRegistry.funnels[ funnelId ] = {
		funnelId,
		config: fullConfig,
		stores: [],
		isInitialized: false,
	};

	return {
		funnelId,
		config: fullConfig,
	};
}

/**
 * Add a store to a funnel for tracking.
 * @param {string} storeName - Name of the store to add.
 * @param {string} funnelId  - ID of the funnel to add the store to.
 */
export function addStoreToFunnel( storeName, funnelId ) {
	// Check if funnel exists.
	if ( ! trackingRegistry.funnels[ funnelId ] ) {
		console.error( `[REGISTRY] Funnel ${ funnelId } does not exist` );
		return false;
	}

	// Add store to funnel if not already added.
	if ( ! trackingRegistry.funnels[ funnelId ].stores.includes( storeName ) ) {
		trackingRegistry.funnels[ funnelId ].stores.push( storeName );
	}

	// Map store to funnel.
	if ( ! trackingRegistry.storeToFunnel[ storeName ] ) {
		trackingRegistry.storeToFunnel[ storeName ] = [];
	}

	if ( ! trackingRegistry.storeToFunnel[ storeName ].includes( funnelId ) ) {
		trackingRegistry.storeToFunnel[ storeName ].push( funnelId );
	}

	return true;
}

/**
 * Initialize all registered tracking funnels.
 */
export function initializeTracking() {
	const initialized = {};

	// Initialize each registered funnel.
	Object.values( trackingRegistry.funnels ).forEach( ( funnel ) => {
		if ( ! funnel.isInitialized ) {
			const instance = initializeTrackingFunnel( funnel.funnelId );
			if ( instance ) {
				initialized[ funnel.funnelId ] = instance;
				trackingRegistry.funnels[
					funnel.funnelId
				].isInitialized = true;
			}
		}
	} );

	return initialized;
}

/**
 * Initialize a single tracking funnel with subscription manager coordination.
 * @param {string} funnelId - The funnel ID to initialize.
 */
function initializeTrackingFunnel( funnelId ) {
	const funnel = trackingRegistry.funnels[ funnelId ];
	if ( ! funnel ) {
		console.error( `[REGISTRY] Funnel ${ funnelId } not found` );
		return null;
	}

	const { config, stores } = funnel;

	// Skip if no stores are registered for this funnel.
	if ( stores.length === 0 ) {
		console.warn(
			`[REGISTRY] No stores registered for funnel ${ funnelId }`
		);
		return null;
	}

	const trackingService = new FunnelTrackingService( config, {
		debugMode: config.debug,
	} );

	// Add requested adapters.
	if ( config.adapters.includes( 'woocommerce-tracks' ) ) {
		trackingService.addAdapter(
			new WooCommerceTracksAdapter( config.eventPrefix, {
				debugMode: config.debug,
			} )
		);
	}

	if ( config.adapters.includes( 'console' ) || config.debug ) {
		trackingService.addAdapter(
			new ConsoleLoggerAdapter( {
				enabled: true,
				logLevel: config.debug ? 'debug' : 'info',
				prefix: `[${ funnelId }]`,
				colorize: true,
				showTimestamp: true,
			} )
		);
	}

	const registrations = [];
	stores.forEach( ( storeName ) => {
		// Check if WordPress data store exists.
		if ( ! wp.data || ! wp.data.select( storeName ) ) {
			console.warn(
				`[REGISTRY] Store ${ storeName } not available for funnel ${ funnelId }`
			);
			return;
		}

		// Get field configs for this store in this funnel.
		const fieldConfigs = config.fieldConfigs[ storeName ] || [];

		// Extract field rules from field configs.
		const fieldRules = {};
		fieldConfigs.forEach( ( fieldConfig ) => {
			if ( fieldConfig.rules ) {
				fieldRules[ fieldConfig.fieldName ] = fieldConfig.rules;
			}
		} );

		// Register this funnel for the store with the subscription manager.
		const registration = subscriptionManager.registerFunnelForStore(
			storeName,
			funnelId,
			trackingService,
			fieldRules,
			fieldConfigs,
			config.debug,
			config.trackingCondition,
			config.stepInfo
		);

		registrations.push( {
			storeName,
			registration,
		} );
	} );

	// Create the tracking instance.
	const instance = {
		funnelId,
		trackingService,
		stores,
		config,
		trackingCondition: config.trackingCondition,
		registrations, // Store the subscription manager registrations.

		unsubscribe: () => {
			// Unregister from subscription manager.
			registrations.forEach( ( { storeName } ) => {
				subscriptionManager.unregisterFunnelForStore(
					storeName,
					funnelId
				);
			} );
			delete trackingRegistry.instances[ funnelId ];
		},

		getConditionStatus: () => {
			const storeStatuses = {};
			registrations.forEach( ( { storeName, registration } ) => {
				storeStatuses[ storeName ] = {
					isActive: registration.isActive,
					conditionMet: registration.lastConditionResult,
					conditionChecks: registration.conditionCheckCount,
					initAttempts: registration.initializationAttempts,
				};
			} );
			return storeStatuses;
		},

		testCondition: () => {
			const results = {};
			registrations.forEach( ( { storeName, registration } ) => {
				// Force condition check by accessing the subscription manager.
				const conditionMet =
					subscriptionManager.evaluateTrackingCondition(
						wp.data.select,
						registration.trackingCondition,
						registration
					);
				results[ storeName ] = {
					conditionMet,
					registration: {
						funnelId: registration.funnelId,
						isActive: registration.isActive,
						lastResult: registration.lastConditionResult,
					},
				};
			} );
			return results;
		},

		// Get detailed status from subscription manager.
		getDetailedStatus: () => {
			return {
				funnelId,
				stores,
				trackingCondition: config.trackingCondition,
				storeStatuses: instance.getConditionStatus(),
				subscriptionManagerStatus: subscriptionManager.getStatus(),
				adapterCount: trackingService.adapters.length,
				eventCount: trackingService.eventCount,
			};
		},
	};

	// Store the instance.
	trackingRegistry.instances[ funnelId ] = instance;

	return instance;
}

/**
 * Get all registered tracking funnels.
 */
export function getRegisteredFunnels() {
	return { ...trackingRegistry.funnels };
}

/**
 * Get all initialized tracking instances.
 */
export function getTrackingInstances() {
	return { ...trackingRegistry.instances };
}

/**
 * Helper to get a specific tracking instance.
 * @param {string} funnelId - The funnel ID.
 */
export function getTrackingInstance( funnelId ) {
	return trackingRegistry.instances[ funnelId ] || null;
}

/**
 * Get stores that are tracked by multiple funnels.
 * @return {Object} Store name -> array of funnel IDs.
 */
export function getMultiFunnelStores() {
	const multiFunnelStores = {};
	Object.entries( trackingRegistry.storeToFunnel ).forEach(
		( [ storeName, funnelIds ] ) => {
			if ( funnelIds.length > 1 ) {
				multiFunnelStores[ storeName ] = funnelIds;
			}
		}
	);
	return multiFunnelStores;
}

/**
 * Get all funnels tracking a specific store.
 * @param {string} storeName - The store name.
 * @return {Array} Array of funnel IDs.
 */
export function getFunnelsForStore( storeName ) {
	return trackingRegistry.storeToFunnel[ storeName ] || [];
}

/**
 * Get comprehensive tracking status.
 * @return {Object} Complete status information.
 */
export function getTrackingStatus() {
	const status = {
		totalFunnels: Object.keys( trackingRegistry.funnels ).length,
		initializedFunnels: Object.keys( trackingRegistry.instances ).length,
		totalStores: Object.keys( trackingRegistry.storeToFunnel ).length,
		multiFunnelStores: getMultiFunnelStores(),
		subscriptionManagerStatus: subscriptionManager.getStatus(),
		funnelDetails: {},
	};

	// Add details for each funnel.
	Object.values( trackingRegistry.instances ).forEach( ( instance ) => {
		status.funnelDetails[ instance.funnelId ] =
			instance.getDetailedStatus();
	} );

	return status;
}

/**
 * Validate tracking condition configuration.
 * @param {Object|null} trackingCondition - The condition to validate.
 * @return {Object} Validation result with valid flag, errors, and condition.
 */
function validateTrackingCondition( trackingCondition ) {
	if ( ! trackingCondition ) {
		return { valid: true, message: 'No condition specified' };
	}

	const errors = [];

	if ( ! trackingCondition.store ) {
		errors.push( 'Missing required field: store' );
	}

	if ( ! trackingCondition.selector ) {
		errors.push( 'Missing required field: selector' );
	}

	return {
		valid: errors.length === 0,
		errors,
		condition: trackingCondition,
	};
}

/**
 * Helper to check if a funnel is properly configured.
 * @param {string} funnelId - The funnel ID to validate.
 * @return {Object} Validation result with valid flag, errors, warnings, and stats.
 */
export function validateFunnelConfig( funnelId ) {
	const funnel = trackingRegistry.funnels[ funnelId ];
	if ( ! funnel ) {
		return { valid: false, errors: [ `Funnel ${ funnelId } not found` ] };
	}

	const { config } = funnel;
	const errors = [];
	const warnings = [];

	// Check if events are defined.
	if ( ! config.events || Object.keys( config.events ).length === 0 ) {
		warnings.push( 'No events defined for funnel' );
	}

	// Check if field configs exist.
	if (
		! config.fieldConfigs ||
		Object.keys( config.fieldConfigs ).length === 0
	) {
		errors.push( 'No field configurations defined' );
	}

	// Check if translations exist for tracked fields.
	const allFields = [];
	Object.values( config.fieldConfigs ).forEach( ( storeFields ) => {
		allFields.push( ...storeFields.map( ( f ) => f.fieldName ) );
	} );

	const missingTranslations = allFields.filter(
		( field ) => ! config.translations[ field ]
	);
	if ( missingTranslations.length > 0 ) {
		warnings.push(
			`Missing translations for fields: ${ missingTranslations.join(
				', '
			) }`
		);
	}

	// Validate tracking condition if present.
	if ( config.trackingCondition ) {
		const conditionValidation = validateTrackingCondition(
			config.trackingCondition
		);
		if ( ! conditionValidation.valid ) {
			errors.push(
				`Invalid tracking condition: ${ conditionValidation.errors.join(
					', '
				) }`
			);
		}
	}

	return {
		valid: errors.length === 0,
		errors,
		warnings,
		stats: {
			stores: Object.keys( config.fieldConfigs ).length,
			fields: allFields.length,
			events: Object.keys( config.events ).length,
			translations: Object.keys( config.translations ).length,
			trackingCondition: config.trackingCondition ? 'configured' : 'none',
		},
	};
}
