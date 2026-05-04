import { useEffect } from '@wordpress/element';

export const PayPalPlaceOrderContent = ( {
	description,
	placeOrderButtonDescription,
	eventRegistration,
	emitResponse,
} ) => {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;

	useEffect(
		() =>
			onPaymentSetup( () => {
				return { type: responseTypes.SUCCESS };
			} ),
		[ onPaymentSetup, responseTypes ]
	);

	if ( placeOrderButtonDescription ) {
		return (
			<div>
				<p dangerouslySetInnerHTML={ { __html: description } } />
				<p
					style={ { textAlign: 'center' } }
					className="ppcp-place-order-description"
					dangerouslySetInnerHTML={ {
						__html: placeOrderButtonDescription,
					} }
				/>
			</div>
		);
	}
	return <div dangerouslySetInnerHTML={ { __html: description } } />;
};
