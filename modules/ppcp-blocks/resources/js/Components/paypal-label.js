export const PaypalLabel = ( { components, config } ) => {
	const { PaymentMethodIcons } = components;

	return (
		<>
			<span
				dangerouslySetInnerHTML={ {
					__html: config.title,
				} }
			/>
			<PaymentMethodIcons icons={ config.icon } align="right" />
		</>
	);
};
