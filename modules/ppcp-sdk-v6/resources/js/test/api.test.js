import { postJson } from '../utils/api';

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
