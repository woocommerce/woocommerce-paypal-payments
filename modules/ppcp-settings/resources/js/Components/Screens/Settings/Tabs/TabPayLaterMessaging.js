import { PayLaterMessagingHooks } from '../../../../data';
import { useEffect } from '@wordpress/element';

const TabPayLaterMessaging = () => {
	const {
		config,
		setCart,
		setCheckout,
		setProduct,
		setShop,
		setHome,
		setCustomPlacement,
	} = PayLaterMessagingHooks.usePayLaterMessaging();
	const PcpPayLaterConfigurator =
		window.ppcpSettings?.PcpPayLaterConfigurator;

	useEffect( () => {
		if ( window.merchantConfigurators && PcpPayLaterConfigurator ) {
			window.merchantConfigurators.Messaging( {
				config,
				merchantClientId: PcpPayLaterConfigurator.merchantClientId,
				partnerClientId: PcpPayLaterConfigurator.partnerClientId,
				partnerName: 'WooCommerce',
				bnCode: PcpPayLaterConfigurator.bnCode,
				placements: [
					'cart',
					'checkout',
					'product',
					'shop',
					'home',
					'custom_placement',
				],
				styleOverrides: {
					button: 'ppcp-r-paylater-configurator__publish-button',
					header: 'ppcp-r-paylater-configurator__header',
					subheader: 'ppcp-r-paylater-configurator__subheader',
				},
				onSave: ( data ) => {
					setCart( data.config.cart );
					setCheckout( data.config.checkout );
					setProduct( data.config.product );
					setShop( data.config.shop );
					setHome( data.config.home );
					setCustomPlacement( data.config.custom_placement );
				},
			} );
		}
	}, [
		PcpPayLaterConfigurator,
		config,
		setCart,
		setCheckout,
		setCustomPlacement,
		setHome,
		setProduct,
		setShop,
	] );

	return (
		<div
			id="messaging-configurator"
			className="ppcp-r-paylater-configurator"
		></div>
	);
};

export default TabPayLaterMessaging;
