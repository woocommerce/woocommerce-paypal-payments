export const isChangePaymentPage = () => {
	const urlParams = new URLSearchParams( window.location.search );
	return urlParams.has( 'change_payment_method' );
};

export const getPlanIdFromVariation = ( variation ) => {
	let subscription_plan = '';
	PayPalCommerceGateway.variable_paypal_subscription_variations.forEach(
		( element ) => {
			const obj = {};
			variation.forEach( ( { name, value } ) => {
				Object.assign( obj, {
					[ name.replace( 'attribute_', '' ) ]: value,
				} );
			} );

			if (
				JSON.stringify( obj ) ===
					JSON.stringify( element.attributes ) &&
				element.subscription_plan !== ''
			) {
				subscription_plan = element.subscription_plan;
			}
		}
	);

	return subscription_plan;
};
