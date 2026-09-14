const mockPostJson = jest.fn();
jest.mock( '../utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

import {
	createVaultSetupToken,
	createCardSetupToken,
	exchangeSetupToken,
	createFreeTrialPayPalSession,
} from '../sessions/freeTrialSave';

const baseConfig = ( overrides = {} ) => ( {
	ajax: {
		create_setup_token: { endpoint: '/cst', nonce: 'n-cst' },
		create_payment_token: { endpoint: '/cpt', nonce: 'n-cpt' },
		create_payment_token_for_guest: { endpoint: '/cptg', nonce: 'n-cptg' },
	},
	user: { is_logged: true },
	card_fields: { payment_method: 'ppcp-credit-card-gateway' },
	verification_method: 'SCA_ALWAYS',
	is_free_trial_cart: true,
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'createVaultSetupToken', () => {
	test( 'posts to the create-setup-token endpoint and resolves the setup token id', async () => {
		mockPostJson.mockResolvedValueOnce( { id: 'SETUP1' } );

		const result = await createVaultSetupToken( baseConfig() );

		expect( mockPostJson ).toHaveBeenCalledWith(
			baseConfig().ajax.create_setup_token
		);
		expect( result ).toEqual( { vaultSetupToken: 'SETUP1' } );
	} );
} );

describe( 'createCardSetupToken', () => {
	test( 'posts the card payment method and verification method, resolving to the setup token id', async () => {
		mockPostJson.mockResolvedValueOnce( { id: 'CARDSETUP1' } );

		const result = await createCardSetupToken( baseConfig() );

		expect( mockPostJson ).toHaveBeenCalledWith(
			baseConfig().ajax.create_setup_token,
			{
				payment_method: 'ppcp-credit-card-gateway',
				verification_method: 'SCA_ALWAYS',
			}
		);
		expect( result ).toBe( 'CARDSETUP1' );
	} );
} );

describe( 'exchangeSetupToken', () => {
	test( 'posts to the logged-in endpoint with only the setup token when the buyer is logged in', async () => {
		mockPostJson.mockResolvedValueOnce( {} );

		await exchangeSetupToken( baseConfig(), 'SETUP1' );

		// The endpoint asks the cart itself whether this is a free trial.
		expect( mockPostJson ).toHaveBeenCalledWith(
			baseConfig().ajax.create_payment_token,
			{ vault_setup_token: 'SETUP1' }
		);
	} );

	test( 'posts to the guest endpoint with only the setup token when the buyer is not logged in', async () => {
		mockPostJson.mockResolvedValueOnce( {} );

		await exchangeSetupToken(
			baseConfig( { user: { is_logged: false } } ),
			'SETUP1'
		);

		expect( mockPostJson ).toHaveBeenCalledWith(
			baseConfig().ajax.create_payment_token_for_guest,
			{ vault_setup_token: 'SETUP1' }
		);
	} );
} );

describe( 'createFreeTrialPayPalSession', () => {
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

	test( 'builds the save session from the sdk instance with onApprove, onCancel and onError handlers', () => {
		const sdk = fakeSdk();

		const session = createFreeTrialPayPalSession( sdk, baseConfig() );

		expect( session ).toEqual( { session: true } );
		expect( sdk.capture.config.onApprove ).toBeInstanceOf( Function );
		expect( sdk.capture.config.onCancel ).toBeInstanceOf( Function );
		expect( sdk.capture.config.onError ).toBeInstanceOf( Function );
	} );

	test( 'onApprove exchanges the setup token and calls onComplete on success', async () => {
		const sdk = fakeSdk();
		mockPostJson.mockResolvedValueOnce( {} );
		const onComplete = jest.fn();
		const onError = jest.fn();

		createFreeTrialPayPalSession( sdk, baseConfig(), {
			onComplete,
			onError,
		} );
		await sdk.capture.config.onApprove( { vaultSetupToken: 'SETUP1' } );

		expect( mockPostJson ).toHaveBeenCalledWith(
			baseConfig().ajax.create_payment_token,
			expect.objectContaining( { vault_setup_token: 'SETUP1' } )
		);
		expect( onComplete ).toHaveBeenCalledTimes( 1 );
		expect( onError ).not.toHaveBeenCalled();
	} );

	test( 'onApprove calls the provided onError when the exchange fails', async () => {
		const sdk = fakeSdk();
		const error = new Error( 'token exchange failed' );
		mockPostJson.mockRejectedValueOnce( error );
		const onComplete = jest.fn();
		const onError = jest.fn();

		createFreeTrialPayPalSession( sdk, baseConfig(), {
			onComplete,
			onError,
		} );
		await sdk.capture.config.onApprove( { vaultSetupToken: 'SETUP1' } );

		expect( onError ).toHaveBeenCalledWith( error );
		expect( onComplete ).not.toHaveBeenCalled();
	} );

	test( 'onApprove falls back to the shared error handler when no onError is provided', async () => {
		const sdk = fakeSdk();
		mockPostJson.mockRejectedValueOnce( new Error( 'token exchange failed' ) );

		createFreeTrialPayPalSession( sdk, baseConfig() );
		await sdk.capture.config.onApprove( { vaultSetupToken: 'SETUP1' } );

		expect( mockHandleError ).toHaveBeenCalledWith( expect.any( Error ) );
	} );

	test( 'onError forwards the error to the provided onError', () => {
		const sdk = fakeSdk();
		const error = new Error( 'sdk session error' );
		const onError = jest.fn();

		createFreeTrialPayPalSession( sdk, baseConfig(), { onError } );
		sdk.capture.config.onError( error );

		expect( onError ).toHaveBeenCalledWith( error );
		expect( mockHandleError ).not.toHaveBeenCalled();
	} );

	test( 'onError falls back to the shared error handler when no onError is provided', () => {
		const sdk = fakeSdk();
		const error = new Error( 'sdk session error' );

		createFreeTrialPayPalSession( sdk, baseConfig() );
		sdk.capture.config.onError( error );

		expect( mockHandleError ).toHaveBeenCalledWith( error );
	} );
} );
