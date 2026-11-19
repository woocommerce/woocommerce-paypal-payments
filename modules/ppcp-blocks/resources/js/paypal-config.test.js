jest.mock( './Components/paypal', () => ( {
	shouldEnableAppSwitch: jest.fn( () => false ),
} ) );
jest.mock( '@ppcp-button/Helper/CartPayerData', () => ( {
	cartPayerData: jest.fn(),
} ) );

import '@ppcp-test/helpers/silenceConsole';
import {
	completeApproval,
	handleApproveSubscription,
	onApproveSavePayment,
} from './paypal-config';

const config = {
	scriptData: {
		ajax: { approve_subscription: { endpoint: 'x', nonce: 'n' } },
		labels: { error: { generic: 'err' } },
	},
};

const subscription = {
	subscriber: {
		email_address: 'jane@example.com',
		name: { given_name: 'Jane', surname: 'Doe' },
		shipping_address: {
			address: {
				address_line_1: '1 Main St',
				admin_area_2: 'Springfield',
				admin_area_1: 'IL',
				postal_code: '62701',
				country_code: 'US',
			},
		},
	},
};

describe( 'onApproveSavePayment()', () => {
	let originalFetch;

	const saveConfig = ( user = {} ) => ( {
		scriptData: {
			user,
			is_free_trial_cart: true,
			labels: { error: { generic: 'Generic error' } },
			ajax: {
				create_payment_token: { endpoint: '/token', nonce: 'n-user' },
				create_payment_token_for_guest: {
					endpoint: '/guest-token',
					nonce: 'n-guest',
				},
			},
		},
	} );

	const respondWith = ( body ) => {
		global.fetch = jest.fn().mockResolvedValue( {
			json: async () => body,
		} );
	};

	beforeEach( () => {
		originalFetch = global.fetch;
	} );

	afterEach( () => {
		global.fetch = originalFetch;
	} );

	test.each( [
		[
			'logged-out',
			{ is_logged_in: false },
			'/guest-token',
			{
				nonce: 'n-guest',
				vault_setup_token: 'VAULT1',
				free_trial_continuation: true,
			},
		],
		[
			'logged-in',
			{ is_logged_in: true },
			'/token',
			{
				nonce: 'n-user',
				vault_setup_token: 'VAULT1',
				is_free_trial_cart: true,
				free_trial_continuation: true,
			},
		],
	] )(
		'saves a %s buyer via the matching endpoint and calls onComplete without reporting an error',
		async ( _label, user, endpoint, body ) => {
			respondWith( { success: true } );
			const onComplete = jest.fn();
			const onError = jest.fn();
			const onClose = jest.fn();

			await onApproveSavePayment(
				'VAULT1',
				saveConfig( user ),
				onComplete,
				onError,
				onClose
			);

			const [ url, options ] = global.fetch.mock.calls[ 0 ];
			expect( url ).toBe( endpoint );
			expect( JSON.parse( options.body ) ).toEqual( body );
			expect( onComplete ).toHaveBeenCalledTimes( 1 );
			expect( onError ).not.toHaveBeenCalled();
			expect( onClose ).not.toHaveBeenCalled();
		}
	);

	test.each( [
		[ 'success is false', { success: false }, 'Generic error' ],
		[ 'success is not strictly true', { success: 'yes' }, 'Generic error' ],
		[ 'the response has no success flag', {}, 'Generic error' ],
		[
			'the server sends a message',
			{ success: false, data: { message: 'Token rejected' } },
			'Token rejected',
		],
	] )(
		'reports the error and closes without calling onComplete when %s',
		async ( _label, body, expectedMessage ) => {
			respondWith( body );
			const onComplete = jest.fn();
			const onError = jest.fn();
			const onClose = jest.fn();

			await onApproveSavePayment(
				'VAULT1',
				saveConfig(),
				onComplete,
				onError,
				onClose
			);

			expect( onComplete ).not.toHaveBeenCalled();
			expect( onError ).toHaveBeenCalledWith( expectedMessage );
			expect( onClose ).toHaveBeenCalledTimes( 1 );
		}
	);

	test( 'reports the error and closes without calling onComplete when the request fails', async () => {
		global.fetch = jest
			.fn()
			.mockRejectedValue( new Error( 'Network down' ) );
		const onComplete = jest.fn();
		const onError = jest.fn();
		const onClose = jest.fn();

		await onApproveSavePayment(
			'VAULT1',
			saveConfig(),
			onComplete,
			onError,
			onClose
		);

		expect( onComplete ).not.toHaveBeenCalled();
		expect( onError ).toHaveBeenCalledWith( 'Network down' );
		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'completeApproval()', () => {
	const approvalArgs = ( skipFinalConfirmation, overrides = {} ) => ( {
		shouldskipFinalConfirmation: () => skipFinalConfirmation,
		getCheckoutRedirectUrl: jest.fn( () => '#final-review' ),
		setGotoContinuationOnError: jest.fn(),
		onSubmit: jest.fn(),
		...overrides,
	} );

	beforeEach( () => {
		window.location.hash = '';
	} );

	test( 'flags continuation on error and submits when the final review is skipped', () => {
		const args = approvalArgs( true );

		completeApproval( args );

		expect( args.setGotoContinuationOnError ).toHaveBeenCalledWith( true );
		expect( args.onSubmit ).toHaveBeenCalledTimes( 1 );
		expect( window.location.hash ).toBe( '' );
	} );

	test( 'navigates to the redirect URL without submitting when the final review is required', () => {
		const args = approvalArgs( false );

		completeApproval( args );

		expect( window.location.hash ).toBe( '#final-review' );
		expect( args.onSubmit ).not.toHaveBeenCalled();
		expect( args.setGotoContinuationOnError ).not.toHaveBeenCalled();
	} );
} );

describe( 'handleApproveSubscription()', () => {
	let cart;
	let onSubmit;
	let originalFetch;
	let originalWp;

	const approve = ( shippingData ) =>
		handleApproveSubscription(
			{ orderID: 'O1', subscriptionID: 'S1' },
			{
				subscription: {
					get: jest.fn().mockResolvedValue( subscription ),
				},
			},
			config,
			shippingData,
			jest.fn(),
			jest.fn(),
			jest.fn(),
			jest.fn(),
			onSubmit,
			jest.fn(),
			jest.fn()
		);

	beforeEach( () => {
		jest.clearAllMocks();
		originalFetch = global.fetch;
		originalWp = global.wp;
		global.fetch = jest.fn().mockResolvedValue( {
			json: () => ( { success: true } ),
		} );
		cart = {
			updateCustomerData: jest.fn().mockResolvedValue( undefined ),
			setBillingAddress: jest.fn().mockResolvedValue( undefined ),
			setShippingAddress: jest.fn().mockResolvedValue( undefined ),
		};
		global.wp = { data: { dispatch: jest.fn( () => cart ) } };
		onSubmit = jest.fn();
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		global.wp = originalWp;
	} );

	test( 'sets billing and shipping addresses in the UI and submits when shipping is needed', async () => {
		await approve( { needsShipping: true } );

		const expected = expect.objectContaining( {
			email: 'jane@example.com',
			address_1: '1 Main St',
		} );
		expect( cart.setBillingAddress ).toHaveBeenCalledWith( expected );
		expect( cart.setShippingAddress ).toHaveBeenCalledWith( expected );
		expect( onSubmit ).toHaveBeenCalled();
	} );

	test( 'sets only the billing address in the UI when shipping is not needed', async () => {
		await approve( { needsShipping: false } );

		expect( cart.setBillingAddress ).toHaveBeenCalledWith(
			expect.objectContaining( { email: 'jane@example.com' } )
		);
		expect( cart.setShippingAddress ).not.toHaveBeenCalled();
		expect( onSubmit ).toHaveBeenCalled();
	} );
} );
