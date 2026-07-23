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
jest.mock( '../../endpointsAdapter', () => ( {
	getOrder: ( ...args ) => mockGetOrder( ...args ),
	approveOrderInSession: ( ...args ) => mockApproveInSession( ...args ),
	createOrder: ( ...args ) => mockCreateOrder( ...args ),
	updateShipping: ( ...args ) => mockUpdateShipping( ...args ),
} ) );

const mockButtonContainer = jest.fn( () => null );
jest.mock( '../../blocks/V6ButtonContainer', () => ( {
	V6ButtonContainer: ( props ) => mockButtonContainer( props ),
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
} );
