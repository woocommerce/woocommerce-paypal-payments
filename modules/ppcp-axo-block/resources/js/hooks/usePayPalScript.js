import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { loadPayPalScript } from '../../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import { STORE_NAME } from '../stores/axoStore';

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
				try {
					await loadPayPalScript( namespace, ppcpConfig );
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
