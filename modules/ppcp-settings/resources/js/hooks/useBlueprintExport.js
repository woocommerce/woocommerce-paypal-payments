import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { downloadBlob } from '@wordpress/blob';

/**
 * Generates a timestamp string for filenames.
 *
 * @return {string} Formatted timestamp (YYYY-MM-DDTHH-MM-SS)
 */
const generateTimestamp = () => {
	return new Date().toISOString().slice( 0, 19 ).replace( /:/g, '-' );
};

/**
 * Custom hook for exporting PayPal settings as a WooCommerce Blueprint.
 *
 * @return {Object} Object containing exportBlueprint function and isExporting state
 */
export const useBlueprintExport = () => {
	const [ isExporting, setIsExporting ] = useState( false );
	const { createErrorNotice, createSuccessNotice } =
		useDispatch( noticesStore );

	/**
	 * Exports PayPal settings as a WooCommerce Blueprint file.
	 */
	const exportBlueprint = async () => {
		setIsExporting( true );

		try {
			const response = await apiFetch( {
				path: '/wc-admin/blueprint/export',
				method: 'POST',
				data: {
					steps: {
						settings: [ 'paypalSettings', 'wcPaymentGateways' ],
					},
				},
			} );

			if ( ! response?.data ) {
				throw new Error( 'Invalid response from server' );
			}

			const filename = `paypal-blueprint-${ generateTimestamp() }.json`;
			const blueprintData = JSON.stringify( response.data, null, 2 );
			const blob = new Blob( [ blueprintData ], {
				type: 'application/json',
			} );

			downloadBlob( filename, blob, 'application/json' );

			createSuccessNotice(
				__(
					'Blueprint exported successfully.',
					'woocommerce-paypal-payments'
				),
				{ type: 'snackbar' }
			);
		} catch ( error ) {
			createErrorNotice(
				__(
					'Blueprint export failed. Please try again.',
					'woocommerce-paypal-payments'
				),
				{ type: 'snackbar' }
			);
		} finally {
			setIsExporting( false );
		}
	};

	return {
		exportBlueprint,
		isExporting,
	};
};
