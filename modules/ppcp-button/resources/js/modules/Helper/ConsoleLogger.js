/**
 * Logs debug details to the console.
 *
 * A utility class that is used by payment buttons on the front-end, like the GooglePayButton.
 */
export default class ConsoleLogger {
	/**
	 * The prefix to display before every log output.
	 *
	 * @type {string}
	 */
	#prefix = '';

	/**
	 * Whether logging is enabled, disabled by default.
	 *
	 * @type {boolean}
	 */
	#enabled = false;

	constructor( ...prefixes ) {
		if ( prefixes.length ) {
			this.#prefix = `[${ prefixes.join( ' | ' ) }]`;
		}
	}

	set enabled( state ) {
		this.#enabled = state;
	}

	log( ...args ) {
		if ( this.#enabled ) {
			console.log( this.#prefix, ...args );
		}
	}

	error( ...args ) {
		if ( this.#enabled ) {
			console.error( this.#prefix, ...args );
		}
	}
}
