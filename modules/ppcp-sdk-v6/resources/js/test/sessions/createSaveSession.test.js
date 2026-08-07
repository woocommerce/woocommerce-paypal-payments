jest.mock( '../../utils/api', () => ( {
	postJson: jest.fn(),
} ) );

jest.mock( '../../utils/errorHandler', () => ( {
	handleError: jest.fn(),
} ) );

import {
	createSavePayPalSession,
	navigation,
} from '../../sessions/createSaveSession';
import { postJson } from '../../utils/api';
import { handleError } from '../../utils/errorHandler';

const config = {
	ajax: {
		create_payment_token: { endpoint: '/cpt', nonce: 'n-cpt' },
	},
	payment_methods_page: '/my-account/payment-methods/',
};

function sdkWith() {
	let captured;
	const sdk = {
		createPayPalSavePaymentSession: jest.fn( ( sessionConfig ) => {
			captured = sessionConfig;
			return { __config: sessionConfig };
		} ),
	};
	return {
		sdk,
		config: () => captured,
	};
}

afterEach( () => {
	postJson.mockReset();
	handleError.mockReset();
} );

describe( 'createSavePayPalSession', () => {
	test( 'creates the save session with approve/cancel/error handlers', () => {
		const { sdk, config: sessionConfig } = sdkWith();

		createSavePayPalSession( sdk, config );

		expect( sdk.createPayPalSavePaymentSession ).toHaveBeenCalledTimes( 1 );
		expect( typeof sessionConfig().onApprove ).toBe( 'function' );
		expect( typeof sessionConfig().onCancel ).toBe( 'function' );
		expect( typeof sessionConfig().onError ).toBe( 'function' );
	} );

	test( 'onApprove exchanges the setup token and redirects to the payment methods page', async () => {
		const { sdk, config: sessionConfig } = sdkWith();
		postJson.mockResolvedValueOnce( 42 );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		createSavePayPalSession( sdk, config );
		await sessionConfig().onApprove( { vaultSetupToken: 'SETUP-1' } );

		expect( postJson ).toHaveBeenCalledWith(
			config.ajax.create_payment_token,
			{ vault_setup_token: 'SETUP-1' }
		);
		expect( assign ).toHaveBeenCalledWith( '/my-account/payment-methods/' );
		expect( handleError ).not.toHaveBeenCalled();

		assign.mockRestore();
	} );

	test( 'onApprove routes failures to the error handler without redirecting', async () => {
		const { sdk, config: sessionConfig } = sdkWith();
		const error = new Error( 'token exchange failed' );
		postJson.mockRejectedValueOnce( error );
		const assign = jest
			.spyOn( navigation, 'assign' )
			.mockImplementation( () => {} );

		createSavePayPalSession( sdk, config );
		await sessionConfig().onApprove( { vaultSetupToken: 'SETUP-2' } );

		expect( handleError ).toHaveBeenCalledWith( error );
		expect( assign ).not.toHaveBeenCalled();

		assign.mockRestore();
	} );

	test( 'onError forwards to the error handler', () => {
		const { sdk, config: sessionConfig } = sdkWith();
		const error = new Error( 'sdk error' );

		createSavePayPalSession( sdk, config );
		sessionConfig().onError( error );

		expect( handleError ).toHaveBeenCalledWith( error );
	} );
} );
