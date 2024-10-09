import { useEffect, useState } from '@wordpress/element';
import useButtonStyles from './useButtonStyles';

const useGooglepayApiToGenerateButton = (
	namespace,
	buttonConfig,
	ppcpConfig,
	googlepayConfig
) => {
	const [ googlepayButton, setGooglepayButton ] = useState( null );
	const buttonStyles = useButtonStyles( buttonConfig, ppcpConfig );

	useEffect( () => {
		if (
			! buttonConfig ||
			! googlepayConfig ||
			! window.google ||
			! window.google.payments ||
			! window.google.payments.api
		) {
			return;
		}

		const paymentsClient = new window.google.payments.api.PaymentsClient( {
			environment: 'TEST',
		} );

		console.log( 'paymentsClient', paymentsClient );

		console.log( 'googlepayConfig', googlepayConfig );
		console.log( 'buttonStyles?.Default', buttonStyles?.Default );

		const button = paymentsClient.createButton( {
			onClick: () => {
				console.log( 'Google Pay button clicked' );
			},
			allowedPaymentMethods: googlepayConfig.allowedPaymentMethods,
			buttonColor: buttonConfig.buttonColor || 'black',
			buttonType: buttonConfig.buttonType || 'pay',
			buttonLocale: buttonConfig.buttonLocale || 'en',
			buttonSizeMode: 'fill',
		} );

		console.log( {
			allowedPaymentMethods: googlepayConfig.allowedPaymentMethods,
			buttonColor: buttonConfig.buttonColor || 'black',
			buttonType: buttonConfig.buttonType || 'pay',
			buttonLocale: buttonConfig.buttonLocale || 'en',
			buttonSizeMode: 'fill',
		} );

		setGooglepayButton( button );

		return () => {
			setGooglepayButton( null );
		};
	}, [ namespace, buttonConfig, ppcpConfig, googlepayConfig, buttonStyles ] );

	return googlepayButton;
};

export default useGooglepayApiToGenerateButton;
