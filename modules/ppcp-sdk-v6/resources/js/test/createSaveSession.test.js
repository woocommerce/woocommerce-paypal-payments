const mockPostJson = jest.fn();
jest.mock( '../utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

const mockSetSdkBusy = jest.fn();
jest.mock( '../tokenRefresh', () => ( {
	releasingSdkBusy: ( sessionConfig ) => {
		for ( const name of [ 'onApprove', 'onCancel', 'onError' ] ) {
			const callback = sessionConfig[ name ];
			if ( ! callback ) {
				continue;
			}
			sessionConfig[ name ] = async ( ...args ) => {
				try {
					return await callback( ...args );
				} finally {
					mockSetSdkBusy( false );
				}
			};
		}
		return sessionConfig;
	},
} ) );

import { createSavePayPalSession } from '../sessions/createSaveSession';
import { navigation } from '../utils/navigation';

const config = {
	ajax: {
		create_payment_token: { endpoint: '/cpt', nonce: 'n-cpt' },
	},
	payment_methods_page: '/my-account/payment-methods/',
};

function fakeSdk() {
	const capture = {};
	return {
		capture,
		createPayPalSavePaymentSession: ( sessionConfig ) => {
			capture.config = sessionConfig;
			return { session: true };
		},
	};
}

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'createSavePayPalSession', () => {
	test( 'builds the session from the sdk instance with onApprove, onCancel and onError handlers', () => {
		const sdk = fakeSdk();

		const session = createSavePayPalSession( sdk, config );

		expect( session ).toEqual( { session: true } );
		expect( sdk.capture.config.onApprove ).toBeInstanceOf( Function );
		expect( sdk.capture.config.onCancel ).toBeInstanceOf( Function );
		expect( sdk.capture.config.onError ).toBeInstanceOf( Function );
	} );

	test( 'onApprove exchanges the vault setup token and redirects to the payment methods page', async () => {
		const sdk = fakeSdk();
		mockPostJson.mockResolvedValueOnce( {} );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		createSavePayPalSession( sdk, config );
		await sdk.capture.config.onApprove( { vaultSetupToken: 'SETUP1' } );

		expect( mockPostJson ).toHaveBeenCalledWith(
			config.ajax.create_payment_token,
			{ vault_setup_token: 'SETUP1' }
		);
		expect( assign ).toHaveBeenCalledWith( config.payment_methods_page );
	} );

	test( 'onApprove routes to the error handler and does not redirect when the exchange fails', async () => {
		const sdk = fakeSdk();
		mockPostJson.mockRejectedValueOnce( new Error( 'token exchange failed' ) );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		createSavePayPalSession( sdk, config );
		await sdk.capture.config.onApprove( { vaultSetupToken: 'SETUP1' } );

		expect( mockHandleError ).toHaveBeenCalledWith(
			expect.any( Error )
		);
		expect( assign ).not.toHaveBeenCalled();
	} );

	test( 'onError forwards the error to the error handler', () => {
		const sdk = fakeSdk();
		const error = new Error( 'sdk session error' );

		createSavePayPalSession( sdk, config );
		sdk.capture.config.onError( error );

		expect( mockHandleError ).toHaveBeenCalledWith( error );
	} );

	describe( 'releasing the SDK busy state', () => {
		const releasedBusy = () =>
			mockSetSdkBusy.mock.calls.some( ( [ busy ] ) => busy === false );

		test( 'onApprove releases it only after the token exchange finished', async () => {
			const sdk = fakeSdk();
			let finishExchange;
			mockPostJson.mockReturnValueOnce(
				new Promise( ( resolve ) => {
					finishExchange = resolve;
				} )
			);
			jest.spyOn( navigation, 'assign' ).mockImplementation( () => {} );
			createSavePayPalSession( sdk, config );

			const approval = sdk.capture.config.onApprove( {
				vaultSetupToken: 'SETUP1',
			} );
			await Promise.resolve();
			expect( releasedBusy() ).toBe( false );

			finishExchange( {} );
			await approval;

			expect( releasedBusy() ).toBe( true );
		} );

		test( 'onApprove releases it after a failed exchange', async () => {
			const sdk = fakeSdk();
			mockPostJson.mockRejectedValueOnce( new Error( 'exchange failed' ) );
			createSavePayPalSession( sdk, config );

			await sdk.capture.config.onApprove( { vaultSetupToken: 'SETUP1' } );

			expect( releasedBusy() ).toBe( true );
		} );

		test( 'onCancel releases it', async () => {
			const sdk = fakeSdk();
			createSavePayPalSession( sdk, config );

			await sdk.capture.config.onCancel();

			expect( releasedBusy() ).toBe( true );
		} );

		test( 'onError releases it', async () => {
			const sdk = fakeSdk();
			createSavePayPalSession( sdk, config );

			await sdk.capture.config.onError( new Error( 'sdk session error' ) );

			expect( releasedBusy() ).toBe( true );
		} );

		test.each( [
			[
				'onApprove',
				( callbacks ) => callbacks.onApprove( { vaultSetupToken: 'S' } ),
				() => mockPostJson.mockRejectedValueOnce( new Error( 'x' ) ),
			],
			[
				'onError',
				( callbacks ) => callbacks.onError( new Error( 'x' ) ),
				() => {},
			],
		] )(
			'%s releases it and rejects the same way when the error handler throws',
			async ( name, trigger, arrange ) => {
				const sdk = fakeSdk();
				const failure = new Error( 'handler failed' );
				mockHandleError.mockImplementationOnce( () => {
					throw failure;
				} );
				arrange();
				createSavePayPalSession( sdk, config );

				await expect( trigger( sdk.capture.config ) ).rejects.toBe(
					failure
				);
				expect( releasedBusy() ).toBe( true );
			}
		);
	} );
} );
