const mockPostJson = jest.fn();
jest.mock( '../utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

import {
	createFreeTrialPayPalSession,
	exchangeSetupToken,
} from './freeTrialSave';

const config = ( isLogged ) => ( {
	user: { is_logged: isLogged },
	ajax: {
		create_payment_token: { endpoint: '/token' },
		create_payment_token_for_guest: { endpoint: '/guest-token' },
	},
} );

beforeEach( () => {
	jest.clearAllMocks();
	mockPostJson.mockResolvedValue( {} );
} );

describe( 'exchangeSetupToken()', () => {
	test.each( [
		[ 'logged-in', true, 'create_payment_token', undefined ],
		[ 'guest', false, 'create_payment_token_for_guest', undefined ],
		[
			'logged-in, with the continuation flag explicitly disabled,',
			true,
			'create_payment_token',
			{ freeTrialContinuation: false },
		],
	] )(
		'posts only the setup token to the %s endpoint by default',
		async ( _label, isLogged, ajaxKey, options ) => {
			const cfg = config( isLogged );

			await exchangeSetupToken( cfg, 'VAULT1', options );

			expect( mockPostJson ).toHaveBeenCalledWith( cfg.ajax[ ajaxKey ], {
				vault_setup_token: 'VAULT1',
			} );
		}
	);

	test.each( [
		[ 'logged-in', true, 'create_payment_token' ],
		[ 'guest', false, 'create_payment_token_for_guest' ],
	] )(
		'adds the continuation flag for the %s endpoint when requested',
		async ( _label, isLogged, ajaxKey ) => {
			const cfg = config( isLogged );

			await exchangeSetupToken( cfg, 'VAULT1', {
				freeTrialContinuation: true,
			} );

			expect( mockPostJson ).toHaveBeenCalledWith( cfg.ajax[ ajaxKey ], {
				vault_setup_token: 'VAULT1',
				free_trial_continuation: true,
			} );
		}
	);
} );

describe( 'createFreeTrialPayPalSession()', () => {
	const createSession = ( handlers ) => {
		let sessionOptions;
		const sdk = {
			createPayPalSavePaymentSession: jest.fn( ( options ) => {
				sessionOptions = options;
				return {};
			} ),
		};
		createFreeTrialPayPalSession( sdk, config( true ), handlers );
		return sessionOptions;
	};

	test( 'forwards the continuation option to the exchange and then completes', async () => {
		const onComplete = jest.fn();
		const session = createSession( {
			onComplete,
			freeTrialContinuation: true,
		} );

		await session.onApprove( { vaultSetupToken: 'VAULT1' } );

		expect( mockPostJson ).toHaveBeenCalledWith(
			{ endpoint: '/token' },
			{ vault_setup_token: 'VAULT1', free_trial_continuation: true }
		);
		expect( onComplete ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reports a failed exchange to onError without completing', async () => {
		const failure = new Error( 'denied' );
		mockPostJson.mockRejectedValue( failure );
		const onComplete = jest.fn();
		const onError = jest.fn();
		const session = createSession( { onComplete, onError } );

		await session.onApprove( { vaultSetupToken: 'VAULT1' } );

		expect( onError ).toHaveBeenCalledWith( failure );
		expect( onComplete ).not.toHaveBeenCalled();
	} );
} );
