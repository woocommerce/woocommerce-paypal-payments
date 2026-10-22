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
	const { isReady } = PayLaterMessagingHooks.useStore();
	const { clientId: merchantClientId } = CommonHooks.useMerchant();
	const PcpPayLaterConfigurator =
		window.ppcpSettings?.PcpPayLaterConfigurator;

	useEffect( () => {
		/*
		 * Wait for the stored config: the configurator seeds its checkbox state
		 * from the config it first receives and never re-seeds it, so mounting
		 * it before the REST hydration lands leaves it showing PayPal's
		 * defaults. Saving from there writes those defaults over the merchant's
		 * own locations.
		 */
		if (
			isReady &&
			window.merchantConfigurators &&
			PcpPayLaterConfigurator
		) {
			// Shop and home are banner-only and v6 serves neither, so a
			// configuration here would render nothing.
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

					// Writing undefined would discard a v5 store's stored config.
					if ( data.config.shop ) {
						setShop( data.config.shop );
					}
					if ( data.config.home ) {
						setHome( data.config.home );
					}
				},
			} );
		}
	}, [ isReady, PcpPayLaterConfigurator, config, merchantClientId ] );

	return (
		<div
			id="messaging-configurator"
			className="ppcp-r-paylater-configurator"
		></div>
	);
};

export default TabPayLaterMessaging;
