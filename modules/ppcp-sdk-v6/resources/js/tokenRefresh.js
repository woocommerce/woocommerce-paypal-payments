/**
 * Renews the PayPal SDK v6 instance before its client token expires.
 *
 * The SDK cannot change the token of an existing instance, and every object
 * created from an instance keeps its token. So the page gets a new instance,
 * and each consumer creates its objects again from it.
 *
 * @package
 */

import { backoffDelay, isRetryable } from './utils/retry';

const HANDLER_KEY = '__ppcpV6TokenRefresh';

// The refresh is planned this long before the token expires; mirrors
// ClientTokenEndpoint::REFRESH_BEFORE_EXPIRY.
const REFRESH_BEFORE_EXPIRY_MS = 60000;
const MAX_RETRY_SECONDS = 300;
// After this many 4xx answers in a row the page stops refreshing.
const MAX_REJECTIONS = 2;

export class TokenRefreshHandler {
	constructor() {
		this.listeners = [];
		this.timer = null;
		this.refreshAt = 0;
		this.retryIn = undefined;
		this.expiresAt = 0;
		this.failing = false;
		this.lateFailures = 0;
		this.rejections = 0;
		this.stopped = false;
		this.refreshing = false;
		this.busy = false;
		this.pendingInstance = null;
		this.renewInstance = null;
	}

	/**
	 * A background tab or a sleeping device delays timers, so a visible tab
	 * also refreshes when the planned time has passed. A failing refresh is
	 * tried again at once when the tab becomes visible or the browser is back
	 * online, rather than at the end of its wait.
	 *
	 * @param {Function} renewInstance - Resolves to { sdkInstance, tokenData }
	 *                                 with a new token.
	 * @param {Object}   tokenData     - The client token endpoint response.
	 */
	start( renewInstance, tokenData ) {
		this.renewInstance = renewInstance;

		document.addEventListener( 'visibilitychange', () => {
			const due = this.refreshAt && Date.now() >= this.refreshAt;
			if (
				document.visibilityState === 'visible' &&
				( due || this.failing )
			) {
				this.refresh();
			}
		} );

		window.addEventListener( 'online', () => {
			if ( this.failing ) {
				this.refresh();
			}
		} );

		this.planNextRefresh( tokenData );
	}

	/**
	 * @param {Function} callback - Receives the new SDK instance.
	 * @return {Function} Removes the callback again.
	 */
	subscribe( callback ) {
		this.listeners.push( callback );

		return () => {
			const index = this.listeners.indexOf( callback );
			if ( index >= 0 ) {
				this.listeners.splice( index, 1 );
			}
		};
	}

	/**
	 * @param {boolean} busy - Whether a payment flow runs.
	 */
	setBusy( busy ) {
		this.busy = Boolean( busy );

		if ( ! this.busy && this.pendingInstance ) {
			const sdkInstance = this.pendingInstance;
			this.pendingInstance = null;
			this.notify( sdkInstance );
		}
	}

	/**
	 * @param {Object} tokenData - The client token endpoint response.
	 */
	planNextRefresh( tokenData ) {
		this.retryIn = tokenData.retry_in;
		this.scheduleRefresh( tokenData.refresh_in );
		this.expiresAt = this.refreshAt + REFRESH_BEFORE_EXPIRY_MS;
	}

	/**
	 * @param {number} seconds - Seconds until the refresh, from the server.
	 */
	scheduleRefresh( seconds ) {
		if ( ! Number.isFinite( seconds ) ) {
			return;
		}

		const delay = seconds * 1000;

		clearTimeout( this.timer );
		this.refreshAt = Date.now() + delay;
		this.timer = setTimeout( () => this.refresh(), delay );
	}

	/**
	 * Plans no further refresh, also not on a visibility or online event.
	 */
	stop() {
		clearTimeout( this.timer );
		this.timer = null;
		this.refreshAt = 0;
		this.stopped = true;
	}

