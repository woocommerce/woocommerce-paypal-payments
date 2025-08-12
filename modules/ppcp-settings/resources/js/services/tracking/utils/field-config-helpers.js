/**
 * Field Configuration Helpers: Generic utilities for creating field tracking configurations.
 *
 * Provides helper functions and builder patterns for defining field tracking rules.
 * Includes validation helpers, transform patterns, and configuration builders for funnels.
 *
 * @file
 */

/**
 * Create a standard field tracking configuration.
 * @param {string} fieldName - The name of the field.
 * @param {string} type      - The type of field ('persistent' or 'transient').
 * @param {Object} options   - Additional configuration options.
 * @return {Object} Field tracking configuration object.
 */
export const createFieldTrackingConfig = (
	fieldName,
	type = 'persistent',
	options = {}
) => {
	return {
		fieldName,
		type,
		selector:
			options.selector ||
			( ( select, storeName ) => {
				const data =
					type === 'persistent'
						? select( storeName ).persistentData()
						: select( storeName ).transientData();
				return data?.[ fieldName ];
			} ),
		...( options.rules && { rules: options.rules } ),
		...options,
	};
};

/**
 * Create a field tracking config that allows both user and system sources.
 * @param {string} fieldName - The name of the field.
 * @param {string} type      - The type of field ('persistent' or 'transient').
 * @param {Object} options   - Additional configuration options.
 * @return {Object} Field tracking configuration object.
 */
export const createSystemFieldTrackingConfig = (
	fieldName,
	type = 'persistent',
	options = {}
) => {
	return createFieldTrackingConfig( fieldName, type, {
		...options,
		rules: {
			allowedSources: [ 'user', 'system' ],
			...options.rules,
		},
	} );
};

/**
 * Create a transient field tracking config (for fields stored in transientData).
 * @param {string} fieldName - The name of the field.
 * @param {Object} options   - Additional configuration options.
 * @return {Object} Field tracking configuration object.
 */
export const createTransientFieldTrackingConfig = (
	fieldName,
	options = {}
) => {
	return createFieldTrackingConfig( fieldName, 'transient', options );
};

/**
 * Create multiple field tracking configs with the same base configuration.
 * @param {Array}  fieldNames  - Array of field names.
 * @param {string} type        - The type of field ('persistent' or 'transient').
 * @param {Object} baseOptions - Base configuration options.
 * @return {Array} Array of field tracking configuration objects.
 */
export const createFieldTrackingConfigs = (
	fieldNames,
	type = 'persistent',
	baseOptions = {}
) => {
	return fieldNames.map( ( fieldName ) =>
		createFieldTrackingConfig( fieldName, type, baseOptions )
	);
};

/**
 * Create a tracking config for nested field data.
 * @param {string} fieldName - The name of the field.
 * @param {string} path      - Dot-separated path to nested field.
 * @param {string} type      - The type of field ('persistent' or 'transient').
 * @param {Object} options   - Additional configuration options.
 * @return {Object} Field tracking configuration object.
 */
export const createNestedFieldTrackingConfig = (
	fieldName,
	path,
	type = 'persistent',
	options = {}
) => {
	return createFieldTrackingConfig( fieldName, type, {
		...options,
		selector: ( select, storeName ) => {
			const data =
				type === 'persistent'
					? select( storeName ).persistentData()
					: select( storeName ).transientData();
			return path
				.split( '.' )
				.reduce( ( obj, key ) => obj?.[ key ], data );
		},
	} );
};

/**
 * Create a tracking config for boolean fields with value mapping.
 * @param {string} fieldName  - The name of the field.
 * @param {string} type       - The type of field ('persistent' or 'transient').
 * @param {string} trueValue  - Value to use for true.
 * @param {string} falseValue - Value to use for false.
 * @return {Object} Field tracking configuration object.
 */
export const createBooleanFieldTrackingConfig = (
	fieldName,
	type = 'persistent',
	trueValue = 'enabled',
	falseValue = 'disabled'
) => {
	return createFieldTrackingConfig( fieldName, type, {
		transform: ( value ) => {
			let selectedValue;
			if ( value === true ) {
				selectedValue = trueValue;
			} else if ( value === false ) {
				selectedValue = falseValue;
			} else {
				selectedValue = 'not_selected';
			}
			return { selected_value: selectedValue };
		},
	} );
};

