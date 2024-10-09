import { useState, useEffect } from '@wordpress/element';
import useGooglepayApiToGenerateButton from '../hooks/useGooglepayApiToGenerateButton';

const GooglepayButton = ( {
	namespace,
	buttonConfig,
	ppcpConfig,
	googlepayConfig,
} ) => {
	const [ buttonHtml, setButtonHtml ] = useState( '' );
	const googlepayButton = useGooglepayApiToGenerateButton(
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

	if ( ! buttonHtml ) {
		return null;
	}

	return <div dangerouslySetInnerHTML={ { __html: buttonHtml } } />;
};

export default GooglepayButton;
