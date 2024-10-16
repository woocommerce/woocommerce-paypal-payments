import CardChangeButton from './../Card/CardChangeButton';

/**
 * TitleLabel component for displaying a payment method title with icons and a change card button.
 *
 * @param {Object} props            - Component props
 * @param {Object} props.components - Object containing WooCommerce components
 * @param {Object} props.config     - Configuration object for the payment method
 * @return {JSX.Element} WordPress element
 */
const TitleLabel = ( { components, config } ) => {
	const axoConfig = window.wc_ppcp_axo;
	const { PaymentMethodIcons } = components;

	return (
		<>
			<span dangerouslySetInnerHTML={ { __html: config.title } } />
			<PaymentMethodIcons icons={ axoConfig?.card_icons } />
			<CardChangeButton />
		</>
	);
};

export default TitleLabel;
