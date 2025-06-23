/**
 * Funnel Tracking Service: Generic tracking service with funnel-specific translations.
 *
 * Processes state changes and routes events through funnel-specific translation functions.
 * Manages adapters, session tracking, and provides field-level source filtering.
 *
 * @file
 */

export class FunnelTrackingService {
	/**
	 * @param {Object}  funnelConfig        - Funnel configuration object.
	 * @param {Object}  [options={}]        - Optional configuration.
	 * @param {boolean} [options.debugMode] - Enable debug mode.
	 */
	constructor( funnelConfig, options = {} ) {
		this.funnelConfig = funnelConfig;
		this.adapters = [];
		this.sessionStartTime = Date.now();
		this.sessionId = this.generateSessionId();
		this.eventCount = 0;
		this.debugMode = options.debugMode || false;

		// Get funnel-specific configurations.
		this.events = funnelConfig.events || {};
		this.translations = funnelConfig.translations || {};
		this.stepInfo = funnelConfig.stepInfo || {};

		// Sources that are completely ignored.
		this.ignoredSources = new Set( [
			'subscription',
			'unknown',
			undefined,
			'', // Empty source.
		] );

		// Force debug for PayPal funnels.
		if (
			funnelConfig.funnelId?.includes( 'ppcp' ) ||
			funnelConfig.funnelId?.includes( 'paypal' )
		) {
			this.debugMode = true;
		}

		// Expose globally for debugging.
		if ( typeof window !== 'undefined' ) {
			window.funnelTrackingService = this;
		}
	}

	/**
	 * Generate a unique session ID.
	 * @return {string} Generated session ID.
	 */
	generateSessionId() {
		return `${
			this.funnelConfig.eventPrefix || 'tracking'
		}_${ Date.now() }_${ Math.random().toString( 36 ).slice( 2, 11 ) }`;
	}
	/**
	 * Register a tracking adapter.
	 * @param {Object} adapter - Tracking adapter instance.
	 */
	addAdapter( adapter ) {
		this.adapters.push( adapter );
	}

	/**
	 * Remove all adapters.
	 */
	clearAdapters() {
		this.adapters = [];
	}

	/**
	 * Get all registered adapters.
	 * @return {Array} Array of adapter information.
	 */
	getAdapters() {
		return this.adapters.map(
			( adapter ) => adapter.getInfo?.() || adapter
		);
	}

	/**
	 * Process state changes using funnel-specific logic.
	 * @param {Object}        changeEvent          - State change event object.
	 * @param {string}        changeEvent.field    - Field name that changed.
	 * @param {*}             changeEvent.oldValue - Previous value.
	 * @param {*}             changeEvent.newValue - New value.
	 * @param {Object}        changeEvent.metadata - Additional metadata.
	 * @param {string|Object} changeEvent.action   - Action that triggered the change.
	 */
	processStateChange( changeEvent ) {
		const { field, oldValue, newValue, metadata, action } = changeEvent;

		// Skip if no actual change.
		if ( oldValue === newValue ) {
			return;
		}

		// Handle different types of action input.
		let source;
		let actionType;

		if ( typeof action === 'string' ) {
			source = metadata?.source || '';
			actionType = action;
		} else if ( action && typeof action === 'object' && action.type ) {
			source = action.source || 'unknown';
			actionType = action.type;
		} else {
			return;
		}

		const fieldName =
			field ||
			( action?.payload
				? Object.keys( action.payload )[ 0 ]
				: 'unknown' );

		// Filter based on field-specific source rules.
		const shouldTrack = this.shouldTrackFieldSource( fieldName, source );

		if ( ! shouldTrack ) {
			return;
		}

		// Use funnel-specific translation if available.
		this.processTrackedChange( fieldName, oldValue, newValue, {
			...metadata,
			source,
			actionType,
		} );
	}

	/**
	 * Determine if a field/source combination should be tracked.
	 * @param {string} fieldName - Name of the field.
	 * @param {string} source    - Source of the change.
	 * @return {boolean} Whether the field/source should be tracked.
	 */
	shouldTrackFieldSource( fieldName, source ) {
		// Ignore completely blocked sources.
		if ( this.ignoredSources.has( source ) ) {
			return false;
		}

		// Find field rules in funnel configuration.
		const fieldRules = this.findFieldRules( fieldName );

		if ( fieldRules ) {
			return fieldRules.allowedSources.includes( source );
		}
		// No rules = accept all sources.
		return true;
	}

