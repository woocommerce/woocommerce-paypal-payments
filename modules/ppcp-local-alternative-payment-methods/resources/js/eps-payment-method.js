import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { EPS } from './eps-block';

const config = wc.wcSettings.getSetting( 'ppcp-eps_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <EPS config={ config } />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
