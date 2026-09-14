import { postJson, postStoreApi } from '../utils/api';

describe( 'postJson', () => {
	afterEach( () => {
		global.fetch = undefined;
	} );

	test( 'posts the nonce merged with the body and returns data', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			json: async () => ( { success: true, data: { id: 'ABC' } } ),
		} );

		const data = await postJson(
			{ endpoint: '/e', nonce: 'n1' },
			{ context: 'cart' }
		);

		expect( data ).toEqual( { id: 'ABC' } );
		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/e' );
		expect( JSON.parse( options.body ) ).toEqual( {
			nonce: 'n1',
			context: 'cart',
		} );
		expect( options.headers[ 'Content-Type' ] ).toBe( 'application/json' );
	} );

	test( 'marks server-provided messages as user facing', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			json: async () => ( {
				success: false,
				data: { message: 'Übersetzter Fehler' },
			} ),
		} );

		await expect(
			postJson( { endpoint: '/e', nonce: 'n' } )
		).rejects.toMatchObject( {
			message: 'Übersetzter Fehler',
			isUserFacing: true,
		} );
	} );

	test( 'does not mark message-less failures as user facing', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			json: async () => ( { success: false } ),
		} );

		await expect(
			postJson( { endpoint: '/e', nonce: 'n' } )
		).rejects.toMatchObject( {
			isUserFacing: false,
		} );
	} );
} );

describe( 'postStoreApi', () => {
	const storeApi = { nonce: 'store-nonce' };

	afterEach( () => {
		global.fetch = undefined;
	} );

	test( 'authenticates with the Store API nonce header and returns the parsed cart', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( { totals: { total_price: '1000' } } ),
		} );

		const cart = await postStoreApi( storeApi, '/cart/update-customer', {
			shipping_address: { country: 'US' },
		} );

		expect( cart ).toEqual( { totals: { total_price: '1000' } } );
		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/cart/update-customer' );
		expect( options.headers.Nonce ).toBe( 'store-nonce' );
		expect( JSON.parse( options.body ) ).toEqual( {
			shipping_address: { country: 'US' },
		} );
	} );

	test( 'throws a user-facing error built from the Store API code and message', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( {
				code: 'rate_not_found',
				message: 'No shipping options were found.',
			} ),
		} );

		await expect(
			postStoreApi( storeApi, '/cart/select-shipping-rate', {} )
		).rejects.toMatchObject( {
			message: 'No shipping options were found.',
			isUserFacing: true,
			code: 'rate_not_found',
		} );
	} );

	test( 'falls back to a generic message when the failed response carries none', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( {} ),
		} );

		await expect(
			postStoreApi( storeApi, '/cart/update-customer', {} )
		).rejects.toMatchObject( {
			message: 'Store API request failed.',
			isUserFacing: false,
		} );
	} );

	test( 'still throws when the failed response body is not JSON', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => {
				throw new Error( 'not json' );
			},
		} );

		await expect(
			postStoreApi( storeApi, '/cart/update-customer', {} )
		).rejects.toMatchObject( {
			message: 'Store API request failed.',
		} );
	} );
} );
