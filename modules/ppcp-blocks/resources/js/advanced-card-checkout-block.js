import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { CardFields } from './Components/card-fields';

const config = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <CardFields config={ config } />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		showSavedCards: true,
		features: config.supports,
	},
} );
