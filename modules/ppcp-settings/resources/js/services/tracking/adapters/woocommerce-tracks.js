/**
 * WooCommerce Tracks Adapter: Send tracking events to WooCommerce Tracks API.
 *
 * Handles event transmission to WooCommerce Tracks with proper data sanitization.
 * Includes smart prefix handling, event queuing, and WooCommerce naming conventions.
 *
 * @file
 */

export class WooCommerceTracksAdapter {
	/**
	 * Create a WooCommerce Tracks adapter.
	 * @param {string} eventPrefix - Prefix for all track events.
	 * @param {Object} options     - Configuration options.
	 */
	constructor( eventPrefix = 'ppcp_onboarding', options = {} ) {
		this.eventPrefix = eventPrefix;
		this.debug = options.debugMode || false;
		this.isAvailable = this.checkAvailability();
		this.pendingEvents = [];
		this.setupAvailabilityCheck();
	}

	/**
	 * Get the correct tracking function (real system with fallback)
	 * @return {Function|null} The tracking function to use.
	 */
	getTrackingFunction() {
		// Use wc.tracks.recordEvent (real system) with wcTracks.recordEvent as fallback.
		return (
			window.wc?.tracks?.recordEvent ??
			window.wcTracks?.recordEvent ??
			null
		);
	}

	/**
	 * Check if WooCommerce Tracks is available.
	 * @return {boolean} Whether WooCommerce Tracks is available.
	 */
	checkAvailability() {
		const trackingFunction = this.getTrackingFunction();
		const isAvailable = !! (
			typeof window !== 'undefined' &&
			trackingFunction &&
			typeof trackingFunction === 'function'
		);

		// Debug which tracking system we're using.
		if ( isAvailable && this.debug ) {
			if ( window.wc?.tracks?.recordEvent ) {
				console.log(
					'[WC Tracks] Using wc.tracks.recordEvent (real system)'
				);
			} else if ( window.wcTracks?.recordEvent ) {
				console.log(
					'[WC Tracks] Using wcTracks.recordEvent (fallback)'
				);
			}
		}

		return isAvailable;
	}

	/**
	 * Set up periodic checks for tracks availability.
	 * @return {void}
	 */
	setupAvailabilityCheck() {
		if ( ! this.isAvailable ) {
			const checkInterval = setInterval( () => {
				if ( this.checkAvailability() ) {
					this.isAvailable = true;
					this.processPendingEvents();
					clearInterval( checkInterval );
				}
			}, 1000 );

			// Stop checking after 5 seconds.
			setTimeout( () => clearInterval( checkInterval ), 5000 );
		}
	}

	/**
	 * Log debug messages if debug mode is enabled.
	 * @param {...any} args - Arguments to pass to console.log.
	 * @return {void}
	 */
	debugLog( ...args ) {
		if ( this.debug ) {
			console.log( ...args );
		}
	}

	/**
	 * Build the full event name with smart prefix handling.
	 * @param {string} eventName - The base event name.
	 * @return {string} The full event name.
	 */
	buildEventName( eventName ) {
		// Check if the event name already starts with the expected prefix.
		if ( eventName.startsWith( this.eventPrefix + '_' ) ) {
			// Event already has the correct prefix, use as-is.
			this.debugLog( '[WC Tracks] Event already prefixed:', eventName );
			return eventName;
		}

		// Event doesn't have prefix, add it.
		const fullEventName = `${ this.eventPrefix }_${ eventName }`;
		this.debugLog(
			'[WC Tracks] Adding prefix:',
			eventName,
			'→',
			fullEventName
		);
		return fullEventName;
	}

