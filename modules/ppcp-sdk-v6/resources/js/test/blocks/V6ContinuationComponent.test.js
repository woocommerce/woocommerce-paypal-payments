import { render, waitFor } from '@testing-library/react';
import { createElement } from '@wordpress/element';
import { V6ContinuationComponent } from '../../blocks/V6ContinuationComponent';

const config = {
	page_context: 'checkout-block',
	continuation: {
		order_id: 'PPORDER',
		funding_source: 'venmo',
		cancel: { html: '<p class="ppcp-cancel">Choose another method</p>' },
		order: {
			id: 'PPORDER',
			purchase_units: [
				{
					shipping: {
						name: { full_name: 'John Doe' },
						address: {
							address_line_1: 'WooVille 12',
							admin_area_1: 'IA',
							admin_area_2: 'Dubai',
							postal_code: '12862',
							country_code: 'US',
						},
					},
				},
			],
		},
	},
};

let onPaymentSetup;
let paymentSetupCb;
let mockUpdateCustomerData;
let mockSetBillingAddress;
let mockSetShippingAddress;
let savedCustomerData;

function renderComponent( overrides = {} ) {
	return render(
		createElement( V6ContinuationComponent, {
			config,
			eventRegistration: { onPaymentSetup },
			emitResponse: {
				responseTypes: { SUCCESS: 'success', ERROR: 'error' },
			},
			shippingData: { needsShipping: true },
			...overrides,
		} )
	);
}

beforeEach( () => {
	paymentSetupCb = null;
	onPaymentSetup = jest.fn( ( cb ) => {
		paymentSetupCb = cb;
		return () => {};
	} );

	mockUpdateCustomerData = jest.fn( () => Promise.resolve() );
	mockSetBillingAddress = jest.fn();
	mockSetShippingAddress = jest.fn();
	savedCustomerData = { billingAddress: {}, shippingAddress: {} };
	global.wp = {
		data: {
			dispatch: () => ( {
				updateCustomerData: mockUpdateCustomerData,
				setBillingAddress: mockSetBillingAddress,
				setShippingAddress: mockSetShippingAddress,
			} ),
			select: () => ( {
				getCustomerData: () => savedCustomerData,
			} ),
		},
	};
} );

describe( 'V6ContinuationComponent', () => {
	test( 'prefills the checkout from the approved PayPal order', async () => {
		renderComponent();

		await waitFor( () =>
			expect( mockUpdateCustomerData ).toHaveBeenCalledTimes( 1 )
		);

		const sent = mockUpdateCustomerData.mock.calls[ 0 ][ 0 ];
		expect( sent.shipping_address.address_1 ).toBe( 'WooVille 12' );
		expect( sent.shipping_address.first_name ).toBe( 'John' );
		expect( sent.shipping_address.last_name ).toBe( 'Doe' );

		// UI reflection happens after the server write.
		await waitFor( () =>
			expect( mockSetBillingAddress ).toHaveBeenCalledTimes( 1 )
		);
		expect( mockSetShippingAddress ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'keeps saved customer fields the PayPal order does not carry', async () => {
		// PayPal supplies no company or phone; the buyer's stored values must
		// survive rather than being blanked out.
		savedCustomerData = {
			billingAddress: { company: 'Acme GmbH', phone: '+4930123' },
			shippingAddress: { company: 'Acme GmbH' },
		};

		renderComponent();

		await waitFor( () =>
			expect( mockUpdateCustomerData ).toHaveBeenCalledTimes( 1 )
		);

		const sent = mockUpdateCustomerData.mock.calls[ 0 ][ 0 ];
		expect( sent.billing_address.company ).toBe( 'Acme GmbH' );
		expect( sent.billing_address.phone ).toBe( '+4930123' );
		// The PayPal-supplied fields still win.
		expect( sent.shipping_address.address_1 ).toBe( 'WooVille 12' );
	} );

	test( 'does not set the shipping address for a cart that does not ship', async () => {
		renderComponent( { shippingData: { needsShipping: false } } );

		await waitFor( () =>
			expect( mockSetBillingAddress ).toHaveBeenCalledTimes( 1 )
		);
		expect( mockSetShippingAddress ).not.toHaveBeenCalled();
	} );

	test( 'prefills once, not again on re-render', async () => {
		const { rerender } = renderComponent();
		await waitFor( () =>
			expect( mockUpdateCustomerData ).toHaveBeenCalledTimes( 1 )
		);

		// A cart update hands fresh props; the buyer's own edits must survive.
		rerender(
			createElement( V6ContinuationComponent, {
				config,
				eventRegistration: { onPaymentSetup },
				emitResponse: {
					responseTypes: { SUCCESS: 'success', ERROR: 'error' },
				},
				shippingData: { needsShipping: true },
			} )
		);

		expect( mockUpdateCustomerData ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'hands the session order id and funding source to the gateway', async () => {
		renderComponent();
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

		const result = paymentSetupCb();
		expect( result.type ).toBe( 'success' );
		expect( result.meta.paymentMethodData.paypal_order_id ).toBe(
			'PPORDER'
		);
		expect( result.meta.paymentMethodData.funding_source ).toBe( 'venmo' );
	} );

	test( 'renders the server-rendered cancel link', () => {
		const { container } = renderComponent();

		expect( container.querySelector( '.ppcp-cancel' ) ).not.toBeNull();
	} );
} );
