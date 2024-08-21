export function MyBank( { config, components } ) {
	const { PaymentMethodIcons } = components;

	return (
		<div>
			<PaymentMethodIcons icons={ [ config.icon ] } align="right" />
		</div>
	);
}