	/**
	 * Track an event
	 * @param {string} eventName  - The name of the event to track.
	 * @param {Object} properties - Properties to send with the event.
	 * @return {boolean} Success status of the tracking event.
	 */
	track( eventName, properties = {} ) {
		if ( ! this.isAvailable ) {
			// Queue events if tracks isn't available yet.
			this.pendingEvents.push( {
				eventName,
				properties,
				timestamp: Date.now(),
			} );
			this.debugLog( '[WC Tracks] Not available, queuing:', eventName );
			return false;
		}

		const fullEventName = this.buildEventName( eventName );

		// Validate event name follows WooCommerce pattern.
		if ( ! this.isValidEventName( fullEventName ) ) {
			console.error( '[WC Tracks] Invalid event name:', fullEventName );
			return false;
		}

		try {
			// Use the real tracking function.
			const trackingFunction = this.getTrackingFunction();

			if ( ! trackingFunction ) {
				console.error( '[WC Tracks] No tracking function available' );
				return false;
			}

			// Sanitize properties for WooCommerce.
			const sanitizedProps = this.sanitizeProperties( properties );

			// Call the tracking function (either wc.tracks.recordEvent or wcTracks.recordEvent).
			trackingFunction( fullEventName, sanitizedProps );

			this.debugLog(
				'[WC Tracks] Event sent:',
				fullEventName,
				sanitizedProps
			);
			return true;
		} catch ( error ) {
			console.error( '[WC Tracks] Error sending event:', error );
			return false;
		}
	}

	/**
	 * Process any events that were queued while tracks was unavailable.
	 * @return {void}
	 */
	processPendingEvents() {
		if ( this.pendingEvents.length === 0 ) {
			return;
		}

		this.debugLog(
			`[WC Tracks] Processing ${ this.pendingEvents.length } queued events`
		);

		this.pendingEvents.forEach( ( { eventName, properties } ) => {
			this.track( eventName, properties );
		} );

		this.pendingEvents = [];
	}

	/**
	 * Validate event name follows WooCommerce pattern: ^[a-z_][a-z0-9_]*$.
	 * @param {string} eventName - The event name to validate.
	 * @return {boolean} Whether the event name is valid.
	 */
	isValidEventName( eventName ) {
		const pattern = /^[a-z_][a-z0-9_]*$/;
		return pattern.test( eventName );
	}

	/**
	 * Sanitize properties according to WooCommerce requirements.
	 * @param {Object} properties - Properties to send with the event.
	 * @return {Object} Sanitized properties object.
	 */
	sanitizeProperties( properties ) {
		const sanitized = {};

		Object.entries( properties ).forEach( ( [ key, value ] ) => {
			// Convert key to lowercase with underscores.
			const sanitizedKey = key
				.toLowerCase()
				.replace( /[^a-z0-9_]/g, '_' );

			// Skip properties with leading underscores (reserved by WooCommerce).
			if ( sanitizedKey.startsWith( '_' ) && ! key.startsWith( '_' ) ) {
				return;
			}

			// Handle different value types.
			if ( value === null || value === undefined ) {
				sanitized[ sanitizedKey ] = 'null';
			} else if ( typeof value === 'boolean' ) {
				sanitized[ sanitizedKey ] = value;
			} else if ( typeof value === 'number' ) {
				sanitized[ sanitizedKey ] = value;
			} else if ( Array.isArray( value ) ) {
				// Convert arrays to comma-separated strings.
				sanitized[ sanitizedKey ] = value.join( ',' );
			} else if ( typeof value === 'object' ) {
				// Convert objects to JSON strings (truncated for safety).
				const jsonString = JSON.stringify( value );
				sanitized[ sanitizedKey ] =
					jsonString.length > 200
						? jsonString.substring( 0, 200 ) + '...'
						: jsonString;
			} else {
				// Convert to string and truncate if too long.
				const stringValue = String( value );
				sanitized[ sanitizedKey ] =
					stringValue.length > 255
						? stringValue.substring( 0, 255 ) + '...'
						: stringValue;
			}
		} );

		return sanitized;
	}

	/**
	 * Get adapter info for debugging.
	 * @return {Object} Adapter information.
	 */
	getInfo() {
		const trackingFunction = this.getTrackingFunction();
		const usingRealSystem = !! window.wc?.tracks?.recordEvent;

		return {
			name: 'WooCommerce Tracks',
			available: this.isAvailable,
			eventPrefix: this.eventPrefix,
			pendingEvents: this.pendingEvents.length,
			debug: this.debug,
			usingRealSystem,
			trackingFunction: trackingFunction ? 'available' : 'not available',
		};
	}

	/**
	 * Enable or disable debug mode.
	 * @param {boolean|Object} options - Boolean or options object with debugMode property.
	 * @return {void}
	 */
	setDebugMode( options ) {
		if ( typeof options === 'boolean' ) {
			this.debug = options;
		} else if ( options && typeof options === 'object' ) {
			this.debug = !! options.debugMode;
		}
	}
}
