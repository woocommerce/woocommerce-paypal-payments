import { registerPaymentMethod } from '@woocommerce/blocks-registry';

const config = wc.wcSettings.getSetting( 'ppcp-pwc_data' );

registerPaymentMethod( {
	name: config.id,
	label: (
		<>
			<div dangerouslySetInnerHTML={ { __html: config.title } } />
			{ config.icon && (
				<img
					className={ `wc-block-components-payment-method-icon wc-block-components-payment-method-icon--${ config.id }` }
					src={ config.icon }
					alt={ config.title }
				/>
			) }
		</>
	),
	content: (
		<>
			{ config.description && (
				<div
					dangerouslySetInnerHTML={ { __html: config.description } }
				/>
			) }
		</>
	),
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
