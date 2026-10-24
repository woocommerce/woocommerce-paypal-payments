/**
 * Fetches a client token and creates the PayPal SDK v6 instance.
 *
 * @package
 */

import { postJson } from './utils/api';
import { createSdkInstance } from './utils/createSdkInstance';
import { loadScript } from './utils/scriptLoaders';
import { methodSdkComponents } from './methods/methodRegistry';
import { startTokenRefresh } from './tokenRefresh';
import { backoffDelay, isRetryable } from './utils/retry';

const INSTANCE_KEY = '__ppcpV6InstancePromise';
const METADATA_ID_KEY = '__ppcpV6ClientMetadataId';
const FAILURE_KEY = '__ppcpV6LoadFailure';
const RECOVERY_KEY = '__ppcpV6LoadRecovery';

const LOAD_TIMEOUT_MS = 15000;

// The wait before the next load after a failed one, doubled per failure.
const RETRY_BASE_MS = 5000;
const RETRY_MAX_MS = 60000;
// After this many 4xx answers in a row the page stops trying until a reload.
const MAX_REJECTIONS = 2;

/**
 * The in-flight instance promise, shared across bundles.
 *
 * On window rather than in module scope because each webpack bundle gets its own
 * copy of this module: the ppcp-axo bundle asking for Fastlane must reuse the
 * instance this module's own bootstrap created, or the SDK script loads twice and
 * its custom elements fail to register a second time.
 *
 * @return {?Promise<Object>} The cached promise, or null.
 */
function cachedInstance() {
	return window[ INSTANCE_KEY ] || null;
}

/**
 * One client metadata id per page, shared across bundles.
 *
 * PayPal correlates it with the order for fraud checks, and createInstance
 * rejects outright when the fastlane component is requested without it.
 *
 * @return {string} The id.
 */
