import { loadPaypalScript } from '../../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

export const payPalScriptLoader = ( ppcpConfig, callback ) => {
	console.log( 'Loading PayPal script' );
	loadPaypalScript( ppcpConfig, () => {
		console.log( 'PayPal script loaded' );
		callback();
	} );
};
