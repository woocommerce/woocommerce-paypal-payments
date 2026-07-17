const mockGetProducts = jest.fn();

jest.mock(
	'@ppcp-button/ActionHandler/SingleProductActionHandler',
	() =>
		jest.fn().mockImplementation( () => ( {
			getProducts: mockGetProducts,
		} ) ),
	{ virtual: true }
);

jest.mock( '../utils/api', () => ( {
	postJson: jest.fn(),
} ) );

import {
	createOrder,
	approveOrder,
	fetchCartTotal,
	navigation,
} from '../endpointsAdapter';
import { postJson } from '../utils/api';

const config = {
	ajax: {
		change_cart: { endpoint: '/cc', nonce: 'n-cc' },
		create_order: { endpoint: '/co', nonce: 'n-co' },
		approve_order: { endpoint: '/ao', nonce: 'n-ao' },
		wc_store_api: { cart: '/wp-json/wc/store/v1/cart' },
	},
	urls: { checkout: '/checkout/' },
};

afterEach( () => {
	postJson.mockReset();
	mockGetProducts.mockReset();
	document.body.innerHTML = '';
} );

describe( 'createOrder', () => {
	test( 'product context adds the product to the cart first and forwards purchase units', async () => {
		document.body.innerHTML =
			'<form class="wc-block-add-to-cart-with-options">' +
			'<input name="add-to-cart" value="1006" /></form>';
		mockGetProducts.mockReturnValue( [
			{ data: () => ( { id: 1006, quantity: 1, variations: [] } ) },
		] );
		const purchaseUnits = [ { reference_id: 'default' } ];
		postJson
			.mockResolvedValueOnce( purchaseUnits )
			.mockResolvedValueOnce( { id: 'PAYPAL1' } );

		const result = await createOrder( config, 'product', 'paypal' );

		expect( result ).toEqual( { orderId: 'PAYPAL1' } );
		expect( postJson ).toHaveBeenNthCalledWith(
			1,
			config.ajax.change_cart,
			{
				products: [ { id: 1006, quantity: 1, variations: [] } ],
			}
		);
		expect( postJson ).toHaveBeenNthCalledWith(
			2,
			config.ajax.create_order,
			{
				context: 'product',
				purchase_units: purchaseUnits,
				payment_method: 'ppcp-gateway',
				funding_source: 'paypal',
				save_order_in_session: 1,
			}
		);
	} );

	test( 'cart context skips the change-cart step', async () => {
		postJson.mockResolvedValueOnce( { id: 'PAYPAL2' } );

		await createOrder( config, 'cart', 'venmo' );

		expect( postJson ).toHaveBeenCalledTimes( 1 );
		expect( postJson ).toHaveBeenCalledWith( config.ajax.create_order, {
			context: 'cart',
			purchase_units: [],
			payment_method: 'ppcp-gateway',
			funding_source: 'venmo',
			save_order_in_session: 1,
		} );
	} );

	test( 'product context fails clearly without a product form', async () => {
		await expect(
			createOrder( config, 'product', 'paypal' )
		).rejects.toThrow( 'Product form not found.' );
		expect( postJson ).not.toHaveBeenCalled();
	} );
} );

describe( 'approveOrder', () => {
	test( 'product context requests should_create_wc_order and continues on checkout without order_received_url', async () => {
		postJson.mockResolvedValueOnce( {} );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		await approveOrder( config, 'product', 'paypal', 'ORDER1' );

		expect( postJson ).toHaveBeenCalledWith( config.ajax.approve_order, {
			order_id: 'ORDER1',
			funding_source: 'paypal',
			should_create_wc_order: true,
		} );
		expect( assign ).toHaveBeenCalledWith( '/checkout/' );
	} );

	test( 'redirects to order_received_url when the server creates the WC order (Pay Now)', async () => {
		postJson.mockResolvedValueOnce( {
			order_received_url: '/checkout/order-received/123/?key=wc_abc',
		} );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		await approveOrder( config, 'product', 'paypal', 'ORDER1' );

		expect( assign ).toHaveBeenCalledWith(
			'/checkout/order-received/123/?key=wc_abc'
		);
	} );

	test( 'does not request a WC order for Venmo when vaulting is enabled', async () => {
		postJson.mockResolvedValueOnce( {} );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		await approveOrder(
			{ ...config, vaulting_enabled: true },
			'product',
			'venmo',
			'ORDER1'
		);

		expect( postJson ).toHaveBeenCalledWith( config.ajax.approve_order, {
			order_id: 'ORDER1',
			funding_source: 'venmo',
			should_create_wc_order: false,
		} );
		expect( assign ).toHaveBeenCalledWith( '/checkout/' );
	} );

	test( 'checkout context pins the PayPal gateway radio and submits the form', async () => {
		postJson.mockResolvedValueOnce( {} );
		document.body.innerHTML =
			'<form class="checkout">' +
			'<input type="radio" id="payment_method_ppcp-gateway" /></form>';
		const trigger = jest.fn();
		global.jQuery = jest.fn( ( selector ) =>
			typeof selector === 'string' ? { length: 1, trigger } : { trigger }
		);

		await approveOrder( config, 'checkout', 'paypal', 'ORDER2' );

		expect(
			document.querySelector( '#payment_method_ppcp-gateway' ).checked
		).toBe( true );
		expect( trigger ).toHaveBeenCalledWith( 'submit' );

		delete global.jQuery;
	} );
} );

describe( 'fetchCartTotal', () => {
	afterEach( () => {
		global.fetch = undefined;
	} );

	test( 'converts Store API minor units to a decimal string', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			json: async () => ( {
				totals: { total_price: '11000', currency_minor_unit: 2 },
			} ),
		} );

		await expect( fetchCartTotal( config ) ).resolves.toBe( '110.00' );
	} );

	test( 'returns an empty string on failure', async () => {
		global.fetch = jest.fn().mockRejectedValue( new Error( 'down' ) );

		await expect( fetchCartTotal( config ) ).resolves.toBe( '' );
	} );
} );
