const mockLoadSdkV6 = jest.fn();
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

jest.mock( '../eligibility', () => ( {
	checkEligibility: jest.fn( () => Promise.resolve( {} ) ),
} ) );

jest.mock( '../sessions/createSession', () => ( {
	createSession: jest.fn(),
} ) );

const mockCreateFreeTrialPayPalSession = jest.fn();
jest.mock( '../sessions/freeTrialSave', () => ( {
	createFreeTrialPayPalSession: ( ...args ) =>
		mockCreateFreeTrialPayPalSession( ...args ),
	createVaultSetupToken: jest.fn(),
} ) );

const mockNavigationAssign = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	approveOrder: jest.fn(),
	approveOrderInSession: jest.fn(),
	createOrder: jest.fn(),
	getOrder: jest.fn(),
	navigation: { assign: ( ...args ) => mockNavigationAssign( ...args ) },
} ) );

jest.mock( './prefillAddresses', () => ( {
	prefillFromPayPalOrder: jest.fn(),
} ) );

jest.mock( './blocksShippingHandlers', () => ( {
	buildBlocksShippingHandlers: jest.fn( () => ( {} ) ),
} ) );

jest.mock( './V6ButtonContainer', () => ( {
	V6ButtonContainer: () => null,
} ) );

import { render, waitFor } from '@testing-library/react';
import { createElement } from '@wordpress/element';
import { V6ExpressComponent } from './V6ExpressComponent';

const freeTrialConfig = ( overrides = {} ) => ( {
	page_context: 'checkout-block',
	currency: 'USD',
	buyer_country: 'US',
	final_review: false,
	cart_needs_vaulting: true,
	is_free_trial_cart: true,
	urls: { checkout: '/checkout/' },
	...overrides,
} );

let sessionHandlers;
let onSubmit;

async function renderFreeTrialButton( config ) {
	const registerSubscription = () => () => {};

	render(
		createElement( V6ExpressComponent, {
			config,
			fundingSource: 'paypal',
			onClick: jest.fn(),
			onClose: jest.fn(),
			onError: jest.fn(),
			onSubmit,
			eventRegistration: {
				onPaymentSetup: registerSubscription,
				onCheckoutFail: registerSubscription,
				onCheckoutValidation: registerSubscription,
			},
			emitResponse: { responseTypes: { SUCCESS: 'success' } },
			activePaymentMethod: 'ppcp-gateway-paypal',
			shippingData: { needsShipping: false },
			// A zero cart total puts the cart on the save-without-purchase flow.
			billing: { cartTotal: { value: '0' }, currency: { minorUnit: 2 } },
		} )
	);

	await waitFor( () => expect( sessionHandlers ).toBeDefined() );
}

beforeEach( () => {
	jest.clearAllMocks();
	sessionHandlers = undefined;
	onSubmit = jest.fn();
	mockLoadSdkV6.mockReset().mockResolvedValue( {} );
	mockCreateFreeTrialPayPalSession
		.mockReset()
		.mockImplementation( ( _sdk, _config, handlers ) => {
			sessionHandlers = handlers;
			return {};
		} );
} );

describe( 'V6ExpressComponent', () => {
	describe( 'free-trial completion', () => {
		test.each( [
			[
				'final review is enabled on the checkout block',
				{ final_review: true, page_context: 'checkout-block' },
			],
			[
				'Pay Now is used on the cart block',
				{ final_review: false, page_context: 'cart-block' },
			],
		] )(
			'redirects to the checkout continuation without submitting when %s',
			async ( _name, overrides ) => {
				await renderFreeTrialButton( freeTrialConfig( overrides ) );

				sessionHandlers.onComplete();

				expect( mockNavigationAssign ).toHaveBeenCalledTimes( 1 );
				const url = new URL(
					mockNavigationAssign.mock.calls[ 0 ][ 0 ]
				);
				expect( url.pathname ).toBe( '/checkout/' );
				expect(
					url.searchParams.has( 'ppcp-continuation-redirect' )
				).toBe( true );
				expect( onSubmit ).not.toHaveBeenCalled();
			}
		);

		test( 'requests the free-trial continuation when creating the save session', async () => {
			await renderFreeTrialButton( freeTrialConfig() );

			expect( mockCreateFreeTrialPayPalSession ).toHaveBeenCalledWith(
				expect.anything(),
				expect.anything(),
				expect.objectContaining( { freeTrialContinuation: true } )
			);
		} );

		test( 'submits the checkout without redirecting when final review is disabled on the checkout block', async () => {
			await renderFreeTrialButton(
				freeTrialConfig( {
					final_review: false,
					page_context: 'checkout-block',
				} )
			);

			sessionHandlers.onComplete();

			expect( onSubmit ).toHaveBeenCalledTimes( 1 );
			expect( mockNavigationAssign ).not.toHaveBeenCalled();
		} );
	} );
} );
