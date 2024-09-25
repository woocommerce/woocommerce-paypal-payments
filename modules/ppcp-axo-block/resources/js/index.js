import { useState, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

// Hooks
import useFastlaneSdk from './hooks/useFastlaneSdk';
import useTokenizeCustomerData from './hooks/useTokenizeCustomerData';
import useCardChange from './hooks/useCardChange';
import useAxoSetup from './hooks/useAxoSetup';
import useAxoCleanup from './hooks/useAxoCleanup';
import useHandlePaymentSetup from './hooks/useHandlePaymentSetup';

// Components
import { Payment } from './components/Payment/Payment';
import usePaymentSetupEffect from './hooks/usePaymentSetupEffect';

const gatewayHandle = 'ppcp-axo-gateway';
const ppcpConfig = wc.wcSettings.getSetting( `${ gatewayHandle }_data` );

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const axoConfig = window.wc_ppcp_axo;

const Axo = ( props ) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;
	const [ shippingAddress, setShippingAddress ] = useState( null );
	const [ card, setCard ] = useState( null );
	const [ paymentComponent, setPaymentComponent ] = useState( null );

	const fastlaneSdk = useFastlaneSdk( axoConfig, ppcpConfig );
	const tokenizedCustomerData = useTokenizeCustomerData();
	const onChangeCardButtonClick = useCardChange( fastlaneSdk, setCard );
	const handlePaymentSetup = useHandlePaymentSetup(
		emitResponse,
		card,
		paymentComponent,
		tokenizedCustomerData
	);

	useAxoSetup(
		ppcpConfig,
		fastlaneSdk,
		paymentComponent,
		onChangeCardButtonClick,
		setShippingAddress,
		setCard
	);

	const { handlePaymentLoad } = usePaymentSetupEffect(
		onPaymentSetup,
		handlePaymentSetup,
		setPaymentComponent
	);

	useAxoCleanup();

	const handleCardChange = ( selectedCard ) => {
		console.log( 'Card selection changed', selectedCard );
		setCard( selectedCard );
	};

	console.log( 'Rendering Axo component', {
		fastlaneSdk,
		card,
		shippingAddress,
	} );

	return fastlaneSdk ? (
		<Payment
			fastlaneSdk={ fastlaneSdk }
			card={ card }
			onChange={ handleCardChange }
			onPaymentLoad={ handlePaymentLoad }
			onChangeButtonClick={ onChangeCardButtonClick }
		/>
	) : (
		<>{ __( 'Loading Fastlane…', 'woocommerce-paypal-payments' ) }</>
	);
};

registerPaymentMethod( {
	name: ppcpConfig.id,
	label: (
		<div
			id="ppcp-axo-block-radio-label"
			dangerouslySetInnerHTML={ { __html: ppcpConfig.title } }
		/>
	),
	content: <Axo />,
	edit: createElement( ppcpConfig.title ),
	ariaLabel: ppcpConfig.title,
	canMakePayment: () => true,
	supports: {
		showSavedCards: true,
		features: ppcpConfig.supports,
	},
} );

export default Axo;
