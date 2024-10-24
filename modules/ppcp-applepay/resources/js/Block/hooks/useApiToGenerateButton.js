import { useEffect, useState } from '@wordpress/element';
import useButtonStyles from './useButtonStyles';

const useApiToGenerateButton = (
	componentDocument,
	namespace,
	buttonConfig,
	ppcpConfig,
	applepayConfig
) => {
	const [ applepayButton, setApplepayButton ] = useState( null );
	const buttonStyles = useButtonStyles( buttonConfig, ppcpConfig );

	useEffect( () => {
		if ( ! buttonConfig || ! applepayConfig ) {
			return;
		}

		const button = document.createElement( 'apple-pay-button' );
		button.setAttribute(
			'buttonstyle',
			buttonConfig.buttonColor || 'black'
		);
		button.setAttribute( 'type', buttonConfig.buttonType || 'pay' );
		button.setAttribute( 'locale', buttonConfig.buttonLocale || 'en' );

		setApplepayButton( button );

		return () => {
			setApplepayButton( null );
		};
	}, [ namespace, buttonConfig, ppcpConfig, applepayConfig, buttonStyles ] );

	return applepayButton;
};

export default useApiToGenerateButton;