function clientMetadataId() {
	if ( ! window[ METADATA_ID_KEY ] ) {
		window[ METADATA_ID_KEY ] =
			window.crypto?.randomUUID?.() ||
			`ppcp-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	return window[ METADATA_ID_KEY ];
}

const PAGE_TYPE_MAP = {
	product: 'product-details',
	cart: 'cart',
	checkout: 'checkout',
	'pay-now': 'checkout',
	'mini-cart': 'mini-cart',
	'cart-block': 'cart',
	'checkout-block': 'checkout',
};

/**
 * Rejects when the promise has not settled within the given time.
 *
 * @param {Promise} promise - The promise to wait for.
 * @param {number}  ms      - The time limit.
 * @return {Promise} Settles as the promise does, or rejects on timeout.
 */
function withTimeout( promise, ms ) {
	const error = new Error( `PayPal SDK v6 did not load within ${ ms } ms.` );
	let timer;
	const timeout = new Promise( ( resolve, reject ) => {
		timer = setTimeout( () => reject( error ), ms );
	} );

	return Promise.race( [ promise, timeout ] ).finally( () =>
		clearTimeout( timer )
	);
}

/**
 * The error of the last failed load, while the next load has to wait.
 *
 * On window for the same reason as the instance promise. Callers ask for the
 * instance on DOM and cart events (the messages MutationObserver, WooCommerce
 * fragment and checkout updates, block cart changes), so without the wait a
 * failing load would be repeated on every one of them.
 *
 * @return {?Error} The error, or null when a load may start.
 */
function blockingFailure() {
	const failure = window[ FAILURE_KEY ];
	if ( ! failure ) {
		return null;
	}

	// A clock set back counts as the wait having passed.
	const elapsed = Date.now() - failure.failedAt;
	const waiting = elapsed >= 0 && elapsed < failure.wait;

	return failure.final || waiting ? failure.error : null;
}

/**
 * The wait never ends a load for good unless the server answered 4xx twice in
 * a row; one such answer can be a passing firewall block. A wait the server
 * asks for (retry_in) is kept even when it is longer than the backoff.
 *
 * @param {Error} error - Why the load failed.
 */
function recordFailure( error ) {
	const previous = window[ FAILURE_KEY ];
	const failures = ( previous?.failures || 0 ) + 1;
	const rejections = isRetryable( error )
		? 0
		: ( previous?.rejections || 0 ) + 1;

	window[ FAILURE_KEY ] = {
		error,
		failures,
		rejections,
		final: rejections >= MAX_REJECTIONS,
		failedAt: Date.now(),
		wait: Math.max(
			backoffDelay( failures, RETRY_BASE_MS, RETRY_MAX_MS ),
			( Number( error?.retryIn ) || 0 ) * 1000
		),
	};

	watchForRecovery();
}

/**
 * A browser back online, or a page restored from the back/forward cache, may
 * load at once rather than at the end of a wait meant for the earlier failure.
 */
function watchForRecovery() {
	if ( window[ RECOVERY_KEY ] ) {
		return;
	}
	window[ RECOVERY_KEY ] = true;

	const clearWait = () => {
		if ( ! window[ FAILURE_KEY ]?.final ) {
			delete window[ FAILURE_KEY ];
		}
	};

	window.addEventListener( 'online', clearWait );
	window.addEventListener( 'pageshow', ( event ) => {
		if ( event.persisted ) {
			clearWait();
		}
	} );
}

/**
 * Loads the SDK script, fetches a client token and creates the instance.
 *
 * Memoized on the in-flight promise so concurrent callers share one
 * token fetch and one instance; reset on failure or timeout to allow retries.
 * A retry waits RETRY_BASE_MS after the first failure, twice as long after
 * each further one up to RETRY_MAX_MS, and no longer happens after
 * MAX_REJECTIONS 4xx answers in a row; during the wait callers get the last
 * error without a new request.
 * Dispatches the ppcp-sdk-v6-ready event once, when the instance exists, and
 * starts its token refresh; an instance that only arrives after the timeout is
 * dropped.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context used for the SDK pageType.
 * @return {Promise<Object>} The SDK instance.
 */
export function loadSdkV6( config, context ) {
	if ( ! cachedInstance() ) {
		const failure = blockingFailure();
		if ( failure ) {
			return Promise.reject( failure );
		}

		window[ INSTANCE_KEY ] = withTimeout(
			createInstance( config, context ),
			LOAD_TIMEOUT_MS
		)
			.then( ( { sdkInstance, tokenData, renewInstance } ) => {
				delete window[ FAILURE_KEY ];

				// Later loadSdkV6() calls get the new instance.
				startTokenRefresh( renewInstance, tokenData );

				document.dispatchEvent(
					new CustomEvent( 'ppcp-sdk-v6-ready', {
						detail: { sdkInstance },
					} )
				);

				return sdkInstance;
			} )
			.catch( ( error ) => {
				delete window[ INSTANCE_KEY ];
				recordFailure( error );
				throw error;
			} );
	}

	return cachedInstance();
}

/**
 * Performs the actual script load, token fetch and instance creation.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context used for the SDK pageType.
 * @return {Promise<Object>} The { sdkInstance, tokenData } it was created with,
 *                           and renewInstance(), which creates its successor.
 */
async function createInstance( config, context ) {
	const [ , tokenData ] = await Promise.all( [
		loadScript( config.sdk_url ),
		postJson( config.ajax.client_token ),
	] );

	if ( ! window.paypal?.createInstance ) {
		throw new Error( 'PayPal SDK v6 global not found after script load.' );
	}

	const components = [ 'paypal-payments' ];
	// One instance serves the page and the mini-cart.
	if ( Object.values( config.venmo_button || {} ).some( Boolean ) ) {
		components.push( 'venmo-payments' );
	}
	if ( config.card_fields?.enabled ) {
		components.push( 'card-fields' );
	}
	// Its own component, not part of paypal-payments, and only the classic row
	// uses the SDK at all.
	if ( config.card_button?.row ) {
		components.push( 'paypal-guest-payments' );
	}
	if ( config.fastlane?.enabled ) {
		components.push( 'fastlane' );
	}
	components.push( ...methodSdkComponents( config ) );
	if ( config.messages?.enabled ) {
		components.push( 'paypal-messages' );
	}

	const instanceOptions = {
		clientToken: tokenData.client_token,
		components,
		pageType: PAGE_TYPE_MAP[ context ] || 'checkout',
		locale: config.locale,
		clientMetadataId: clientMetadataId(),
	};

	const sdkInstance = await createSdkInstance(
		window.paypal,
		instanceOptions
	);

	return {
		sdkInstance,
		tokenData,
		renewInstance: async () => {
			const nextTokenData = await postJson( config.ajax.client_token );
			const nextInstance = await createSdkInstance( window.paypal, {
				...instanceOptions,
				clientToken: nextTokenData.client_token,
			} );
			window[ INSTANCE_KEY ] = Promise.resolve( nextInstance );

			return { sdkInstance: nextInstance, tokenData: nextTokenData };
		},
	};
}
