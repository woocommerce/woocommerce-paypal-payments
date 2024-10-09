import { useState, useEffect } from '@wordpress/element';
import useGooglepayApiToGenerateButton from '../hooks/useGooglepayApiToGenerateButton';
import usePayPalScript from '../hooks/usePayPalScript';
import useGooglepayScript from '../hooks/useGooglepayScript';
import useGooglepayConfig from '../hooks/useGooglepayConfig';

const GooglepayButton = ( { namespace, buttonConfig, ppcpConfig } ) => {
	const [ buttonHtml, setButtonHtml ] = useState( '' );
	const [ buttonElement, setButtonElement ] = useState( null );
	const [ componentFrame, setComponentFrame ] = useState( null );

	const buttonLoader = '<div>Loading Google Pay...</div>';

	const isPayPalLoaded = usePayPalScript( namespace, ppcpConfig );

	const isGooglepayLoaded = useGooglepayScript(
		componentFrame,
		buttonConfig,
		isPayPalLoaded
	);

	const googlepayConfig = useGooglepayConfig( namespace, isGooglepayLoaded );

	useEffect( () => {
		if ( ! buttonElement ) {
			return;
		}

		setComponentFrame( buttonElement.ownerDocument );
	}, [ buttonElement ] );

	const googlepayButton = useGooglepayApiToGenerateButton(
		componentFrame,
		namespace,
		buttonConfig,
		ppcpConfig,
		googlepayConfig
	);

	useEffect( () => {
		if ( googlepayButton ) {
			setButtonHtml( googlepayButton.outerHTML );
		}
	}, [ googlepayButton ] );

	return (
		<div
			ref={ setButtonElement }
			dangerouslySetInnerHTML={ { __html: buttonHtml || buttonLoader } }
		/>
	);
};

export default GooglepayButton;
