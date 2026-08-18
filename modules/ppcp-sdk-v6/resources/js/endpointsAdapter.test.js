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
	simulateCart,
	navigation,
} from './endpointsAdapter';
import { postJson } from './utils/api';

const config = {
	ajax: {
		change_cart: { endpoint: '/cc', nonce: 'n-cc' },
		create_order: { endpoint: '/co', nonce: 'n-co' },
		approve_order: { endpoint: '/ao', nonce: 'n-ao' },
		simulate_cart: { endpoint: '/sc', nonce: 'n-sc' },
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
	jest.restoreAllMocks();
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

	test( 'product context with supplied purchase units skips change-cart', async () => {
		const purchaseUnits = [ { reference_id: 'wallet' } ];
		postJson.mockResolvedValueOnce( { id: 'PAYPAL4' } );

		const result = await createOrder(
			config,
			'product',
			'paypal',
			purchaseUnits
		);

		expect( result ).toEqual( { orderId: 'PAYPAL4' } );
		expect( postJson ).toHaveBeenCalledTimes( 1 );
		expect( postJson ).toHaveBeenCalledWith( config.ajax.create_order, {
			context: 'product',
			purchase_units: purchaseUnits,
			payment_method: 'ppcp-gateway',
			funding_source: 'paypal',
			save_order_in_session: 1,
		} );
	} );

	test(
		'sends a supplied paymentMethod as payment_method instead of ' +
			'the express default',
		async () => {
			const purchaseUnits = [ { reference_id: 'wallet' } ];
			postJson.mockResolvedValueOnce( { id: 'PAYPAL6' } );

			await createOrder(
				config,
				'cart',
				'googlepay',
				purchaseUnits,
				'ppcp-googlepay'
			);

			expect( postJson ).toHaveBeenCalledWith(
				config.ajax.create_order,
				expect.objectContaining( { payment_method: 'ppcp-googlepay' } )
			);
		}
	);

	test( 'product context with an explicit empty array of purchase units skips change-cart', async () => {
		// The only test guarding this: a supplied [] must count as resolved
		// rather than fall through to changeCart(), which a truthiness or
		// .length check would get wrong. A non-empty array cannot catch it.
		postJson.mockResolvedValueOnce( { id: 'PAYPAL5' } );

		await createOrder( config, 'product', 'paypal', [] );

		expect( postJson ).toHaveBeenCalledTimes( 1 );
		expect( postJson ).toHaveBeenCalledWith(
			config.ajax.create_order,
			expect.objectContaining( { purchase_units: [] } )
		);
	} );
} );

describe( 'simulateCart', () => {
	test( 'posts the viewed product and returns the simulated total, without calling change_cart', async () => {
		document.body.innerHTML =
			'<form class="wc-block-add-to-cart-with-options">' +
			'<input name="add-to-cart" value="1006" /></form>';
		mockGetProducts.mockReturnValue( [
			{ data: () => ( { id: 1006, quantity: 1, variations: [] } ) },
		] );
		postJson.mockResolvedValueOnce( {
			total: '110.00',
			currency_code: 'USD',
		} );

		const result = await simulateCart( config );

		expect( result ).toEqual( { total: '110.00', currency_code: 'USD' } );
		expect( postJson ).toHaveBeenCalledTimes( 1 );
		expect( postJson ).toHaveBeenCalledWith( config.ajax.simulate_cart, {
			products: [ { id: 1006, quantity: 1, variations: [] } ],
		} );
		expect( postJson ).not.toHaveBeenCalledWith(
			config.ajax.change_cart,
			expect.anything()
		);
	} );

	test( 'fails clearly without a product form, leaving the real cart untouched', async () => {
		await expect( simulateCart( config ) ).rejects.toThrow(
			'Product form not found.'
		);
		expect( postJson ).not.toHaveBeenCalled();
	} );
} );

