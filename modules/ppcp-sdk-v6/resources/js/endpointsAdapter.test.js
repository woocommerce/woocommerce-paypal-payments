const mockGetProducts = jest.fn();

jest.mock(
	'@ppcp-button/ActionHandler/SingleProductActionHandler',
	() =>
		jest.fn().mockImplementation( () => ( {
			getProducts: mockGetProducts,
		} ) ),
	{ virtual: true }
);

const mockPayerData = jest.fn( () => null );

jest.mock(
	'@ppcp-button/Helper/PayerData',
	() => ( {
		payerData: () => mockPayerData(),
	} ),
	{ virtual: true }
);

jest.mock( './utils/api', () => ( {
	postJson: jest.fn(),
} ) );

import {
	createOrder,
	approveOrder,
	createCardOrder,
	approveCardOrder,
	fetchCartTotal,
	navigation,
} from './endpointsAdapter';
import { postJson } from './utils/api';

const config = {
	ajax: {
		change_cart: { endpoint: '/cc', nonce: 'n-cc' },
		create_order: { endpoint: '/co', nonce: 'n-co' },
		approve_order: { endpoint: '/ao', nonce: 'n-ao' },
		wc_store_api: { cart: '/wp-json/wc/store/v1/cart' },
	},
	urls: { checkout: '/checkout/' },
	card_fields: {
		payment_method: 'ppcp-credit-card-gateway',
		funding_source: 'card',
	},
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

	test( 'checkout context serializes the form for early validation and sends the payer', async () => {
		document.body.innerHTML =
			'<form class="checkout">' +
			'<input name="billing_email" value="a@b.com" />' +
			'<input type="checkbox" id="createaccount" name="createaccount" checked /></form>';
		mockPayerData.mockReturnValueOnce( {
			email_address: 'a@b.com',
		} );
		postJson.mockResolvedValueOnce( { id: 'PAYPAL3' } );

		await createOrder( config, 'checkout', 'paypal' );

		expect( postJson ).toHaveBeenCalledWith( config.ajax.create_order, {
			context: 'checkout',
			purchase_units: [],
			payment_method: 'ppcp-gateway',
			funding_source: 'paypal',
			save_order_in_session: 1,
			form_encoded: 'billing_email=a%40b.com&createaccount=on',
			createaccount: true,
			payer: { email_address: 'a@b.com' },
		} );
	} );

	test( 'product context fails clearly without a product form', async () => {
		await expect(
			createOrder( config, 'product', 'paypal' )
		).rejects.toThrow( 'Product form not found.' );
		expect( postJson ).not.toHaveBeenCalled();
	} );

	test( 'pay-now context identifies the existing WC order to build from', async () => {
		postJson.mockResolvedValueOnce( { id: 'PAYPAL4' } );

		await createOrder(
			{ ...config, pay_now: { order_id: 123, order_key: 'wc_abc' } },
			'pay-now',
			'paypal'
		);

		expect( postJson ).toHaveBeenCalledWith( config.ajax.create_order, {
			context: 'pay-now',
			purchase_units: [],
			payment_method: 'ppcp-gateway',
			funding_source: 'paypal',
			save_order_in_session: 1,
			order_id: 123,
			order_key: 'wc_abc',
		} );
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
		expect( assign.mock.calls[ 0 ][ 0 ] ).toContain( '/checkout/' );
		// Cache-busted so a cached checkout cannot drop the buyer back
		// into the express flow with an order already approved.
		expect( assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'ppcp-continuation-redirect='
		);
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

	test( 'falls back to the continuation approval when WC order creation fails', async () => {
		postJson
			.mockRejectedValueOnce(
				new Error( 'No shipping method has been selected.' )
			)
			.mockResolvedValueOnce( {} );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		await approveOrder( config, 'product', 'paypal', 'ORDER1' );

		expect( postJson ).toHaveBeenCalledTimes( 2 );
		expect( postJson ).toHaveBeenNthCalledWith(
			2,
			config.ajax.approve_order,
			{
				order_id: 'ORDER1',
				funding_source: 'paypal',
				should_create_wc_order: false,
			}
		);
		expect( assign.mock.calls[ 0 ][ 0 ] ).toContain( '/checkout/' );
		// Cache-busted so a cached checkout cannot drop the buyer back
		// into the express flow with an order already approved.
		expect( assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'ppcp-continuation-redirect='
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
		expect( assign.mock.calls[ 0 ][ 0 ] ).toContain( '/checkout/' );
		// Cache-busted so a cached checkout cannot drop the buyer back
		// into the express flow with an order already approved.
		expect( assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'ppcp-continuation-redirect='
		);
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

	describe( 'pay-now context', () => {
		test( 'approves the order in the session and submits the pay-order form, without creating a WC order', async () => {
			postJson.mockResolvedValueOnce( {} );
			document.body.innerHTML =
				'<form id="order_review">' +
				'<input type="radio" id="payment_method_ppcp-gateway" /></form>';
			const trigger = jest.fn();
			global.jQuery = jest.fn( ( selector ) =>
				typeof selector === 'string'
					? { length: 1, trigger }
					: { trigger }
			);

			await approveOrder( config, 'pay-now', 'paypal', 'ORDER3' );

			expect( postJson ).toHaveBeenCalledTimes( 1 );
			expect( postJson ).toHaveBeenCalledWith( config.ajax.approve_order, {
				order_id: 'ORDER3',
				funding_source: 'paypal',
				should_create_wc_order: false,
			} );
			expect(
				document.querySelector( '#payment_method_ppcp-gateway' ).checked
			).toBe( true );
			expect( trigger ).toHaveBeenCalledWith( 'submit' );

			delete global.jQuery;
		} );

		test( 'throws instead of falling through to the classic continuation when the pay-order form is missing', async () => {
			postJson.mockResolvedValueOnce( {} );
			document.body.innerHTML = '';
			global.jQuery = jest.fn( () => ( { length: 0 } ) );

			await expect(
				approveOrder( config, 'pay-now', 'paypal', 'ORDER3' )
			).rejects.toThrow( 'Order form not found.' );

			delete global.jQuery;
		} );
	} );
} );

describe( 'createCardOrder', () => {
	test( 'sends the card payment method/funding source and never sets save_order_in_session', async () => {
		document.body.innerHTML =
			'<form class="checkout">' +
			'<input name="billing_email" value="a@b.com" /></form>';
		mockPayerData.mockReturnValueOnce( null );
		postJson.mockResolvedValueOnce( { id: 'CARDORDER1' } );

		const result = await createCardOrder( config );

		expect( result ).toEqual( { orderId: 'CARDORDER1' } );
		expect( postJson ).toHaveBeenCalledWith( config.ajax.create_order, {
			context: 'checkout',
			purchase_units: [],
			payment_method: 'ppcp-credit-card-gateway',
			funding_source: 'card',
			form_encoded: 'billing_email=a%40b.com',
			createaccount: false,
		} );
	} );

	test( 'forwards the payer when available', async () => {
		document.body.innerHTML = '<form class="checkout"></form>';
		mockPayerData.mockReturnValueOnce( { email_address: 'a@b.com' } );
		postJson.mockResolvedValueOnce( { id: 'CARDORDER2' } );

		await createCardOrder( config );

		expect( postJson ).toHaveBeenCalledWith(
			config.ajax.create_order,
			expect.objectContaining( { payer: { email_address: 'a@b.com' } } )
		);
	} );

	test( 'defaults to the checkout context when none is passed', async () => {
		document.body.innerHTML = '<form class="checkout"></form>';
		mockPayerData.mockReturnValueOnce( null );
		postJson.mockResolvedValueOnce( { id: 'CARDORDER3' } );

		await createCardOrder( config );

		expect( postJson ).toHaveBeenCalledWith(
			config.ajax.create_order,
			expect.objectContaining( { context: 'checkout' } )
		);
	} );

	test( 'adds the cardholder name to the body when provided', async () => {
		document.body.innerHTML = '<form class="checkout"></form>';
		mockPayerData.mockReturnValueOnce( null );
		postJson.mockResolvedValueOnce( { id: 'CARDORDER5' } );

		await createCardOrder( config, 'checkout', 'Jane Doe' );

		expect( postJson ).toHaveBeenCalledWith(
			config.ajax.create_order,
			expect.objectContaining( { card_name: 'Jane Doe' } )
		);
	} );

	test( 'omits the cardholder name from the body when not provided', async () => {
		document.body.innerHTML = '<form class="checkout"></form>';
		mockPayerData.mockReturnValueOnce( null );
		postJson.mockResolvedValueOnce( { id: 'CARDORDER6' } );

		await createCardOrder( config );

		expect( postJson ).toHaveBeenCalledWith(
			config.ajax.create_order,
			expect.not.objectContaining( { card_name: expect.anything() } )
		);
	} );

	test( 'pay-now context identifies the existing WC order to build from', async () => {
		postJson.mockResolvedValueOnce( { id: 'CARDORDER7' } );

		await createCardOrder(
			{ ...config, pay_now: { order_id: 456, order_key: 'wc_def' } },
			'pay-now'
		);

		expect( postJson ).toHaveBeenCalledWith( config.ajax.create_order, {
			context: 'pay-now',
			purchase_units: [],
			payment_method: 'ppcp-credit-card-gateway',
			funding_source: 'card',
			order_id: 456,
			order_key: 'wc_def',
		} );
	} );

	test( 'the checkout-block context sends no classic-form data even with a checkout form present', async () => {
		document.body.innerHTML =
			'<form class="checkout">' +
			'<input name="billing_email" value="a@b.com" />' +
			'<input type="checkbox" id="createaccount" name="createaccount" checked /></form>';
		mockPayerData.mockReturnValueOnce( { email_address: 'a@b.com' } );
		postJson.mockResolvedValueOnce( { id: 'CARDORDER4' } );

		const result = await createCardOrder( config, 'checkout-block' );

		expect( result ).toEqual( { orderId: 'CARDORDER4' } );
		expect( postJson ).toHaveBeenCalledWith( config.ajax.create_order, {
			context: 'checkout-block',
			purchase_units: [],
			payment_method: 'ppcp-credit-card-gateway',
			funding_source: 'card',
		} );
	} );
} );

describe( 'approveCardOrder', () => {
	test( 'posts the order id and card funding source', async () => {
		postJson.mockResolvedValueOnce( {} );

		await approveCardOrder( config, 'CARDORDER1' );

		expect( postJson ).toHaveBeenCalledWith( config.ajax.approve_order, {
			order_id: 'CARDORDER1',
			funding_source: 'card',
		} );
	} );

	test( 'propagates a rejected (declined/disabled-card/3DS) approval', async () => {
		postJson.mockRejectedValueOnce(
			new Error( 'Unfortunately, we do not accept this card.' )
		);

		await expect(
			approveCardOrder( config, 'CARDORDER1' )
		).rejects.toThrow( 'Unfortunately, we do not accept this card.' );
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
