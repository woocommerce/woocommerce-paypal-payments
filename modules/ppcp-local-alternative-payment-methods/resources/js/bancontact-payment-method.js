import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { Bancontact } from './bancontact-block';

const config = wc.wcSettings.getSetting( 'ppcp-bancontact_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <Bancontact config={ config } />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
