import ApplepayButton from './Block/components/ApplePayButton';

const ApplePayManagerBlockEditor = ( {
	namespace,
	buttonConfig,
	ppcpConfig,
} ) => (
	<ApplepayButton
		namespace={ namespace }
		buttonConfig={ buttonConfig }
		ppcpConfig={ ppcpConfig }
	/>
);

export default ApplePayManagerBlockEditor;
