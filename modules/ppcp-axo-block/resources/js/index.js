import { useCallback, useState } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

// Hooks
import useFastlaneSdk from './hooks/useFastlaneSdk';
import useCustomerData from './hooks/useCustomerData';
import useShippingAddressChange from './hooks/useShippingAddressChange';
import useCardChange from './hooks/useCardChange';
import useAxoSetup from './hooks/useAxoSetup';
import usePaymentSetup from './hooks/usePaymentSetup';
import useAxoCleanup from './hooks/useAxoCleanup';

// Components
import { Payment } from './components/Payment/Payment';

const ppcpConfig = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );
const axoConfig = window.wc_ppcp_axo;

const Axo = ( props ) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;
	const [ shippingAddress, setShippingAddress ] = useState( null );
	const [ card, setCard ] = useState( null );
	const fastlaneSdk = useFastlaneSdk( axoConfig, ppcpConfig );

	const {
		shippingAddress: wooShippingAddress,
		billingAddress: wooBillingAddress,
		setShippingAddress: updateWooShippingAddress,
		setBillingAddress: updateWooBillingAddress,
	} = useCustomerData();

	const onChangeShippingAddressClick = useShippingAddressChange(
		fastlaneSdk,
		setShippingAddress,
		updateWooShippingAddress
	);
	const onChangeCardButtonClick = useCardChange(
		fastlaneSdk,
		setCard,
		updateWooBillingAddress
	);

	useAxoSetup(
		ppcpConfig,
		fastlaneSdk,
		wooShippingAddress,
		wooBillingAddress,
		updateWooShippingAddress,
		updateWooBillingAddress,
		onChangeShippingAddressClick,
		onChangeCardButtonClick,
		setShippingAddress,
		setCard
	);
	usePaymentSetup( onPaymentSetup, emitResponse, card );
	useAxoCleanup( updateWooShippingAddress, updateWooBillingAddress );

	const handlePaymentLoad = useCallback( ( paymentComponent ) => {
		console.log( 'Payment component loaded', paymentComponent );
	}, [] );

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
