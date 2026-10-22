import { createSdkInstance } from './createSdkInstance';

const sdkError = ( code ) => Object.assign( new Error( code ), { code } );
const unsupportedLocale = () => sdkError( 'ERR_DEV_UNSUPPORTED_LOCALE' );

const baseOptions = ( overrides = {} ) => ( {
	clientId: 'client-id',
	components: [ 'paypal-payments' ],
	...overrides,
} );

describe( 'createSdkInstance()', () => {
	test( 'creates the instance once with a supported locale', async () => {
		const createInstance = jest.fn().mockResolvedValue( { id: 1 } );
		const options = baseOptions( { locale: 'de_DE' } );

		await expect(
			createSdkInstance( { createInstance }, options )
		).resolves.toEqual( { id: 1 } );
		expect( createInstance.mock.calls ).toEqual( [ [ options ] ] );
	} );

	test.each( [ '', undefined ] )(
		'omits the locale key when it is %p',
		async ( locale ) => {
			const createInstance = jest.fn().mockResolvedValue( {} );

			await createSdkInstance(
				{ createInstance },
				baseOptions( { locale } )
			);

			expect( createInstance.mock.calls ).toEqual( [
				[ baseOptions() ],
			] );
		}
	);

	test( 'retries once without the locale when it is unsupported', async () => {
		const createInstance = jest
			.fn()
			.mockRejectedValueOnce( unsupportedLocale() )
			.mockResolvedValueOnce( { id: 2 } );

		await expect(
			createSdkInstance(
				{ createInstance },
				baseOptions( { locale: 'tr_TR' } )
			)
		).resolves.toEqual( { id: 2 } );
		expect( createInstance.mock.calls[ 1 ] ).toEqual( [ baseOptions() ] );
	} );

	test( 'rethrows other SDK errors without retrying', async () => {
		const error = sdkError( 'ERR_DEV_INVALID_CLIENT_ID' );
		const createInstance = jest.fn().mockRejectedValue( error );

		await expect(
			createSdkInstance(
				{ createInstance },
				baseOptions( { locale: 'de_DE' } )
			)
		).rejects.toBe( error );
		expect( createInstance ).toHaveBeenCalledTimes( 1 );
	} );
} );
