import GooglepayButton from './Block/components/GooglepayButton';

const GooglepayManagerBlockEditor = ( {
	namespace,
	buttonConfig,
	ppcpConfig,
} ) => (
	<GooglepayButton
		namespace={ namespace }
		buttonConfig={ buttonConfig }
		ppcpConfig={ ppcpConfig }
	/>
);

export default GooglepayManagerBlockEditor;
