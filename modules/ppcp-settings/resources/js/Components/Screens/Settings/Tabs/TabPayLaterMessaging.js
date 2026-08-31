import { PayLaterMessagingHooks, CommonHooks } from '@ppcp-settings/data';
import { useEffect } from '@wordpress/element';

const TabPayLaterMessaging = () => {
	const {
		config,
		setCart,
		setCheckout,
		setProduct,
		setShop,
		setHome,
		setCustom_placement,
	} = PayLaterMessagingHooks.usePayLaterMessaging();
	const { clientId: merchantClientId } = CommonHooks.useMerchant();
	const PcpPayLaterConfigurator =
		window.ppcpSettings?.PcpPayLaterConfigurator;

	useEffect( () => {
		if ( window.merchantConfigurators && PcpPayLaterConfigurator ) {
			// The shop and home placements are offered as banners only, which
			// the v6 messaging component cannot render — and v6 serves neither
			// location at all, so a configuration made here would produce
			// nothing on the storefront. Withhold them rather than collect it.
			const isSdkV6Active = !! PcpPayLaterConfigurator.isSdkV6Active;
			const placements = [ 'cart', 'checkout', 'product' ];

			if ( ! isSdkV6Active ) {
				placements.push( 'shop', 'home' );
			}

			placements.push( 'custom_placement' );

			window.merchantConfigurators.Messaging( {
				config,
				merchantClientId,
				partnerClientId: PcpPayLaterConfigurator.partnerClientId,
				partnerName: 'WooCommerce',
				bnCode: PcpPayLaterConfigurator.bnCode,
				placements,
				styleOverrides: {
					button: 'ppcp-r-paylater-configurator__publish-button',
					header: 'ppcp-r-paylater-configurator__header',
					subheader: 'ppcp-r-paylater-configurator__subheader',
				},
				onSave: ( data ) => {
					setCart( data.config.cart );
					setCheckout( data.config.checkout );
					setProduct( data.config.product );
					setCustom_placement( data.config.custom_placement );

					// Only when they were offered: a withheld placement has no
					// value to save, and writing undefined would discard the
					// stored configuration a v5 store may still be using.
					if ( data.config.shop ) {
						setShop( data.config.shop );
					}
					if ( data.config.home ) {
						setHome( data.config.home );
					}
				},
			} );
		}
	}, [ PcpPayLaterConfigurator, config, merchantClientId ] );

	return (
		<div
			id="messaging-configurator"
			className="ppcp-r-paylater-configurator"
		></div>
	);
};

export default TabPayLaterMessaging;
