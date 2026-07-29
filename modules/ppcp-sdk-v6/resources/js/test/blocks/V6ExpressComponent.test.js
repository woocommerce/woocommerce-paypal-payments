const mockLoadSdkV6 = jest.fn();
jest.mock( '../../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

const mockCheckEligibility = jest.fn();
jest.mock( '../../eligibility', () => ( {
	checkEligibility: ( ...args ) => mockCheckEligibility( ...args ),
} ) );

let capturedHandlers = null;
const mockCreateSession = jest.fn(
	( sdk, method, config, context, handlers ) => {
		capturedHandlers = handlers;
		return { fake: 'session' };
	}
);
jest.mock( '../../sessions/createSession', () => ( {
	createSession: ( ...args ) => mockCreateSession( ...args ),
} ) );

const mockGetOrder = jest.fn();
const mockApproveInSession = jest.fn();
const mockCreateOrder = jest.fn();
const mockUpdateShipping = jest.fn();
const mockAssign = jest.fn();
jest.mock( '../../endpointsAdapter', () => ( {
	getOrder: ( ...args ) => mockGetOrder( ...args ),
	approveOrderInSession: ( ...args ) => mockApproveInSession( ...args ),
	createOrder: ( ...args ) => mockCreateOrder( ...args ),
	updateShipping: ( ...args ) => mockUpdateShipping( ...args ),
	navigation: { assign: ( ...args ) => mockAssign( ...args ) },
} ) );

const mockButtonContainer = jest.fn( () => null );
jest.mock( '../../blocks/V6ButtonContainer', () => ( {
	V6ButtonContainer: ( props ) => mockButtonContainer( props ),
} ) );

const mockBuildShippingHandlers = jest.fn();
jest.mock( '../../blocks/blocksShippingHandlers', () => ( {
	buildBlocksShippingHandlers: ( ...args ) =>
		mockBuildShippingHandlers( ...args ),
} ) );

import { render, waitFor, act } from '@testing-library/react';
import { createElement } from '@wordpress/element';
import { V6ExpressComponent } from '../../blocks/V6ExpressComponent';

const config = {
	page_context: 'checkout-block',
	currency: 'USD',
	buyer_country: 'US',
	amount: '50.00',
	button_styles: { 'checkout-block': {} },
	shipping: { handle_in_paypal: false, need_shipping: false },
	ajax: {
		get_order: { endpoint: '', nonce: '' },
		approve_order: { endpoint: '', nonce: '' },
		create_order: { endpoint: '', nonce: '' },
		update_shipping: { endpoint: '', nonce: '' },
	},
	urls: { checkout: '/checkout/' },
};

let onPaymentSetup;
let onCheckoutFail;
let paymentSetupCb;
let mockUpdateCustomerData;

function renderComponent( overrides = {} ) {
	return render(
		createElement( V6ExpressComponent, {
			config,
			fundingSource: 'paypal',
			onClick: jest.fn(),
			onClose: jest.fn(),
			onError: jest.fn(),
			onSubmit: jest.fn(),
			eventRegistration: { onPaymentSetup, onCheckoutFail },
			emitResponse: {
				responseTypes: { SUCCESS: 'success', ERROR: 'error' },
			},
			activePaymentMethod: 'ppcp-gateway-paypal',
			shippingData: { needsShipping: false },
			...overrides,
		} )
	);
}

beforeEach( () => {
	capturedHandlers = null;
	paymentSetupCb = null;
	mockLoadSdkV6.mockReset().mockResolvedValue( { sdk: true } );
	mockCheckEligibility.mockReset().mockResolvedValue( {
		paypal: true,
		venmo: false,
		paylater: false,
		payLaterDetails: null,
	} );
	mockCreateSession.mockClear();
	mockGetOrder.mockReset();
	mockApproveInSession.mockReset().mockResolvedValue( undefined );
	mockCreateOrder.mockReset();
	mockButtonContainer.mockClear();
	mockAssign.mockReset();
	mockBuildShippingHandlers.mockReset().mockReturnValue( {
		onShippingAddressChange: jest.fn(),
		onShippingOptionsChange: jest.fn(),
	} );

	onPaymentSetup = jest.fn( ( cb ) => {
		paymentSetupCb = cb;
		return () => {};
	} );
	onCheckoutFail = jest.fn( () => () => {} );

	mockUpdateCustomerData = jest.fn( () => Promise.resolve() );
	global.wp = {
		data: {
			dispatch: () => ( {
				updateCustomerData: mockUpdateCustomerData,
				selectShippingRate: jest.fn( () => Promise.resolve() ),
				setBillingAddress: jest.fn(),
				setShippingAddress: jest.fn(),
			} ),
			select: () => ( {
				getCustomerData: () => ( {
					billingAddress: {},
					shippingAddress: {},
				} ),
			} ),
		},
	};
} );

describe( 'V6ExpressComponent', () => {
	test( 'approve flow fetches the order, prefills the address, approves without a WC order, then submits', async () => {
		mockGetOrder.mockResolvedValue( {
			id: 'PPORDER',
			purchase_units: [
				{
					shipping: {
						address: {
							address_line_1: '1 St',
							admin_area_1: 'CA',
							admin_area_2: 'LA',
							postal_code: '90001',
							country_code: 'US',
						},
					},
				},
			],
		} );
		const onSubmit = jest.fn();

		renderComponent( { onSubmit } );
		await waitFor( () => expect( mockCreateSession ).toHaveBeenCalled() );

		await act( async () => {
			await capturedHandlers.onApprove( { orderId: 'ORDER1' } );
		} );

		expect( mockGetOrder ).toHaveBeenCalledWith( config, 'ORDER1' );
		expect( mockUpdateCustomerData ).toHaveBeenCalledTimes( 1 );
		expect( mockApproveInSession ).toHaveBeenCalledWith(
			config,
			'paypal',
			'ORDER1'
		);
		expect( onSubmit ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'onPaymentSetup returns paypal_order_id and funding_source after approval', async () => {
		mockGetOrder.mockResolvedValue( {
			id: 'PPORDER',
			purchase_units: [
				{ shipping: { address: { address_line_1: '1 St' } } },
			],
		} );

		renderComponent( { activePaymentMethod: 'ppcp-gateway-paypal' } );
		await waitFor( () => expect( mockCreateSession ).toHaveBeenCalled() );
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

		await act( async () => {
			await capturedHandlers.onApprove( { orderId: 'ORDER1' } );
		} );

		await waitFor( () => {
			const result = paymentSetupCb();
			expect( result.meta.paymentMethodData.paypal_order_id ).toBe(
				'PPORDER'
			);
		} );

		const result = paymentSetupCb();
		expect( result.type ).toBe( 'success' );
		expect( result.meta.paymentMethodData.funding_source ).toBe( 'paypal' );
	} );

	test( 'renders no button until the SDK is ready', () => {
		mockLoadSdkV6.mockReturnValue( new Promise( () => {} ) );

		renderComponent();

		expect( mockButtonContainer ).not.toHaveBeenCalled();
	} );

	test( 'passes the created session and a createOrder function to the button', async () => {
		renderComponent();
		await waitFor( () => expect( mockButtonContainer ).toHaveBeenCalled() );

		const props = mockButtonContainer.mock.calls.at( -1 )[ 0 ];
		expect( props.method ).toBe( 'paypal' );
		expect( props.session ).toEqual( { fake: 'session' } );
		expect( typeof props.createOrderFn ).toBe( 'function' );

		props.createOrderFn();
		expect( mockCreateOrder ).toHaveBeenCalledWith(
			config,
			'checkout-block',
			'paypal'
		);
	} );

	test( 'keeps one session and one button across re-renders with new callback identities', async () => {
		const { rerender } = renderComponent();
		await waitFor( () => expect( mockButtonContainer ).toHaveBeenCalled() );

		const sessionCallsAfterMount = mockCreateSession.mock.calls.length;

		// The Blocks registry hands fresh callback identities on every render;
		// these must not rebuild the session or remount the button.
		rerender(
			createElement( V6ExpressComponent, {
				config,
				fundingSource: 'paypal',
				onClick: jest.fn(),
				onClose: jest.fn(),
				onError: jest.fn(),
				onSubmit: jest.fn(),
				eventRegistration: { onPaymentSetup, onCheckoutFail },
				emitResponse: {
					responseTypes: { SUCCESS: 'success', ERROR: 'error' },
				},
				activePaymentMethod: 'ppcp-gateway-paypal',
				shippingData: { needsShipping: false },
			} )
		);

		expect( mockCreateSession.mock.calls.length ).toBe(
			sessionCallsAfterMount
		);
		expect( mockButtonContainer.mock.calls.at( -1 )[ 0 ].session ).toEqual(
			{
				fake: 'session',
			}
		);
	} );

	test( 're-checks eligibility with the new amount when the cart total changes', async () => {
		const props = {
			config,
			fundingSource: 'paypal',
			onClick: jest.fn(),
			onClose: jest.fn(),
			onError: jest.fn(),
			onSubmit: jest.fn(),
			eventRegistration: { onPaymentSetup, onCheckoutFail },
			emitResponse: {
				responseTypes: { SUCCESS: 'success', ERROR: 'error' },
			},
			activePaymentMethod: 'ppcp-gateway-paypal',
			shippingData: { needsShipping: false },
			billing: {
				cartTotal: { value: '5000' },
				currency: { minorUnit: 2 },
			},
		};

		const { rerender } = render(
			createElement( V6ExpressComponent, props )
		);
		await waitFor( () =>
			expect( mockCheckEligibility ).toHaveBeenCalled()
		);
		expect( mockCheckEligibility.mock.calls.at( -1 )[ 1 ].amount ).toBe(
			'50.00'
		);

		rerender(
			createElement( V6ExpressComponent, {
				...props,
				billing: {
					cartTotal: { value: '15000' },
					currency: { minorUnit: 2 },
				},
			} )
		);

		await waitFor( () => {
			const amounts = mockCheckEligibility.mock.calls.map(
				( call ) => call[ 1 ].amount
			);
			expect( amounts ).toContain( '150.00' );
		} );
	} );

	test( 'redirects to the review page instead of submitting when the final review is on', async () => {
		mockGetOrder.mockResolvedValue( { id: 'PPORDER' } );
		const onSubmit = jest.fn();

		renderComponent( {
			config: { ...config, final_review: true },
			onSubmit,
		} );
		await waitFor( () => expect( mockCreateSession ).toHaveBeenCalled() );

		await act( async () => {
			await capturedHandlers.onApprove( { orderId: 'ORDER1' } );
		} );

		// The order must still be approved in the session — the review page is
		// built from it on the next render.
		expect( mockApproveInSession ).toHaveBeenCalledTimes( 1 );
		expect( onSubmit ).not.toHaveBeenCalled();
		expect( mockAssign ).toHaveBeenCalledTimes( 1 );
		expect( mockAssign.mock.calls[ 0 ][ 0 ] ).toContain(
			'ppcp-continuation-redirect='
		);
	} );

	test( 'submits directly when the final review is off', async () => {
		mockGetOrder.mockResolvedValue( { id: 'PPORDER' } );
		const onSubmit = jest.fn();

		renderComponent( { onSubmit } );
		await waitFor( () => expect( mockCreateSession ).toHaveBeenCalled() );

		await act( async () => {
			await capturedHandlers.onApprove( { orderId: 'ORDER1' } );
		} );

		expect( onSubmit ).toHaveBeenCalledTimes( 1 );
		expect( mockAssign ).not.toHaveBeenCalled();
	} );

	test( 'a failed approval reports the error and releases the express UI', async () => {
		mockGetOrder.mockRejectedValue( new Error( 'nonce expired' ) );
		const onError = jest.fn();
		const onClose = jest.fn();
		const onSubmit = jest.fn();

		renderComponent( { onError, onClose, onSubmit } );
		await waitFor( () => expect( mockCreateSession ).toHaveBeenCalled() );

		await act( async () => {
			// Rethrown so the SDK is told the approval failed, as v5 does.
			await expect(
				capturedHandlers.onApprove( { orderId: 'ORDER1' } )
			).rejects.toThrow( 'nonce expired' );
		} );

		// Without these the buyer is stuck in a blocked express state.
		expect( onError ).toHaveBeenCalledWith( 'nonce expired' );
		expect( onClose ).toHaveBeenCalledTimes( 1 );
		expect( onSubmit ).not.toHaveBeenCalled();
	} );

	test( 'attaches shipping handlers once the cart starts needing shipping', async () => {
		const props = {
			config: {
				...config,
				shipping: { handle_in_paypal: true, need_shipping: true },
			},
			fundingSource: 'paypal',
			onClick: jest.fn(),
			onClose: jest.fn(),
			onError: jest.fn(),
			onSubmit: jest.fn(),
			eventRegistration: { onPaymentSetup, onCheckoutFail },
			emitResponse: {
				responseTypes: { SUCCESS: 'success', ERROR: 'error' },
			},
			activePaymentMethod: 'ppcp-gateway-paypal',
			shippingData: { needsShipping: false },
		};

		const { rerender } = render(
			createElement( V6ExpressComponent, props )
		);
		await waitFor( () => expect( mockCreateSession ).toHaveBeenCalled() );
		expect( capturedHandlers.onShippingAddressChange ).toBeUndefined();

		rerender(
			createElement( V6ExpressComponent, {
				...props,
				shippingData: { needsShipping: true },
			} )
		);

		await waitFor( () =>
			expect( capturedHandlers.onShippingAddressChange ).toBeDefined()
		);
	} );

	test( 'shipping handlers read the current shippingData, not the mount-time one', async () => {
		const first = jest.fn();
		const second = jest.fn();
		mockBuildShippingHandlers
			.mockReturnValueOnce( {
				onShippingAddressChange: first,
				onShippingOptionsChange: jest.fn(),
			} )
			.mockReturnValue( {
				onShippingAddressChange: second,
				onShippingOptionsChange: jest.fn(),
			} );

		const props = {
			config: {
				...config,
				shipping: { handle_in_paypal: true, need_shipping: true },
			},
			fundingSource: 'paypal',
			onClick: jest.fn(),
			onClose: jest.fn(),
			onError: jest.fn(),
			onSubmit: jest.fn(),
			eventRegistration: { onPaymentSetup, onCheckoutFail },
			emitResponse: {
				responseTypes: { SUCCESS: 'success', ERROR: 'error' },
			},
			activePaymentMethod: 'ppcp-gateway-paypal',
			shippingData: { needsShipping: true, setShippingAddress: jest.fn() },
		};

		const { rerender } = render(
			createElement( V6ExpressComponent, props )
		);
		await waitFor( () =>
			expect( capturedHandlers.onShippingAddressChange ).toBeDefined()
		);

		// A fresh shippingData identity (what Blocks hands us on cart updates)
		// must reach the already-created session.
		rerender(
			createElement( V6ExpressComponent, {
				...props,
				shippingData: {
					needsShipping: true,
					setShippingAddress: jest.fn(),
				},
			} )
		);

		await act( async () => {
			await capturedHandlers.onShippingAddressChange( { orderId: 'O1' } );
		} );

		expect( second ).toHaveBeenCalledTimes( 1 );
		expect( first ).not.toHaveBeenCalled();
	} );
} );
