jest.mock( '../endpointsAdapter', () => ( {
	updateShipping: jest.fn().mockResolvedValue( undefined ),
} ) );

import {
	handleShippingAddressChange,
	handleShippingOptionsChange,
} from '../sessions/shippingHandler';
import { updateShipping } from '../endpointsAdapter';

const config = {
	ajax: {
		wc_store_api: {
			update_customer: '/wp-json/wc/store/v1/cart/update-customer',
			select_shipping_rate:
				'/wp-json/wc/store/v1/cart/select-shipping-rate',
			nonce: 'store-nonce',
		},
	},
};

describe( 'handleShippingAddressChange', () => {
	beforeEach( () => {
		global.fetch = jest.fn().mockResolvedValue( { ok: true } );
		updateShipping.mockClear();
	} );

	test( 'maps v6 Orders-v2 address fields to WC state and city', async () => {
		// Regression: adminArea1/adminArea2 used to be dropped, posting
		// empty state/city and producing wrong tax/shipping totals.
		await handleShippingAddressChange(
			{
				orderId: 'ORDER1',
				shippingAddress: {
					countryCode: 'US',
					postalCode: '94105',
					adminArea1: 'CA',
					adminArea2: 'San Francisco',
				},
			},
			config
		);

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( config.ajax.wc_store_api.update_customer );
		expect( options.headers.Nonce ).toBe( 'store-nonce' );
		expect( JSON.parse( options.body ).shipping_address ).toEqual( {
			country: 'US',
			state: 'CA',
			postcode: '94105',
			city: 'San Francisco',
		} );
		expect( updateShipping ).toHaveBeenCalledWith( config, 'ORDER1' );
	} );

	test( 'propagates Store API failures to the caller', async () => {
		global.fetch = jest.fn().mockResolvedValue( { ok: false } );

		await expect(
			handleShippingAddressChange(
				{ orderId: 'O', shippingAddress: {} },
				config
			)
		).rejects.toThrow();
		expect( updateShipping ).not.toHaveBeenCalled();
	} );
} );

describe( 'handleShippingOptionsChange', () => {
	beforeEach( () => {
		global.fetch = jest.fn().mockResolvedValue( { ok: true } );
		updateShipping.mockClear();
	} );

	test( 'selects the rate then patches the order', async () => {
		await handleShippingOptionsChange(
			{
				orderId: 'ORDER2',
				selectedShippingOption: { id: 'flat_rate:1' },
			},
			config
		);

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( config.ajax.wc_store_api.select_shipping_rate );
		expect( JSON.parse( options.body ) ).toEqual( {
			rate_id: 'flat_rate:1',
		} );
		expect( updateShipping ).toHaveBeenCalledWith( config, 'ORDER2' );
	} );

	test( 'skips rate selection without a selected option', async () => {
		await handleShippingOptionsChange( { orderId: 'ORDER3' }, config );

		expect( global.fetch ).not.toHaveBeenCalled();
		expect( updateShipping ).toHaveBeenCalledWith( config, 'ORDER3' );
	} );
} );
