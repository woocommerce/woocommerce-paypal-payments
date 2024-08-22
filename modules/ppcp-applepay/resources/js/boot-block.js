import { useEffect, useState } from '@wordpress/element';
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';
import { cartHasSubscriptionProducts } from '../../../ppcp-blocks/resources/js/Helper/Subscription';
import { loadCustomScript } from '@paypal/paypal-js';
import CheckoutHandler from './Context/CheckoutHandler';
import ApplepayManager from './ApplepayManager';
import ApplepayManagerBlockEditor from './ApplepayManagerBlockEditor';

const ppcpData = wc.wcSettings.getSetting( 'ppcp-gateway_data' );
const ppcpConfig = ppcpData.scriptData;

const buttonData = wc.wcSettings.getSetting( 'ppcp-applepay_data' );
const buttonConfig = buttonData.scriptData;

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const ApplePayComponent = ( props ) => {
	const [ bootstrapped, setBootstrapped ] = useState( false );
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ applePayLoaded, setApplePayLoaded ] = useState( false );

	const bootstrap = function () {
		const ManagerClass = props.isEditing
			? ApplepayManagerBlockEditor
			: ApplepayManager;
		const manager = new ManagerClass( buttonConfig, ppcpConfig );
		manager.init();
	};

	useEffect( () => {
		// Load ApplePay SDK
		loadCustomScript( { url: buttonConfig.sdk_url } ).then( () => {
			setApplePayLoaded( true );
		} );

		ppcpConfig.url_params.components += ',applepay';

		// Load PayPal
		loadPaypalScript( ppcpConfig, () => {
			setPaypalLoaded( true );
		} );
	}, [] );

	useEffect( () => {
		if ( ! bootstrapped && paypalLoaded && applePayLoaded ) {
			setBootstrapped( true );
			bootstrap();
		}
	}, [ paypalLoaded, applePayLoaded ] );

	return (
		<div
			id={ buttonConfig.button.wrapper.replace( '#', '' ) }
			className="ppcp-button-apm ppcp-button-applepay"
		></div>
	);
};

const features = [ 'products' ];

if (
	cartHasSubscriptionProducts( ppcpConfig ) &&
	new CheckoutHandler( buttonConfig, ppcpConfig ).isVaultV3Mode()
) {
	features.push( 'subscriptions' );
}

registerExpressPaymentMethod( {
	name: buttonData.id,
	label: <div dangerouslySetInnerHTML={ { __html: buttonData.title } } />,
	content: <ApplePayComponent isEditing={ false } />,
	edit: <ApplePayComponent isEditing={ true } />,
	ariaLabel: buttonData.title,
	canMakePayment: () => buttonData.enabled,
	supports: {
		features,
	},
} );
