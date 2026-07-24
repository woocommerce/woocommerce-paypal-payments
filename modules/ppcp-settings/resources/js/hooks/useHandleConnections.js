import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';

import { CommonHooks, OnboardingHooks } from '@ppcp-settings/data';
import { useStoreManager } from './useStoreManager';
import useNotices from './useNotices';

/**
 * PayPal's onboarding SDK (partner.js) is environment-specific and offers no
 * namespace support: it always uses the single `window.PAYPAL` global, derives
 * the environment from its own script domain, and only wires up the *first*
 * `[data-paypal-button]` element it finds. We therefore load the SDK for the
 * active environment and make sure only that environment's button is a real
 * `[data-paypal-button]`, so partner.js binds the correct button to the
 * minibrowser (see `useHandleOnboardingButton`).
 */
const PAYPAL_PARTNER_SDK_URL = {
	production:
		'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js',
	sandbox:
		'https://www.sandbox.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js',
};

const MESSAGES = {
	CONNECTED: __( 'Connected to PayPal', 'woocommerce-paypal-payments' ),
	API_ERROR: __(
		'Could not connect to PayPal. Please make sure your Client ID and Secret Key are correct.',
		'woocommerce-paypal-payments'
	),
	LOGIN_FAILED: __(
		'Login was not successful. Please try again.',
		'woocommerce-paypal-payments'
	),
	ONBOARDING_URL_ERROR: __(
		'Could not load the PayPal connection. Please try again.',
		'woocommerce-paypal-payments'
	),
};

const ACTIVITIES = {
	OAUTH_VERIFY: 'oauth/login',
	API_LOGIN: 'auth/api-login',
	API_VERIFY: 'auth/verify-login',
};

/**
 * Environment ('sandbox' | 'production') of the connection button the merchant
 * actually clicked.
 *
 * PayPal's partner.js exposes a single global onOnboardComplete callback, but the
 * onboarding step mounts a live and a sandbox button at the same time. This
 * module-level value lets the shared completion handler authenticate against the
 * environment the merchant chose, rather than whichever button registered its
 * handler first.
 *
 * @type {?string}
 */
let clickedEnvironment = null;

export const setClickedEnvironment = ( environment ) => {
	clickedEnvironment = environment;
};

export const useHandleOnboardingButton = ( isSandbox ) => {
	const { onboardingUrl } = isSandbox
		? CommonHooks.useSandbox()
		: CommonHooks.useProduction();
	const { isSandboxMode } = CommonHooks.useSandbox();

	/**
	 * partner.js only wires up a single button and derives its environment from
	 * its own script domain, so the live and sandbox SDKs cannot coexist. Only
	 * the button whose environment matches the "Enable Sandbox Mode" toggle is
	 * the active one: it loads the matching SDK and is rendered as a real
	 * `[data-paypal-button]`. The other button is rendered inert (no href /
	 * data-attributes) so partner.js ignores it.
	 */
	const isActiveEnvironment = isSandbox === !! isSandboxMode;

	const { ownBrandOnly, storeCountry } = CommonHooks.useWooSettings();
	const { products, options } = OnboardingHooks.useDetermineProducts(
		ownBrandOnly,
		storeCountry
	);
	const { startActivity } = CommonHooks.useBusyState();
	const { authenticateWithOAuth } = CommonHooks.useAuthentication();
	const { createErrorNotice } = useNotices();
	const [ onboardingUrlState, setOnboardingUrl ] = useState( '' );
	const [ scriptLoaded, setScriptLoaded ] = useState( false );
	const timerRef = useRef( null );

	useEffect( () => {
		const fetchOnboardingUrl = async () => {
			const res = await onboardingUrl( products, options, isSandbox );

			if ( res.success && res.data ) {
				setOnboardingUrl( res.data );
			} else {
				console.error( 'Failed to fetch onboarding URL', res );

				// Stable id so a re-fetch replaces the notice instead of stacking.
				createErrorNotice(
					res?.message ?? MESSAGES.ONBOARDING_URL_ERROR,
					{ id: 'ppcp-onboarding-url-error' }
				);
			}
		};

		fetchOnboardingUrl();
	}, [ isSandbox, products, options, onboardingUrl, createErrorNotice ] );

	useEffect( () => {
		/**
		 * The partner.js script initializes the onboarding button in its onload event.
		 * When no button is present, a JS error is displayed; i.e. we should load this script
		 * only when the button is ready (with a valid href and data-attributes).
		 *
		 * We load the SDK only for the button matching the active environment. Because
		 * partner.js keeps its state on the single `window.PAYPAL` global (and derives
		 * the environment from its own script domain), we reset that global and remove
		 * the previously injected scripts before loading, so switching environments
		 * re-initializes cleanly against the correct domain.
		 */
		if ( ! onboardingUrlState || ! isActiveEnvironment ) {
			return;
		}

		// partner.js injects signup-js and rampConfig-js itself; remove all three
		// so switching between environments starts from a clean slate.
		const removeOnboardingScripts = () => {
			[
				'partner-js',
				'signup-js',
				'rampConfig-js',
				'zoidMiniBrowser-js',
			].forEach( ( id ) => {
				const el = document.querySelector( `script[id="${ id }"]` );

				if ( el?.parentNode ) {
					el.parentNode.removeChild( el );
				}
			} );
		};

		removeOnboardingScripts();
		delete window.PAYPAL;

		const script = document.createElement( 'script' );
		script.id = 'partner-js';
		script.src = isSandbox
			? PAYPAL_PARTNER_SDK_URL.sandbox
			: PAYPAL_PARTNER_SDK_URL.production;
		script.onload = () => {
			setScriptLoaded( true );
		};
		document.body.appendChild( script );

		return () => {
			removeOnboardingScripts();
			delete window.PAYPAL;
			setScriptLoaded( false );
		};
	}, [ onboardingUrlState, isActiveEnvironment, isSandbox ] );

	const setCompleteHandler = useCallback( () => {
		const onComplete = async ( authCode, sharedId ) => {
			/**
			 * Until now, the full page is blocked by PayPal's semi-transparent, black overlay.
			 * But at this point, the overlay is removed, while we process the sharedId and
			 * authCode via a REST call.
			 *
			 * Note: The REST response is irrelevant, since PayPal will most likely refresh this
			 * frame before the REST endpoint returns a value. Using "withActivity" is more of a
			 * visual cue to the user that something is still processing in the background.
			 */
			startActivity(
				ACTIVITIES.OAUTH_VERIFY,
				'Validating the connection details'
			);

			await authenticateWithOAuth(
				sharedId,
				authCode,
				clickedEnvironment === 'sandbox'
			);
		};

		const addHandler = () => {
			const MiniBrowser = window.PAYPAL?.apps?.Signup?.MiniBrowser;
			if ( ! MiniBrowser || MiniBrowser.onOnboardComplete ) {
				return;
			}

			MiniBrowser.onOnboardComplete = onComplete;
		};

		// Ensure the onComplete handler is not removed by a PayPal init script.
		timerRef.current = setInterval( addHandler, 250 );
	}, [ authenticateWithOAuth, startActivity ] );

	const removeCompleteHandler = useCallback( () => {
		if ( timerRef.current ) {
			clearInterval( timerRef.current );
			timerRef.current = null;
		}

		delete window.PAYPAL?.apps?.Signup?.MiniBrowser?.onOnboardComplete;
	}, [] );

	return {
		onboardingUrl: onboardingUrlState,
		scriptLoaded,
		isActiveEnvironment,
		setCompleteHandler,
		removeCompleteHandler,
	};
};

