/**
 * Gives the PayPal SDK v6 instance a new client token before the current one
 * expires.
 *
 * The SDK updates the instance and the sessions created from it in place, so
 * rendered buttons, card fields and running payment flows keep working.
 *
 * @package
 */

const HANDLER_KEY = '__ppcpV6TokenRefresh';

const MAX_RETRIES = 3;

/**
 * Other client errors, e.g. an expired nonce, fail the same way on every try,
 * because the page never gets a new nonce.
 *
 * @param {Error} error - The failed fetch.
 * @return {boolean} Whether a later try can succeed.
 */
function isRetryable( error ) {
	const status = error?.status;

	return ! status || status >= 500 || status === 408 || status === 429;
}

export class TokenRefreshHandler {
	constructor() {
		this.timer = null;
		this.refreshAt = 0;
		this.retryIn = undefined;
		this.failures = 0;
		this.refreshing = false;
		this.sdkInstance = null;
		this.fetchTokenData = null;
	}

	/**
	 * A background tab or a sleeping device delays timers, so a visible tab
	 * also refreshes when the planned time has passed.
	 *
	 * @param {Object}   sdkInstance    - The SDK instance to update.
	 * @param {Function} fetchTokenData - Resolves to a new client token
	 *                                  endpoint response.
	 * @param {Object}   tokenData      - The client token endpoint response.
	 */
	start( sdkInstance, fetchTokenData, tokenData ) {
		this.sdkInstance = sdkInstance;
		this.fetchTokenData = fetchTokenData;

		document.addEventListener( 'visibilitychange', () => {
			if (
				document.visibilityState === 'visible' &&
				this.refreshAt &&
				Date.now() >= this.refreshAt
			) {
				this.refresh();
			}
		} );

		this.planNextRefresh( tokenData );
	}

	/**
	 * @param {Object} tokenData - The client token endpoint response.
	 */
	planNextRefresh( tokenData ) {
		this.retryIn = tokenData.retry_in;
		this.failures = 0;
		this.scheduleRefresh( tokenData.refresh_in );
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
	 * A few retries cover a device waking up before its network is back.
	 *
	 * @param {Error} error - The failed fetch.
	 */
	scheduleRetry( error ) {
		if ( ! isRetryable( error ) || this.failures >= MAX_RETRIES ) {
			this.stop();
			return;
		}

		this.failures++;
		this.scheduleRefresh( this.retryIn );
	}

	async refresh() {
		if ( this.refreshing ) {
			return;
		}
		this.refreshing = true;

		try {
			let tokenData;
			try {
				tokenData = await this.fetchTokenData();
			} catch ( error ) {
				console.error(
					'[PPCP SDK v6] Client token refresh failed',
					error
				);
				this.scheduleRetry( error );
				return;
			}

			try {
				this.sdkInstance.updateClientToken( tokenData.client_token );
			} catch ( error ) {
				// The SDK rejects a token it can never accept, e.g. one of another
				// merchant after re-onboarding, so the next payment fails instead.
				console.error(
					'[PPCP SDK v6] Client token update failed',
					error
				);
				this.stop();
				return;
			}

			this.planNextRefresh( tokenData );
		} finally {
			this.refreshing = false;
		}
	}

	stop() {
		clearTimeout( this.timer );
		this.timer = null;
		this.refreshAt = 0;
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
 * @param {Object}   sdkInstance    - The SDK instance to update.
 * @param {Function} fetchTokenData - Resolves to a new client token endpoint
 *                                  response.
 * @param {Object}   tokenData      - The client token endpoint response.
 */
export function startTokenRefresh( sdkInstance, fetchTokenData, tokenData ) {
	handler().start( sdkInstance, fetchTokenData, tokenData );
}
