import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { Blik } from './blik-block';

const config = wc.wcSettings.getSetting( 'ppcp-blik_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <Blik config={ config } />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
