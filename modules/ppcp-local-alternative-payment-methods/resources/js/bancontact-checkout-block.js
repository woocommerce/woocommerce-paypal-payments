import { registerPaymentMethod } from '@woocommerce/blocks-registry';

const config = wc.wcSettings.getSetting( 'ppcp-bancontact_data' );
console.log( config );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <div>Hi there!</div>,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
