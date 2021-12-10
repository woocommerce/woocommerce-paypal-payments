/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

const WC_PAYPAL_NAMESPACE = '/wc-paypal/v1';

/* global ppcp_onboarding */

/**
 * Loads the onboarding script file into the dom on the fly.
 *
 * @param {string} url of the onboarding js file.
 * @param {Object} data required for the onboarding script, labeled as PayPalCommerceGatewayOnboarding
 * @param {Function} onLoad callback for when the script is loaded.
 */
const loadOnboardingScript = ( url, data, onLoad ) => {
	try {
		// eslint-disable-next-line camelcase
		if ( ppcp_onboarding ) {
			onLoad();
		}
	} catch ( e ) {
		const script = document.createElement( 'script' );
		script.src = url;
		document.body.append( script );

		// Callback after scripts have loaded.
		script.onload = function () {
			onLoad();
		};
		window.PayPalCommerceGatewayOnboarding = data;
	}
};

export const ConnectionButton = ( { onError = () => {} } ) => {
	const { createNotice } = useDispatch( 'core/notices' );
	const [ connectionUrl, setConnectionUrl ] = useState( null );
	const [ isPending, setIsPending ] = useState( false );

	useEffect( () => {
		fetchOauthConnectionUrl();
	}, [] );

	useEffect( () => {
		if ( ! connectionUrl ) {
			return;
		}

		// eslint-disable-next-line camelcase
		if ( typeof ppcp_onboarding !== 'undefined' ) {
			// Makes sure the onboarding is hooked up to the Connect button rendered.
			ppcp_onboarding.reload();
		}
	}, [ connectionUrl ] );

	const fetchOauthConnectionUrl = () => {
		setIsPending( true );

		apiFetch( {
			path: WC_PAYPAL_NAMESPACE + '/onboarding/get-params',
			method: 'POST',
			data: {
				environment: 'production',
				returnUrlArgs: {
					ppcpobw: '1',
				},
			},
		} )
			.then( ( result ) => {
				if ( ! result || ! result.signupLink ) {
					throw new Error();
				}
				loadOnboardingScript(
					result.scriptURL,
					result.scriptData,
					() => {
						setIsPending( false );
						setConnectionUrl( result.signupLink );
					}
				);
			} )
			.catch( () => {
				createNotice(
					'error',
					__(
						'There was a problem with the Paypal onboarding setup, please fill the fields in manually.',
						'woocommerce-paypal-payments'
					)
				);
				setIsPending( false );
				onError();
			} );
	};

	if ( isPending ) {
		return <Spinner />;
	}

	return (
		<>
			<a
				className="button-primary"
				target="_blank"
				rel="noreferrer"
				disabled={ isPending }
				href={ connectionUrl }
				data-paypal-onboard-button="true"
				data-paypal-button="true"
				data-paypal-onboard-complete="ppcp_onboarding_productionCallback"
			>
				{ __( 'Connect', 'woocommerce-paypal-payments' ) }
			</a>
			<p>
				{ __(
					'You will be redirected to the PayPal website to create the connection.',
					'woocommerce-paypal-payments'
				) }
			</p>
		</>
	);
};
