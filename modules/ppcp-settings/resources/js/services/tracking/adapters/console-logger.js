/**
 * Console Logger Adapter: Simple tracking event logger for browser console.
 *
 * Logs tracking events to the browser console with configurable formatting.
 * Useful for development, debugging, and testing tracking implementations.
 *
 * @file
 */

export class ConsoleLoggerAdapter {
	/**
	 * Creates a new console logger adapter.
	 * @param {Object}  options                    - Configuration options.
	 * @param {boolean} [options.enabled=true]     - Whether logging is enabled.
	 * @param {string}  [options.prefix='[Track]'] - Prefix for log messages.
	 */
	constructor( options = {} ) {
		this.enabled = options.enabled !== false;
		this.prefix = options.prefix || '[Track]';
	}

	/**
	 * Track an event by logging to console.
	 * @param {string} eventName  - Name of the event to track.
	 * @param {Object} properties - Event properties to log.
	 * @return {boolean} - Success status
	 */
	track( eventName, properties = {} ) {
		if ( ! this.enabled ) {
			return false;
		}

		const hasProperties = Object.keys( properties ).length > 0;

		if ( hasProperties ) {
			console.log( `${ this.prefix } ${ eventName }`, properties );
		} else {
			console.log( `${ this.prefix } ${ eventName }` );
		}

		return true;
	}

	/**
	 * Enable/disable logging.
	 * @param {boolean} enabled - Whether logging is enabled.
	 */
	setEnabled( enabled ) {
		this.enabled = enabled;
	}
}
