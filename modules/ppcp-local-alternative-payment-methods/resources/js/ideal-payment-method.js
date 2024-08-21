import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { IDeal } from './ideal-block';

const config = wc.wcSettings.getSetting( 'ppcp-ideal_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <IDeal config={ config } />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
