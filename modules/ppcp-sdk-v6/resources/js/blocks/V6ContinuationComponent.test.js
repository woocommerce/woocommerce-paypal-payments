jest.mock( './prefillAddresses', () => ( {
	prefillFromPayPalOrder: jest.fn().mockResolvedValue( undefined ),
} ) );

import { render } from '@testing-library/react';
import { createElement } from '@wordpress/element';
import { V6ContinuationComponent } from './V6ContinuationComponent';

const emitResponse = {
	responseTypes: { SUCCESS: 'success', ERROR: 'error' },
};

const continuationConfig = ( continuation = {} ) => ( {
	continuation: {
		order_id: 'ORDER1',
		...continuation,
	},
} );

let paymentSetupCb;
let eventRegistration;

function renderComponent( config, props = {} ) {
	return render(
		createElement( V6ContinuationComponent, {
			config,
			eventRegistration,
			emitResponse,
			shippingData: { needsShipping: false },
			...props,
		} )
	);
}

beforeEach( () => {
	paymentSetupCb = null;
	eventRegistration = {
		onPaymentSetup: jest.fn( ( cb ) => {
			paymentSetupCb = cb;
			return () => {};
		} ),
	};
} );

describe( 'V6ContinuationComponent', () => {
	describe( 'payment setup', () => {
		test( 'hands the approved order and funding source to the gateway for an order continuation', () => {
			renderComponent(
				continuationConfig( { funding_source: 'venmo' } )
			);

			expect( paymentSetupCb() ).toEqual( {
				type: 'success',
				meta: {
					paymentMethodData: {
						paypal_order_id: 'ORDER1',
						funding_source: 'venmo',
					},
				},
			} );
		} );

		test( 'defaults the funding source to paypal when the continuation names none', () => {
			renderComponent( continuationConfig() );

			expect(
				paymentSetupCb().meta.paymentMethodData.funding_source
			).toBe( 'paypal' );
		} );

		test( 'returns success without meta for a saved free-trial account, which has no order to hand over', () => {
			renderComponent( continuationConfig( { free_trial: true } ) );

			expect( paymentSetupCb() ).toEqual( { type: 'success' } );
		} );
	} );
} );
