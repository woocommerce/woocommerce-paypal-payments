import { useEffect, useState } from '@wordpress/element';
import useButtonStyles from './useButtonStyles';

const useGooglepayApiToGenerateButton = (
	componentDocument,
	namespace,
	buttonConfig,
	ppcpConfig,
	googlepayConfig
) => {
	const [ googlepayButton, setGooglepayButton ] = useState( null );
	const buttonStyles = useButtonStyles( buttonConfig, ppcpConfig );

	useEffect( () => {
		if (
			! componentDocument?.defaultView ||
			! buttonConfig ||
			! googlepayConfig
		) {
			return;
		}

		const api = componentDocument.defaultView.google?.payments?.api;
		if ( ! api ) {
			return;
		}

		const paymentsClient = new api.PaymentsClient( {
			environment: 'TEST',
		} );

		const googlePayButtonOptions = {
			allowedPaymentMethods: googlepayConfig.allowedPaymentMethods,
			buttonColor: buttonConfig.buttonColor || 'black',
			buttonType: buttonConfig.buttonType || 'pay',
			buttonLocale: buttonConfig.buttonLocale || 'en',
			buttonSizeMode: 'fill',
		};

		const button = paymentsClient.createButton( {
			...googlePayButtonOptions,
			onClick: ( event ) => {
				event.preventDefault();
			},
		} );

		setGooglepayButton( button );

		return () => {
			setGooglepayButton( null );
		};
	}, [ namespace, buttonConfig, ppcpConfig, googlepayConfig, buttonStyles ] );

	return googlepayButton;
};

export default useGooglepayApiToGenerateButton;
