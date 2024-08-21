import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { MyBank } from './mybank-block';

const config = wc.wcSettings.getSetting( 'ppcp-mybank_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <MyBank config={ config } />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