	/**
	 * Find field rules from funnel configuration.
	 * @param {string} fieldName - Name of the field.
	 * @return {Object|null} Field rules object or null if not found.
	 */
	findFieldRules( fieldName ) {
		for ( const storeName in this.funnelConfig.fieldConfigs ) {
			const storeFields = this.funnelConfig.fieldConfigs[ storeName ];
			const fieldConfig = storeFields.find(
				( f ) => f.fieldName === fieldName
			);

			if ( fieldConfig && fieldConfig.rules ) {
				return fieldConfig.rules;
			}
		}

		return null;
	}

	/**
	 * Process tracked changes using funnel-specific translations.
	 * @param {string} fieldName - Name of the field.
	 * @param {*}      oldValue  - Previous value.
	 * @param {*}      newValue  - New value.
	 * @param {Object} metadata  - Additional metadata.
	 */
	processTrackedChange( fieldName, oldValue, newValue, metadata ) {
		// Check if funnel has a specific translation for this field.
		const translationFn = this.translations[ fieldName ];

		if ( translationFn && typeof translationFn === 'function' ) {
			// Use funnel-specific translation.
			try {
				translationFn( oldValue, newValue, metadata, this );
			} catch ( error ) {
				console.error(
					`[Funnel Tracking] Error in translation for ${ fieldName }:`,
					error
				);
			}
		} else {
			// Fallback to generic tracking.
			this.genericFieldTracking(
				fieldName,
				oldValue,
				newValue,
				metadata
			);
		}
	}

	/**
	 * Generic field tracking when no specific translation exists.
	 * @param {string} fieldName - Name of the field.
	 * @param {*}      oldValue  - Previous value.
	 * @param {*}      newValue  - New value.
	 * @param {Object} metadata  - Additional metadata.
	 */
	genericFieldTracking( fieldName, oldValue, newValue, metadata ) {
		const eventName = `${ fieldName }_change`;
		const properties = {
			field_name: fieldName,
			old_value: oldValue,
			new_value: newValue,
			source: metadata.source,
			...this.getCommonProperties( metadata ),
		};

		this.sendToAdapters( eventName, properties );
	}

	/**
	 * Get common properties for all tracking events.
	 * @param {Object} [metadata={}] - Additional metadata.
	 * @return {Object} Common properties object.
	 */
	getCommonProperties( metadata = {} ) {
		return {};
	}

	/**
	 * Send event to all registered adapters.
	 * @param {string} eventName  - Name of the event.
	 * @param {Object} properties - Event properties.
	 */
	sendToAdapters( eventName, properties ) {
		this.eventCount++;

		this.adapters.forEach( ( adapter, index ) => {
			try {
				// Send only the essential properties.
				adapter.track( eventName, properties );
			} catch ( error ) {
				console.error(
					`[Funnel Tracking] Adapter ${ index } error:`,
					error,
					adapter.getInfo?.() || 'unknown adapter'
				);
			}
		} );
	}

	/**
	 * Get tracking service statistics.
	 * @return {Object} Statistics object.
	 */
	getStats() {
		const stats = {
			sessionId: this.sessionId,
			sessionStartTime: this.sessionStartTime,
			sessionDuration: Date.now() - this.sessionStartTime,
			eventCount: this.eventCount,
			adaptersCount: this.adapters.length,
			debugMode: this.debugMode,
			funnelId: this.funnelConfig.funnelId,
			eventsAvailable: Object.keys( this.events ).length,
			translationsAvailable: Object.keys( this.translations ).length,
			fieldConfigStores: Object.keys(
				this.funnelConfig.fieldConfigs || {}
			),
			totalFieldConfigs: Object.values(
				this.funnelConfig.fieldConfigs || {}
			).reduce( ( total, configs ) => total + configs.length, 0 ),
		};

		return stats;
	}

	/**
	 * Debug helper to test field source tracking.
	 * @param {string} fieldName - Name of the field to test.
	 * @param {string} source    - Source to test.
	 * @return {Object} Test results object.
	 */
	testFieldSourceTracking( fieldName, source ) {
		return {
			field: fieldName,
			source,
			shouldTrack: this.shouldTrackFieldSource( fieldName, source ),
			fieldRules: this.findFieldRules( fieldName ),
			ignoredSources: Array.from( this.ignoredSources ),
		};
	}
}

/**
 * Factory function to create tracking service with funnel configuration.
 * @param {Object} funnelConfig - Funnel configuration object.
 * @param {Object} [options={}] - Optional configuration.
 * @return {FunnelTrackingService} New tracking service instance.
 */
export function createFunnelTrackingService( funnelConfig, options = {} ) {
	return new FunnelTrackingService( funnelConfig, options );
}