// Base connection is only used for API login (manual connection).
const useConnectionBase = () => {
	const { setCompleted } = OnboardingHooks.useSteps();
	const { createSuccessNotice, createErrorNotice } = useNotices();
	const { verifyLoginStatus } = CommonHooks.useMerchantInfo();
	const { withActivity } = CommonHooks.useBusyState();
	const { refreshAll } = useStoreManager();

	return {
		handleFailed: ( res, genericMessage ) => {
			console.error( 'Connection error', res );
			createErrorNotice( res?.message ?? genericMessage );
		},
		handleCompleted: async () => {
			await withActivity(
				ACTIVITIES.API_VERIFY,
				'Verifying Authentication',
				async () => {
					try {
						const loginSuccessful = await verifyLoginStatus();

						if ( loginSuccessful ) {
							createSuccessNotice( MESSAGES.CONNECTED );
							await setCompleted( true );
							refreshAll();
						} else {
							createErrorNotice( MESSAGES.LOGIN_FAILED );
						}
					} catch ( error ) {
						createErrorNotice(
							error.message ?? MESSAGES.LOGIN_FAILED
						);
					}
				}
			);
		},
		createErrorNotice,
	};
};

export const useSandboxConnection = () => {
	const { isSandboxMode, setSandboxMode } = CommonHooks.useSandbox();

	return {
		isSandboxMode,
		setSandboxMode,
	};
};

export const useDirectAuthentication = () => {
	const { handleFailed, handleCompleted, createErrorNotice } =
		useConnectionBase();
	const { withActivity } = CommonHooks.useBusyState();
	const {
		authenticateWithCredentials,
		isManualConnectionMode,
		setManualConnectionMode,
	} = CommonHooks.useAuthentication();

	const handleDirectAuthentication = async ( connectionDetails ) => {
		return withActivity(
			ACTIVITIES.API_LOGIN,
			'Connecting manually via Client ID and Secret',
			async () => {
				let data;

				if ( 'function' === typeof connectionDetails ) {
					try {
						data = connectionDetails();
					} catch ( exception ) {
						createErrorNotice( exception.message );
						return;
					}
				} else if ( 'object' === typeof connectionDetails ) {
					data = connectionDetails;
				}

				if ( ! data || ! data.clientId || ! data.clientSecret ) {
					createErrorNotice(
						'Invalid connection details (clientID or clientSecret missing)'
					);
					return;
				}

				const res = await authenticateWithCredentials(
					data.clientId,
					data.clientSecret,
					!! data.isSandbox
				);

				if ( res.success ) {
					await handleCompleted();
				} else {
					handleFailed( res, MESSAGES.API_ERROR );
				}

				return res.success;
			}
		);
	};

	return {
		handleDirectAuthentication,
		isManualConnectionMode,
		setManualConnectionMode,
	};
};