/**
 * Create a tracking config for array fields.
 * @param {string} fieldName - The name of the field.
 * @param {string} type      - The type of field ('persistent' or 'transient').
 * @param {Object} options   - Additional configuration options.
 * @return {Object} Field tracking configuration object.
 */
export const createArrayFieldTrackingConfig = (
	fieldName,
	type = 'persistent',
	options = {}
) => {
	return createFieldTrackingConfig( fieldName, type, {
		...options,
		transform: ( value ) => ( {
			selected_items: Array.isArray( value ) ? value.join( ',' ) : 'none',
			items_count: Array.isArray( value ) ? value.length : 0,
			...( options.transform ? options.transform( value ) : {} ),
		} ),
	} );
};

/**
 * Create a tracking config for trigger fields (like button clicks).
 * @param {string} fieldName - The name of the field.
 * @param {string} type      - The type of field ('persistent' or 'transient').
 * @param {Object} options   - Additional configuration options.
 * @return {Object} Field tracking configuration object.
 */
export const createTriggerFieldTrackingConfig = (
	fieldName,
	type = 'transient',
	options = {}
) => {
	return createFieldTrackingConfig( fieldName, type, {
		...options,
		// Typically used with translation that checks oldValue === false && newValue === true.
	} );
};

/**
 * Helper to create store field tracking configurations.
 * @param {string}       storeName    - Name of the store.
 * @param {Array|Object} fieldConfigs - Array of field configurations or single config.
 * @return {Object} Store tracking configuration object.
 */
export const createStoreTrackingConfig = ( storeName, fieldConfigs ) => {
	return {
		[ storeName ]: Array.isArray( fieldConfigs )
			? fieldConfigs
			: [ fieldConfigs ],
	};
};

/**
 * Merge multiple store tracking configurations.
 * @param {...Object} configs - Store tracking configuration objects to merge.
 * @return {Object} Merged store tracking configuration.
 */
export const mergeStoreTrackingConfigs = ( ...configs ) => {
	return Object.assign( {}, ...configs );
};

/**
 * Generic configuration builder for any funnel.
 */
export class FunnelConfigBuilder {
	/**
	 * Create a new funnel configuration builder.
	 * @param {string} funnelId - Unique identifier for the funnel.
	 */
	constructor( funnelId ) {
		this.funnelId = funnelId;
		this.config = {
			debug: false,
			adapters: [ 'console' ],
			eventPrefix: funnelId,
			events: {},
			translations: {},
			stepInfo: {},
			fieldConfigs: {},
		};
	}

	/**
	 * Set debug mode.
	 * @param {boolean} debug - Whether to enable debug mode.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	setDebug( debug = true ) {
		this.config.debug = debug;
		return this;
	}

	/**
	 * Set adapters for tracking.
	 * @param {Array} adapters - Array of adapter names.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	setAdapters( adapters ) {
		this.config.adapters = adapters;
		return this;
	}

	/**
	 * Set event prefix.
	 * @param {string} prefix - Prefix for event names.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	setEventPrefix( prefix ) {
		this.config.eventPrefix = prefix;
		return this;
	}

	/**
	 * Add events to the configuration.
	 * @param {Object} events - Object mapping event names to event identifiers.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	addEvents( events ) {
		this.config.events = { ...this.config.events, ...events };
		return this;
	}

	/**
	 * Add translations to the configuration.
	 * @param {Object} translations - Object mapping field names to translation functions.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	addTranslations( translations ) {
		this.config.translations = {
			...this.config.translations,
			...translations,
		};
		return this;
	}

	/**
	 * Add step information to the configuration.
	 * @param {Object} stepInfo - Object mapping step numbers to step metadata.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	addStepInfo( stepInfo ) {
		this.config.stepInfo = { ...this.config.stepInfo, ...stepInfo };
		return this;
	}

	/**
	 * Set tracking condition for the funnel.
	 * @param {Object} condition - Tracking condition configuration.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	setTrackingCondition( condition ) {
		this.config.trackingCondition = condition;
		return this;
	}

	/**
	 * Add store tracking configuration.
	 * @param {string} storeName            - Name of the store.
	 * @param {Array}  fieldTrackingConfigs - Array of field tracking configurations.
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	addStore( storeName, fieldTrackingConfigs ) {
		this.config.fieldConfigs[ storeName ] = fieldTrackingConfigs;
		return this;
	}

	/**
	 * Merge additional configuration.
	 * @param {Object} additionalConfig - Additional configuration to merge
	 * @return {FunnelConfigBuilder} Builder instance for chaining.
	 */
	mergeConfig( additionalConfig ) {
		this.config = { ...this.config, ...additionalConfig };
		return this;
	}

