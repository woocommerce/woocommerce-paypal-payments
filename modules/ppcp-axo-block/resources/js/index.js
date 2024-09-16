import { useState } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

// Hooks
import useFastlaneSdk from './hooks/useFastlaneSdk';
import useTokenizeCustomerData from './hooks/useTokenizeCustomerData';
import useCardChange from './hooks/useCardChange';
import useAxoSetup from './hooks/useAxoSetup';
import usePaymentSetup from './hooks/usePaymentSetup';
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
		onChangeCardButtonClick,
		setShippingAddress,
		setCard
	);
	usePaymentSetup( onPaymentSetup, emitResponse, card );

	const { handlePaymentLoad } = usePaymentSetupEffect(
		onPaymentSetup,
		handlePaymentSetup
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
		<div>Loading Fastlane...</div>
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
	edit: <h1>This is Axo Blocks in the editor</h1>,
	ariaLabel: ppcpConfig.title,
	canMakePayment: () => true,
	supports: {
		showSavedCards: true,
		features: ppcpConfig.supports,
	},
} );

export default Axo;
