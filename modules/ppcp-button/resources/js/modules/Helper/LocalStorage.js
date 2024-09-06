/* global localStorage */

function checkLocalStorageAvailability() {
	try {
		const testKey = '__ppcp_test__';
		localStorage.setItem( testKey, 'test' );
		localStorage.removeItem( testKey );
		return true;
	} catch ( e ) {
		return false;
	}
}

function sanitizeKey( name ) {
	return name
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9_-]/g, '_' );
}

function deserializeEntry( serialized ) {
	try {
		const payload = JSON.parse( serialized );

		return {
			data: payload.data,
			expires: payload.expires || 0,
		};
	} catch ( e ) {
		return null;
	}
}

function serializeEntry( data, timeToLive ) {
	const payload = {
		data,
		expires: calculateExpiration( timeToLive ),
	};

	return JSON.stringify( payload );
}

function calculateExpiration( timeToLive ) {
	return timeToLive ? Date.now() + timeToLive * 1000 : 0;
}

/**
 * A reusable class for handling data storage in the browser's local storage,
 * with optional expiration.
 *
 * Can be extended for module specific logic.
 *
 * @see GooglePaySession
 */
export class LocalStorage {
	/**
	 * @type {string}
	 */
	#group = '';

	/**
	 * @type {null|boolean}
	 */
	#canUseLocalStorage = null;

	/**
	 * @param {string} group - Group name for all storage keys managed by this instance.
	 */
	constructor( group ) {
		this.#group = sanitizeKey( group ) + ':';
		this.#removeExpired();
	}

	/**
	 * Removes all items in the current group that have reached the expiry date.
	 */
	#removeExpired() {
		if ( ! this.canUseLocalStorage ) {
			return;
		}

		Object.keys( localStorage ).forEach( ( key ) => {
			if ( ! key.startsWith( this.#group ) ) {
				return;
			}

			const entry = deserializeEntry( localStorage.getItem( key ) );
			if ( entry && entry.expires > 0 && entry.expires < Date.now() ) {
				localStorage.removeItem( key );
			}
		} );
	}

	/**
	 * Sanitizes the given entry name and adds the group prefix.
	 *
	 * @throws {Error} If the name is empty after sanitization.
	 * @param {string} name - Entry name.
	 * @return {string} Prefixed and sanitized entry name.
	 */
	#entryKey( name ) {
		const sanitizedName = sanitizeKey( name );

		if ( sanitizedName.length === 0 ) {
			throw new Error( 'Name cannot be empty after sanitization' );
		}

		return `${ this.#group }${ sanitizedName }`;
	}

	/**
	 * Indicates, whether localStorage is available.
	 *
	 * @return {boolean} True means the localStorage API is available.
	 */
	get canUseLocalStorage() {
		if ( null === this.#canUseLocalStorage ) {
			this.#canUseLocalStorage = checkLocalStorageAvailability();
		}

		return this.#canUseLocalStorage;
	}

	/**
	 * Stores data in the browser's local storage, with an optional timeout.
	 *
	 * @param {string} name           - Name of the item in the storage.
	 * @param {any}    data           - The data to store.
	 * @param {number} [timeToLive=0] - Lifespan in seconds. 0 means the data won't expire.
	 * @throws {Error} If local storage is not available.
	 */
	set( name, data, timeToLive = 0 ) {
		if ( ! this.canUseLocalStorage ) {
			throw new Error( 'Local storage is not available' );
		}

		const entry = serializeEntry( data, timeToLive );
		const entryKey = this.#entryKey( name );

		localStorage.setItem( entryKey, entry );
	}

	/**
	 * Retrieves previously stored data from the browser's local storage.
	 *
	 * @param {string} name - Name of the stored item.
	 * @return {any|null} The stored data, or null when no valid entry is found or it has expired.
	 * @throws {Error} If local storage is not available.
	 */
	get( name ) {
		if ( ! this.canUseLocalStorage ) {
			throw new Error( 'Local storage is not available' );
		}

		const itemKey = this.#entryKey( name );
		const entry = deserializeEntry( localStorage.getItem( itemKey ) );

		if ( ! entry ) {
			return null;
		}

		return entry.data;
	}

	/**
	 * Removes the specified entry from the browser's local storage.
	 *
	 * @param {string} name - Name of the stored item.
	 * @throws {Error} If local storage is not available.
	 */
	clear( name ) {
		if ( ! this.canUseLocalStorage ) {
			throw new Error( 'Local storage is not available' );
		}

		const itemKey = this.#entryKey( name );
		localStorage.removeItem( itemKey );
	}
}