	/**
	 * Build the final configuration.
	 * @return {Object} Complete funnel configuration.
	 */
	build() {
		return this.config;
	}

	/**
	 * Factory method for typical funnel setups.
	 * @param {string} funnelId - Unique identifier for the funnel.
	 * @param {Object} options  - Configuration options.
	 * @return {FunnelConfigBuilder} New builder instance.
	 */
	static createBasicFunnel( funnelId, options = {} ) {
		const builder = new FunnelConfigBuilder( funnelId )
			.setDebug( options.debug || false )
			.setAdapters( options.adapters || [ 'console' ] );

		if ( options.eventPrefix ) {
			builder.setEventPrefix( options.eventPrefix );
		}

		if ( options.trackingCondition ) {
			builder.setTrackingCondition( options.trackingCondition );
		}

		return builder;
	}
}

/**
 * Common field tracking rule patterns.
 */
export const RulePatterns = {
	// Only user interactions.
	userOnly: { allowedSources: [ 'user' ] },

	// User interactions and system changes.
	userAndSystem: { allowedSources: [ 'user', 'system' ] },

	// Only system changes.
	systemOnly: { allowedSources: [ 'system' ] },

	// Custom rule creator.
	custom: ( sources ) => ( { allowedSources: sources } ),
};

/**
 * Common transform patterns for field tracking.
 */
export const TransformPatterns = {
	// Boolean to enabled/disabled.
	enabledDisabled: ( value ) => {
		let selectedValue;
		if ( value === true ) {
			selectedValue = 'enabled';
		} else if ( value === false ) {
			selectedValue = 'disabled';
		} else {
			selectedValue = 'not_selected';
		}
		return { selected_value: selectedValue };
	},

	// Boolean to yes/no.
	yesNo: ( value ) => {
		let selectedValue;
		if ( value === true ) {
			selectedValue = 'yes';
		} else if ( value === false ) {
			selectedValue = 'no';
		} else {
			selectedValue = 'not_selected';
		}
		return { selected_value: selectedValue };
	},

	// Array to comma-separated string with count.
	arrayWithCount: ( value ) => ( {
		selected_items: Array.isArray( value ) ? value.join( ',' ) : 'none',
		items_count: Array.isArray( value ) ? value.length : 0,
	} ),

	// Just the raw value.
	passthrough: ( value ) => ( { selected_value: value } ),

	// Custom transform creator.
	custom: ( transformFn ) => transformFn,
};

/**
 * Validation helpers.
 */
export const ValidationHelpers = {
	// Validate field tracking configuration.
	validateFieldTrackingConfig: ( fieldConfig ) => {
		const errors = [];

		if ( ! fieldConfig.fieldName ) {
			errors.push( 'fieldName is required' );
		}

		if ( ! [ 'persistent', 'transient' ].includes( fieldConfig.type ) ) {
			errors.push( 'type must be "persistent" or "transient"' );
		}

		if ( fieldConfig.rules && ! fieldConfig.rules.allowedSources ) {
			errors.push(
				'rules.allowedSources is required when rules are specified'
			);
		}

		return { valid: errors.length === 0, errors };
	},

	// Validate store tracking configuration.
	validateStoreTrackingConfig: ( storeConfig ) => {
		const errors = [];

		Object.entries( storeConfig ).forEach(
			( [ storeName, fieldConfigs ] ) => {
				if ( ! Array.isArray( fieldConfigs ) ) {
					errors.push(
						`Field tracking configs for store ${ storeName } must be an array`
					);
					return;
				}

				fieldConfigs.forEach( ( fieldConfig, index ) => {
					const validation =
						ValidationHelpers.validateFieldTrackingConfig(
							fieldConfig
						);
					if ( ! validation.valid ) {
						errors.push(
							`Store ${ storeName }, field config ${ index }: ${ validation.errors.join(
								', '
							) }`
						);
					}
				} );
			}
		);

		return { valid: errors.length === 0, errors };
	},
};
