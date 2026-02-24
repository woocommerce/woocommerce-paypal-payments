import { registerPaymentMethod } from '@woocommerce/blocks-registry';

const config = wc.wcSettings.getSetting( 'ppcp-pwc_data' );

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: (
		<>
			{ config.description && (
				<div
					dangerouslySetInnerHTML={ { __html: config.description } }
				/>
			) }
			{ config.icon && (
				<div className="wc-block-components-payment-method-icons wc-block-components-payment-method-icons--align-right">
					<img
						className={ `wc-block-components-payment-method-icon wc-block-components-payment-method-icon--${ config.id }` }
						src={ config.icon }
						alt={ config.title }
					/>
				</div>
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
