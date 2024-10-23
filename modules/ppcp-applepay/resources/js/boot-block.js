import { useEffect, useRef, useState } from '@wordpress/element';
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import { cartHasSubscriptionProducts } from '../../../ppcp-blocks/resources/js/Helper/Subscription';
import { loadCustomScript } from '@paypal/paypal-js';
import CheckoutHandler from './Context/CheckoutHandler';
import ApplePayManager from './ApplepayManager';
import ApplePayManagerBlockEditor from './ApplepayManagerBlockEditor';

const ppcpData = wc.wcSettings.getSetting( 'ppcp-gateway_data' );
const ppcpConfig = ppcpData.scriptData;

const buttonData = wc.wcSettings.getSetting( 'ppcp-applepay_data' );
const buttonConfig = buttonData.scriptData;
const namespace = 'ppcpBlocksPaypalApplepay';

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const ApplePayComponent = ( { isEditing } ) => {
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ applePayLoaded, setApplePayLoaded ] = useState( false );
	const wrapperRef = useRef( null );

	useEffect( () => {
		if ( isEditing ) {
			return;
		}

		// Load ApplePay SDK
		loadCustomScript( { url: buttonConfig.sdk_url } ).then( () => {
			setApplePayLoaded( true );
		} );

		ppcpConfig.url_params.components += ',applepay';

		// Load PayPal
		loadPayPalScript( namespace, ppcpConfig )
			.then( () => {
				setPaypalLoaded( true );
			} )
			.catch( ( error ) => {
				console.error( 'Failed to load PayPal script: ', error );
			} );
	}, [ isEditing ] );

	useEffect( () => {
		if ( isEditing || ! paypalLoaded || ! applePayLoaded ) {
			return;
		}

		const ManagerClass = isEditing
			? ApplePayManagerBlockEditor
			: ApplePayManager;

		buttonConfig.reactWrapper = wrapperRef.current;

		new ManagerClass( namespace, buttonConfig, ppcpConfig );
	}, [ paypalLoaded, applePayLoaded, isEditing ] );

	if ( isEditing ) {
		return (
			<ApplePayManagerBlockEditor
				namespace={ namespace }
				buttonConfig={ buttonConfig }
				ppcpConfig={ ppcpConfig }
			/>
		);
	}

	return (
		<div
			ref={ wrapperRef }
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
	title: `PayPal - ${ buttonData.title }`,
	description: __(
		'Eligible users will see the PayPal button.',
		'woocommerce-paypal-payments'
	),
	label: <div dangerouslySetInnerHTML={ { __html: buttonData.title } } />,
	content: <ApplePayComponent isEditing={ false } />,
	edit: <ApplePayComponent isEditing={ true } />,
	ariaLabel: buttonData.title,
	canMakePayment: () => buttonData.enabled,
	supports: {
		features,
	},
} );
