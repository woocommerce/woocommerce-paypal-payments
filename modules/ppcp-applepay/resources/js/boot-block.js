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

const ApplePayComponent = ( { isEditing, buttonAttributes } ) => {
	const [ paypalLoaded, setPaypalLoaded ] = useState( false );
	const [ applePayLoaded, setApplePayLoaded ] = useState( false );
	const [ manager, setManager ] = useState( null );
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
		if ( isEditing || ! paypalLoaded || ! applePayLoaded || manager ) {
			return;
		}

		const ManagerClass = isEditing
			? ApplePayManagerBlockEditor
			: ApplePayManager;

		buttonConfig.reactWrapper = wrapperRef.current;

		const newManager = new ManagerClass(
			namespace,
			buttonConfig,
			ppcpConfig,
			buttonAttributes
		);

		setManager( newManager );
	}, [ paypalLoaded, applePayLoaded, isEditing, buttonAttributes, manager ] );

	useEffect( () => {
		if ( ! manager || isEditing ) {
			return;
		}

		let previousTotal = null;

		const unsubscribe = wp.data.subscribe( () => {
			const store = wp.data.select( 'wc/store/cart' );
			if ( ! store ) {
				return;
			}

			const totals = store.getCartTotals();
			if ( ! totals ) {
				return;
			}

			const currentTotal =
				parseInt( totals.total_price, 10 ) /
				10 ** totals.currency_minor_unit;

			if ( currentTotal !== previousTotal && previousTotal !== null ) {
				previousTotal = currentTotal;
				manager.reinit();
			} else if ( previousTotal === null ) {
				previousTotal = currentTotal;
			}
		} );

		return () => {
			unsubscribe();
		};
	}, [ manager, isEditing ] );

	if ( isEditing ) {
		return (
			<ApplePayManagerBlockEditor
				namespace={ namespace }
				buttonConfig={ buttonConfig }
				ppcpConfig={ ppcpConfig }
				buttonAttributes={ buttonAttributes }
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

if ( buttonConfig?.is_enabled ) {
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
		canMakePayment: () =>
			buttonData.enabled && window.ApplePaySession?.canMakePayments(),
		supports: {
			features,
			style: [ 'height', 'borderRadius' ],
		},
	} );
}
