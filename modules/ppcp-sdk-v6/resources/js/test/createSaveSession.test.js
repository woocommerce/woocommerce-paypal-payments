const mockPostJson = jest.fn();
jest.mock( '../utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
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
} );
