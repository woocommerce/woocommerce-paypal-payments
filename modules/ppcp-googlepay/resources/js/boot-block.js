import { useEffect, useState } from '@wordpress/element';
import {
	registerExpressPaymentMethod,
	registerPaymentMethod,
} from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import GooglepayManager from './GooglepayManager';
import { loadCustomScript } from '@paypal/paypal-js';
import GooglepayManagerBlockEditor from './GooglepayManagerBlockEditor';

const ppcpData = wc.wcSettings.getSetting( 'ppcp-gateway_data' );
const ppcpConfig = ppcpData.scriptData;

const buttonData = wc.wcSettings.getSetting( 'ppcp-googlepay_data' );
const buttonConfig = buttonData.scriptData;
const namespace = 'ppcpBlocksPaypalGooglepay';

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const GooglePayComponent = ( props ) => {
	const [ bootstrapped, setBootstrapped ] = useState( false );
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ googlePayLoaded, setGooglePayLoaded ] = useState( false );
	const [ manager, setManager ] = useState( null );

	useEffect( () => {
		// Load GooglePay SDK
		loadCustomScript( { url: buttonConfig.sdk_url } ).then( () => {
			setGooglePayLoaded( true );
		} );

		ppcpConfig.url_params.components += ',googlepay';

		// Load PayPal
		loadPayPalScript( namespace, ppcpConfig )
			.then( () => {
				setPaypalLoaded( true );
			} )
			.catch( ( error ) => {
				console.error( 'Failed to load PayPal script: ', error );
			} );
	}, [] );

	useEffect( () => {
		if ( paypalLoaded && googlePayLoaded && ! manager ) {
			const ManagerClass = props.isEditing
				? GooglepayManagerBlockEditor
				: GooglepayManager;
			const newManager = new ManagerClass(
				namespace,
				buttonConfig,
				ppcpConfig
			);
			setManager( newManager );
		}
	}, [ paypalLoaded, googlePayLoaded, props.isEditing ] );

	useEffect( () => {
		if ( manager && ! bootstrapped ) {
			setBootstrapped( true );
			manager.init();
		}
	}, [ manager, bootstrapped ] );

	return (
		<div
			id={ buttonConfig.button.wrapper.replace( '#', '' ) }
			className="ppcp-button-apm ppcp-button-googlepay"
		></div>
	);
};

const features = [ 'products' ];

registerExpressPaymentMethod( {
	name: buttonData.id,
	title: `PayPal - ${ buttonData.title }`,
	description: __(
		'Eligible users will see the PayPal button.',
		'woocommerce-paypal-payments'
	),
	gatewayId: 'ppcp-gateway',
	label: <div dangerouslySetInnerHTML={ { __html: buttonData.title } } />,
	content: <GooglePayComponent isEditing={ false } />,
	edit: <GooglePayComponent isEditing={ true } />,
	ariaLabel: buttonData.title,
	canMakePayment: () => buttonData.enabled,
	supports: {
		features,
	},
} );
