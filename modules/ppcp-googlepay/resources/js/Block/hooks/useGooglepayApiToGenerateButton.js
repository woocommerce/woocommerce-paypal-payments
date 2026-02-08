import { useEffect, useState } from '@wordpress/element';
import useButtonStyles from './useButtonStyles';

const useGooglepayApiToGenerateButton = (
	componentDocument,
	namespace,
	buttonConfig,
	ppcpConfig,
	googlepayConfig,
	buttonAttributes
) => {
	const [ googlepayButton, setGooglepayButton ] = useState( null );

	const buttonStyles = useButtonStyles(
		buttonConfig,
		ppcpConfig,
		buttonAttributes
	);

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
			buttonRadius: parseInt( buttonStyles?.Default?.borderRadius ),
			onClick: ( event ) => {
				event.preventDefault();
			},
		};

		const button = paymentsClient.createButton( googlePayButtonOptions );

		setGooglepayButton( button );

		return () => {
			setGooglepayButton( null );
		};
	}, [
		componentDocument,
		namespace,
		buttonConfig,
		ppcpConfig,
		googlepayConfig,
		buttonStyles,
	] );

	// Return both the button and the styles needed for the container
	return {
		button: googlepayButton,
		containerStyles: {
			height: buttonStyles?.Default?.height
				? `${ buttonStyles.Default.height }px`
				: '',
		},
	};
};

export default useGooglepayApiToGenerateButton;
