export const PayPalPlaceOrderContent = ( {
	description,
	placeOrderButtonDescription,
} ) => {
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
