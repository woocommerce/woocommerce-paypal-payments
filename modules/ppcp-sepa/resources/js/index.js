import { SEPA } from './SEPA';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const config = wc.wcSettings.getSetting( 'ppcp-sepa_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <SEPA />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