describe( 'approveOrder', () => {
	beforeEach( () => {
		jest.spyOn( navigation, 'assign' ).mockImplementation( () => {} );
	} );

	test( 'product context requests should_create_wc_order and continues on checkout without order_received_url', async () => {
		postJson.mockResolvedValueOnce( {} );

		await approveOrder( config, 'product', 'paypal', 'ORDER1' );

		expect( postJson ).toHaveBeenCalledWith( config.ajax.approve_order, {
			order_id: 'ORDER1',
			funding_source: 'paypal',
			should_create_wc_order: true,
		} );
		expect( navigation.assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'/checkout/'
		);
		// Cache-busted so a cached checkout cannot drop the buyer back
		// into the express flow with an order already approved.
		expect( navigation.assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'ppcp-continuation-redirect='
		);
	} );

	test( 'redirects to order_received_url when the server creates the WC order (Pay Now)', async () => {
		postJson.mockResolvedValueOnce( {
			order_received_url: '/checkout/order-received/123/?key=wc_abc',
		} );

		await approveOrder( config, 'product', 'paypal', 'ORDER1' );

		expect( navigation.assign ).toHaveBeenCalledWith(
			'/checkout/order-received/123/?key=wc_abc'
		);
	} );

	test( 'falls back to the continuation approval when WC order creation fails', async () => {
		postJson
			.mockRejectedValueOnce(
				new Error( 'No shipping method has been selected.' )
			)
			.mockResolvedValueOnce( {} );

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
		expect( navigation.assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'/checkout/'
		);
		// Cache-busted so a cached checkout cannot drop the buyer back
		// into the express flow with an order already approved.
		expect( navigation.assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'ppcp-continuation-redirect='
		);
	} );

	test( 'does not request a WC order for Venmo when vaulting is enabled', async () => {
		postJson.mockResolvedValueOnce( {} );

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
		expect( navigation.assign.mock.calls[ 0 ][ 0 ] ).toContain(
			'/checkout/'
		);
		// Cache-busted so a cached checkout cannot drop the buyer back
		// into the express flow with an order already approved.
		expect( navigation.assign.mock.calls[ 0 ][ 0 ] ).toContain(
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

	test(
		'checkout context still switches an unrelated radio to PayPal ' +
			'on the express path, leaving the buyer with the express gateway',
		async () => {
			postJson.mockResolvedValueOnce( {} );
			document.body.innerHTML =
				'<form class="checkout">' +
				'<input type="radio" id="payment_method_ppcp-gateway" />' +
				'<input type="radio" id="payment_method_ppcp-googlepay" checked /></form>';
			const radioTrigger = jest.fn();
			const formTrigger = jest.fn();
			global.jQuery = jest.fn( ( selector ) =>
				typeof selector === 'string'
					? { length: 1, trigger: formTrigger }
					: { trigger: radioTrigger }
			);

			await approveOrder( config, 'checkout', 'paypal', 'ORDER2b' );

			expect(
				document.querySelector( '#payment_method_ppcp-gateway' ).checked
			).toBe( true );
			expect( radioTrigger ).toHaveBeenCalledWith( 'change' );
			expect( formTrigger ).toHaveBeenCalledWith( 'submit' );

			delete global.jQuery;
		}
	);

	test(
		"checkout context leaves the buyer's own selection unchanged " +
			'when the wallet is its own gateway row',
		async () => {
			postJson.mockResolvedValueOnce( {} );
			document.body.innerHTML =
				'<form class="checkout">' +
				'<input type="radio" id="payment_method_ppcp-googlepay" checked /></form>';
			const radioTrigger = jest.fn();
			const formTrigger = jest.fn();
			global.jQuery = jest.fn( ( selector ) =>
				typeof selector === 'string'
					? { length: 1, trigger: formTrigger }
					: { trigger: radioTrigger }
			);

			await approveOrder(
				config,
				'checkout',
				'googlepay',
				'ORDER3',
				{},
				'ppcp-googlepay'
			);

			expect(
				document.querySelector( '#payment_method_ppcp-googlepay' )
					.checked
			).toBe( true );
			expect( radioTrigger ).not.toHaveBeenCalled();
			expect( formTrigger ).toHaveBeenCalledWith( 'submit' );

			delete global.jQuery;
		}
	);

	describe( 'contact handling', () => {
		const contact = {
			payer: { email_address: 'a@b.com' },
			shippingAddress: { country_code: 'US' },
		};

		test( 'sends payer and shipping_address from the supplied contact', async () => {
			postJson.mockResolvedValueOnce( {} );

			await approveOrder(
				config,
				'product',
				'paypal',
				'ORDER1',
				contact
			);

			expect( postJson ).toHaveBeenCalledWith(
				config.ajax.approve_order,
				{
					order_id: 'ORDER1',
					funding_source: 'paypal',
					should_create_wc_order: true,
					payer: contact.payer,
					shipping_address: contact.shippingAddress,
				}
			);
		} );

		test.each( [
			[
				'only payer',
				{ payer: { email_address: 'a@b.com' } },
				{
					order_id: 'ORDER1',
					funding_source: 'paypal',
					should_create_wc_order: true,
					payer: { email_address: 'a@b.com' },
				},
			],
			[
				'only shipping_address',
				{ shippingAddress: { country_code: 'US' } },
				{
					order_id: 'ORDER1',
					funding_source: 'paypal',
					should_create_wc_order: true,
					shipping_address: { country_code: 'US' },
				},
			],
			[
				'neither',
				undefined,
				{
					order_id: 'ORDER1',
					funding_source: 'paypal',
					should_create_wc_order: true,
				},
			],
		] )(
			'sends %s from the supplied contact',
			async ( label, partialContact, expectedBody ) => {
				postJson.mockResolvedValueOnce( {} );

				await approveOrder(
					config,
					'product',
					'paypal',
					'ORDER1',
					partialContact
				);

				expect( postJson ).toHaveBeenCalledWith(
					config.ajax.approve_order,
					expectedBody
				);
			}
		);

		test( 'omits the contact from the fallback retry after WC order creation fails', async () => {
			postJson
				.mockRejectedValueOnce(
					new Error( 'No shipping method has been selected.' )
				)
				.mockResolvedValueOnce( {} );

			await approveOrder(
				config,
				'product',
				'paypal',
				'ORDER1',
				contact
			);

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
		} );

		test( 'omits the contact for Venmo when vaulting is enabled', async () => {
			postJson.mockResolvedValueOnce( {} );

			await approveOrder(
				{ ...config, vaulting_enabled: true },
				'product',
				'venmo',
				'ORDER1',
				contact
			);

			expect( postJson ).toHaveBeenCalledWith(
				config.ajax.approve_order,
				{
					order_id: 'ORDER1',
					funding_source: 'venmo',
					should_create_wc_order: false,
				}
			);
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
