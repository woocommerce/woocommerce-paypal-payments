import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { log } from '@ppcp-axo/Helper/Debug';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';
import { STORE_NAME } from '@ppcp-axo-block/stores/axoStore';

/**
 * Custom hook to load the PayPal script.
 *
 * @param {string}  namespace      - Namespace for the PayPal script.
 * @param {Object}  ppcpConfig     - Configuration object for PayPal script.
 * @param {boolean} isConfigLoaded - Whether the PayPal Commerce Gateway config is loaded.
 * @return {boolean} True if the PayPal script has loaded, false otherwise.
 */
const usePayPalScript = ( namespace, ppcpConfig, isConfigLoaded ) => {
	// Get dispatch functions from the AXO store
	const { setIsPayPalLoaded } = useDispatch( STORE_NAME );

	// Select relevant states from the AXO store
	const { isPayPalLoaded } = useSelect(
		( select ) => ( {
			isPayPalLoaded: select( STORE_NAME ).getIsPayPalLoaded(),
		} ),
		[]
	);

	useEffect( () => {
		const loadScript = async () => {
			if ( ! isPayPalLoaded && isConfigLoaded ) {
				const axoConfig = window.wc_ppcp_axo;

				try {
					const res = await fetch(
						axoConfig.ajax.axo_script_attributes.endpoint,
						{
							method: 'POST',
							credentials: 'same-origin',
							body: JSON.stringify( {
								nonce: axoConfig.ajax.axo_script_attributes
									.nonce,
							} ),
						}
					);

					const json = await res.json();
					if ( ! json.success ) {
						log(
							`Failed to load axo script attributes: ${ json.data.message }`,
							'error'
						);

						return;
					}

					await loadPayPalScript( namespace, {
						...ppcpConfig,
						script_attributes: {
							...ppcpConfig.script_attributes,
							'data-sdk-client-token': json.data.sdk_client_token,
						},
					} );
					setIsPayPalLoaded( true );
				} catch ( error ) {
					log(
						`Error loading PayPal script for namespace: ${ namespace }. Error: ${ error }`,
						'error'
					);
				}
			}
		};

		loadScript();
	}, [ ppcpConfig, isConfigLoaded, isPayPalLoaded ] );

	return isPayPalLoaded;
};

export default usePayPalScript;
