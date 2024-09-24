import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
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

// Store
import { STORE_NAME } from './stores/axoStore';

const gatewayHandle = 'ppcp-axo-gateway';
const ppcpConfig = wc.wcSettings.getSetting( `${ gatewayHandle }_data` );

if ( typeof window.PayPalCommerceGateway === 'undefined' ) {
	window.PayPalCommerceGateway = ppcpConfig;
}

const axoConfig = window.wc_ppcp_axo;

const Axo = ( props ) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;
	const [ paymentComponent, setPaymentComponent ] = useState( null );

	const { cardDetails } = useSelect(
		( select ) => ( {
			cardDetails: select( STORE_NAME ).getCardDetails(),
		} ),
		[]
	);

	const fastlaneSdk = useFastlaneSdk( axoConfig, ppcpConfig );
	const tokenizedCustomerData = useTokenizeCustomerData();
	const onChangeCardButtonClick = useCardChange( fastlaneSdk );
	const handlePaymentSetup = useHandlePaymentSetup(
		emitResponse,
		paymentComponent,
		tokenizedCustomerData
	);

	useAxoSetup(
		ppcpConfig,
		fastlaneSdk,
		paymentComponent,
		onChangeCardButtonClick
	);

	const { handlePaymentLoad } = usePaymentSetupEffect(
		onPaymentSetup,
		handlePaymentSetup,
		setPaymentComponent
	);

	useAxoCleanup();

	console.log( 'Rendering Axo component', {
		fastlaneSdk,
	} );

	return fastlaneSdk ? (
		<Payment
			fastlaneSdk={ fastlaneSdk }
			card={ cardDetails }
			onChange={ onChangeCardButtonClick }
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
