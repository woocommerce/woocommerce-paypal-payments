import GooglepayButton from './Block/components/GooglepayButton';
import usePayPalScript from './Block/hooks/usePayPalScript';
import useGooglepayScript from './Block/hooks/useGooglepayScript';
import useGooglepayConfig from './Block/hooks/useGooglepayConfig';

const GooglepayManagerBlockEditor = ( {
	namespace,
	buttonConfig,
	ppcpConfig,
} ) => {
	const isPayPalLoaded = usePayPalScript( namespace, ppcpConfig );
	const isGooglepayLoaded = useGooglepayScript(
		buttonConfig,
		isPayPalLoaded
	);
	const googlepayConfig = useGooglepayConfig( namespace, isGooglepayLoaded );

	if ( ! googlepayConfig ) {
		return <div>Loading Google Pay...</div>; // Or any other loading indicator
	}

	return (
		<GooglepayButton
			namespace={ namespace }
			buttonConfig={ buttonConfig }
			ppcpConfig={ ppcpConfig }
			googlepayConfig={ googlepayConfig }
		/>
	);
};

export default GooglepayManagerBlockEditor;
