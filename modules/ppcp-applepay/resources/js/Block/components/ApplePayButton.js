import { useState, useEffect } from '@wordpress/element';
import useApiToGenerateButton from '../hooks/useApiToGenerateButton';
import usePayPalScript from '../hooks/usePayPalScript';
import useApplepayScript from '../hooks/useApplepayScript';
import useApplepayConfig from '../hooks/useApplepayConfig';

const ApplepayButton = ( { namespace, buttonConfig, ppcpConfig } ) => {
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
		applepayConfig
	);

	useEffect( () => {
		if ( applepayButton ) {
			setButtonHtml( applepayButton.outerHTML );
		}
	}, [ applepayButton ] );

	return (
		<div
			ref={ setButtonElement }
			dangerouslySetInnerHTML={ { __html: buttonHtml } }
		/>
	);
};

export default ApplepayButton;
