import { render } from '@testing-library/react';
import { PayLaterMessagingHooks, CommonHooks } from '@ppcp-settings/data';
import TabPayLaterMessaging from './TabPayLaterMessaging';

jest.mock( '@ppcp-settings/data', () => ( {
	PayLaterMessagingHooks: {
		usePayLaterMessaging: jest.fn(),
		useStore: jest.fn(),
	},
	CommonHooks: {
		useMerchant: jest.fn(),
	},
} ) );

const emptyConfig = {
	cart: undefined,
	checkout: undefined,
	product: undefined,
	shop: undefined,
	home: undefined,
	custom_placement: undefined,
};

const textPlacement = ( placement, status ) => ( {
	placement,
	status,
	layout: 'text',
	'logo-type': 'inline',
	'logo-position': 'left',
	'text-color': 'black',
	'text-size': '12',
} );

const bannerPlacement = ( placement, status ) => ( {
	placement,
	status,
	layout: 'flex',
	color: 'blue',
	ratio: '8x1',
} );

const customPlacement = ( status ) => [
	{ status, message_reference: 'woocommerceBlock' },
];

const hydratedConfig = {
	cart: textPlacement( 'cart', 'enabled' ),
	checkout: textPlacement( 'checkout', 'disabled' ),
	product: textPlacement( 'product', 'enabled' ),
	shop: bannerPlacement( 'shop', 'disabled' ),
	home: bannerPlacement( 'home', 'disabled' ),
	custom_placement: customPlacement( 'disabled' ),
};

// What the configurator hands back under v6, where shop and home are withheld.
const savedV6Config = {
	cart: textPlacement( 'cart', 'disabled' ),
	checkout: textPlacement( 'checkout', 'disabled' ),
	product: textPlacement( 'product', 'enabled' ),
	custom_placement: customPlacement( 'disabled' ),
};

const savedV5Config = {
	...savedV6Config,
	shop: bannerPlacement( 'shop', 'enabled' ),
	home: bannerPlacement( 'home', 'disabled' ),
};

const setters = () => ( {
	setCart: jest.fn(),
	setCheckout: jest.fn(),
	setProduct: jest.fn(),
	setShop: jest.fn(),
	setHome: jest.fn(),
	setCustom_placement: jest.fn(),
} );

const mockPayLaterMessaging = ( config, overrides = {} ) => {
	const hooks = { config, ...setters(), ...overrides };
	PayLaterMessagingHooks.usePayLaterMessaging.mockReturnValue( hooks );
	return hooks;
};

describe( 'TabPayLaterMessaging', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		window.merchantConfigurators = { Messaging: jest.fn() };
		window.ppcpSettings = {
			PcpPayLaterConfigurator: {
				partnerClientId: 'partner-client-id',
				bnCode: 'bn-code',
				isSdkV6Active: false,
			},
		};
		CommonHooks.useMerchant.mockReturnValue( {
			clientId: 'merchant-client-id',
		} );
	} );

	afterEach( () => {
		delete window.merchantConfigurators;
		delete window.ppcpSettings;
	} );

	test( 'does not mount the configurator before the store is ready, even though the library and config are present', () => {
		mockPayLaterMessaging( emptyConfig );
		PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: false } );

		render( <TabPayLaterMessaging /> );

		expect( window.merchantConfigurators.Messaging ).not.toHaveBeenCalled();
	} );

	test( 'mounts the configurator with the hydrated config once the store becomes ready', () => {
		mockPayLaterMessaging( emptyConfig );
		PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: false } );

		const { rerender } = render( <TabPayLaterMessaging /> );

		expect( window.merchantConfigurators.Messaging ).not.toHaveBeenCalled();

		mockPayLaterMessaging( hydratedConfig );
		PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: true } );

		rerender( <TabPayLaterMessaging /> );

		expect( window.merchantConfigurators.Messaging ).toHaveBeenCalledTimes(
			1
		);
		expect(
			window.merchantConfigurators.Messaging.mock.calls[ 0 ][ 0 ].config
		).toEqual( hydratedConfig );
	} );

	test( 'withholds shop and home placements when the v6 SDK is active', () => {
		mockPayLaterMessaging( hydratedConfig );
		PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: true } );
		window.ppcpSettings.PcpPayLaterConfigurator.isSdkV6Active = true;

		render( <TabPayLaterMessaging /> );

		expect(
			window.merchantConfigurators.Messaging.mock.calls[ 0 ][ 0 ]
				.placements
		).toEqual( [ 'cart', 'checkout', 'product', 'custom_placement' ] );
	} );

	test( 'includes shop and home placements when the v6 SDK is inactive', () => {
		mockPayLaterMessaging( hydratedConfig );
		PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: true } );
		window.ppcpSettings.PcpPayLaterConfigurator.isSdkV6Active = false;

		render( <TabPayLaterMessaging /> );

		expect(
			window.merchantConfigurators.Messaging.mock.calls[ 0 ][ 0 ]
				.placements
		).toEqual( [
			'cart',
			'checkout',
			'product',
			'shop',
			'home',
			'custom_placement',
		] );
	} );

	describe( 'onSave', () => {
		test( 'skips shop and home setters when the saved config omits them (v6 shape)', () => {
			const hooks = mockPayLaterMessaging( hydratedConfig );
			PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: true } );

			render( <TabPayLaterMessaging /> );

			const { onSave } = window.merchantConfigurators.Messaging.mock
				.calls[ 0 ][ 0 ];
			onSave( { config: savedV6Config } );

			expect( hooks.setCart ).toHaveBeenCalledWith( savedV6Config.cart );
			expect( hooks.setCheckout ).toHaveBeenCalledWith(
				savedV6Config.checkout
			);
			expect( hooks.setProduct ).toHaveBeenCalledWith(
				savedV6Config.product
			);
			expect( hooks.setCustom_placement ).toHaveBeenCalledWith(
				savedV6Config.custom_placement
			);
			expect( hooks.setShop ).not.toHaveBeenCalled();
			expect( hooks.setHome ).not.toHaveBeenCalled();
		} );

		test( 'applies shop and home setters when the saved config includes them (v5 shape)', () => {
			const hooks = mockPayLaterMessaging( hydratedConfig );
			PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: true } );

			render( <TabPayLaterMessaging /> );

			const { onSave } = window.merchantConfigurators.Messaging.mock
				.calls[ 0 ][ 0 ];
			onSave( { config: savedV5Config } );

			expect( hooks.setShop ).toHaveBeenCalledWith( savedV5Config.shop );
			expect( hooks.setHome ).toHaveBeenCalledWith( savedV5Config.home );
		} );
	} );

	test( 'does not mount the configurator when the merchant configurators library is unavailable', () => {
		mockPayLaterMessaging( hydratedConfig );
		PayLaterMessagingHooks.useStore.mockReturnValue( { isReady: true } );
		const { Messaging } = window.merchantConfigurators;
		delete window.merchantConfigurators;

		const { container } = render( <TabPayLaterMessaging /> );

		expect( Messaging ).not.toHaveBeenCalled();
		expect(
			container.querySelector( '#messaging-configurator' )
		).not.toBeNull();
	} );
} );
