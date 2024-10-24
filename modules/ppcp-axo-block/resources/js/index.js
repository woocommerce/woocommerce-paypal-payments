import { useState, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

// Hooks
import useFastlaneSdk from './hooks/useFastlaneSdk';
import useTokenizeCustomerData from './hooks/useTokenizeCustomerData';
import useAxoSetup from './hooks/useAxoSetup';
import useAxoCleanup from './hooks/useAxoCleanup';
import useHandlePaymentSetup from './hooks/useHandlePaymentSetup';
import usePaymentSetupEffect from './hooks/usePaymentSetupEffect';
import usePayPalCommerceGateway from './hooks/usePayPalCommerceGateway';

// Components
import { Payment } from './components/Payment/Payment';
import { TitleLabel } from './components/TitleLabel';

const gatewayHandle = 'ppcp-axo-gateway';
const namespace = 'ppcpBlocksPaypalAxo';
const initialConfig = wc.wcSettings.getSetting( `${ gatewayHandle }_data` );
const Axo = ( props ) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;
	const [ paymentComponent, setPaymentComponent ] = useState( null );

	const { isConfigLoaded, ppcpConfig } =
		usePayPalCommerceGateway( initialConfig );

	const axoConfig = window.wc_ppcp_axo;

	const fastlaneSdk = useFastlaneSdk( namespace, axoConfig, ppcpConfig );
	const tokenizedCustomerData = useTokenizeCustomerData();
	const handlePaymentSetup = useHandlePaymentSetup(
		emitResponse,
		paymentComponent,
		tokenizedCustomerData
	);

	const isScriptLoaded = useAxoSetup(
		namespace,
		ppcpConfig,
		isConfigLoaded,
		fastlaneSdk,
		paymentComponent
	);

	const { handlePaymentLoad } = usePaymentSetupEffect(
		onPaymentSetup,
		handlePaymentSetup,
		setPaymentComponent
	);

	useAxoCleanup();

	if ( ! isConfigLoaded ) {
		return (
			<>
				{ __(
					'Loading configuration…',
					'woocommerce-paypal-payments'
				) }
			</>
		);
	}

	if ( ! isScriptLoaded ) {
		return (
			<>
				{ __(
					'Loading PayPal script…',
					'woocommerce-paypal-payments'
				) }
			</>
		);
	}

	if ( ! fastlaneSdk ) {
		return (
			<>{ __( 'Loading Fastlane…', 'woocommerce-paypal-payments' ) }</>
		);
	}

	return (
		<Payment
			fastlaneSdk={ fastlaneSdk }
			onPaymentLoad={ handlePaymentLoad }
		/>
	);
};

registerPaymentMethod( {
	name: initialConfig.id,
	label: <TitleLabel config={ initialConfig } />,
	content: <Axo />,
	edit: createElement( initialConfig.title ),
	ariaLabel: initialConfig.title,
	canMakePayment: () => true,
	supports: {
		showSavedCards: true,
		features: initialConfig.supports,
	},
} );

export default Axo;