	/**
	 * While the current token is still valid a failed refresh is retried after
	 * retry_in; once it has expired, twice as long after each further failure,
	 * up to MAX_RETRY_SECONDS, and never sooner than the server asks for.
	 *
	 * @param {Error} error - Why the refresh failed.
	 * @return {number} Seconds until the next attempt.
	 */
	retryDelay( error ) {
		let seconds = this.retryIn;
		if ( Date.now() >= this.expiresAt ) {
			this.lateFailures += 1;
			seconds = backoffDelay(
				this.lateFailures,
				this.retryIn,
				MAX_RETRY_SECONDS
			);
		}

		return Math.max( seconds, Number( error?.retryIn ) || 0 );
	}

	/**
	 * A failed refresh is retried without a limit, since the instance is
	 * useless once its token expires. Only MAX_REJECTIONS 4xx answers in a row,
	 * such as a firewall that blocks the endpoint, end the refreshes.
	 */
	async refresh() {
		if ( this.refreshing || this.stopped ) {
			return;
		}
		this.refreshing = true;
		// Whatever triggered this attempt, it replaces the planned one.
		clearTimeout( this.timer );

		try {
			const { sdkInstance, tokenData } = await this.renewInstance();
			this.failing = false;
			this.lateFailures = 0;
			this.rejections = 0;
			this.planNextRefresh( tokenData );

			if ( this.busy ) {
				this.pendingInstance = sdkInstance;
			} else {
				this.notify( sdkInstance );
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6] Client token refresh failed', error );
			this.failing = true;
			this.rejections = isRetryable( error ) ? 0 : this.rejections + 1;

			if ( this.rejections >= MAX_REJECTIONS ) {
				this.stop();
			} else {
				this.scheduleRefresh( this.retryDelay( error ) );
			}
		} finally {
			this.refreshing = false;
		}
	}

	/**
	 * @param {Object} sdkInstance - The new SDK instance.
	 */
	notify( sdkInstance ) {
		// A copy, because a listener can remove itself.
		[ ...this.listeners ].forEach( ( listener ) => {
			try {
				listener( sdkInstance );
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( '[PPCP SDK v6]', error );
			}
		} );
	}
}

/**
 * One handler per page, on window rather than in module scope, because each
 * webpack bundle gets its own copy of this module.
 *
 * @return {TokenRefreshHandler} The handler.
 */
function handler() {
	if ( ! window[ HANDLER_KEY ] ) {
		window[ HANDLER_KEY ] = new TokenRefreshHandler();
	}

	return window[ HANDLER_KEY ];
}

/**
 * @param {Function} renewInstance - Resolves to { sdkInstance, tokenData } with
 *                                 a new token.
 * @param {Object}   tokenData     - The client token endpoint response.
 */
export function startTokenRefresh( renewInstance, tokenData ) {
	handler().start( renewInstance, tokenData );
}

/**
 * An object that must outlive the client token is created again from the new
 * instance.
 *
 * @param {Function} callback - Receives the new SDK instance.
 * @return {Function} Removes the callback again.
 */
export function onSdkInstanceChange( callback ) {
	return handler().subscribe( callback );
}

/**
 * While a payment flow runs, the listeners get a new instance only after it
 * ends, because they replace the objects the flow uses.
 *
 * @param {boolean} busy - Whether a payment flow runs.
 */
export function setSdkBusy( busy ) {
	handler().setBusy( busy );
}

/**
 * Wraps the callbacks of a session config that end the payment flow, so the
 * flow releases the busy state when the callback is done.
 *
 * @param {Object} sessionConfig - The session config.
 * @return {Object} The same config.
 */
export function releasingSdkBusy( sessionConfig ) {
	for ( const name of [ 'onApprove', 'onCancel', 'onError' ] ) {
		const callback = sessionConfig[ name ];
		if ( 'function' !== typeof callback ) {
			continue;
		}

		sessionConfig[ name ] = async ( ...args ) => {
			try {
				return await callback( ...args );
			} finally {
				setSdkBusy( false );
			}
		};
	}

	return sessionConfig;
}
