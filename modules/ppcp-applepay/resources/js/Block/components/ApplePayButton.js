import { useState, useEffect } from '@wordpress/element';
import useApiToGenerateButton from '@ppcp-applepay/Block/hooks/useApiToGenerateButton';
import usePayPalScript from '@ppcp-applepay/Block/hooks/usePayPalScript';
import useApplepayScript from '@ppcp-applepay/Block/hooks/useApplepayScript';
import useApplepayConfig from '@ppcp-applepay/Block/hooks/useApplepayConfig';

const ApplepayButton = ( {
	namespace,
	buttonConfig,
	ppcpConfig,
	buttonAttributes,
} ) => {
	const [ buttonHtml, setButtonHtml ] = useState( '' );
	const [ buttonElement, setButtonElement ] = useState( null );
	const [ componentFrame, setComponentFrame ] = useState( null );
	const isPayPalLoaded = usePayPalScript( namespace, ppcpConfig );

	const isApplepayLoaded = useApplepayScript(
		componentFrame,
		buttonConfig,
		isPayPalLoaded
	);

	const applepayConfig = useApplepayConfig( namespace, isApplepayLoaded );

	useEffect( () => {
		if ( ! buttonElement ) {
			return;
		}

		setComponentFrame( buttonElement.ownerDocument );
	}, [ buttonElement ] );

	const applepayButton = useApiToGenerateButton(
		componentFrame,
		namespace,
		buttonConfig,
		ppcpConfig,
		applepayConfig,
		buttonAttributes
	);

	useEffect( () => {
		if ( ! applepayButton || ! buttonElement ) {
			return;
		}

		setButtonHtml( applepayButton.outerHTML );

		// Add timeout to ensure button is displayed after render
		setTimeout( () => {
			const button = buttonElement.querySelector( 'apple-pay-button' );
			if ( button ) {
				button.style.display = 'block';
			}
		}, 100 ); // Add a small delay to ensure DOM is ready
	}, [ applepayButton, buttonElement ] );

	return (
		<div
			ref={ setButtonElement }
			dangerouslySetInnerHTML={ { __html: buttonHtml } }
			style={ {
				height: buttonAttributes?.height
					? `${ buttonAttributes.height }px`
					: '48px',
				'--apple-pay-button-height': buttonAttributes?.height
					? `${ buttonAttributes.height }px`
					: '48px',
				borderRadius: buttonAttributes?.borderRadius
					? `${ buttonAttributes.borderRadius }px`
					: undefined,
				overflow: 'hidden',
			} }
		/>
	);
};

export default ApplepayButton;
