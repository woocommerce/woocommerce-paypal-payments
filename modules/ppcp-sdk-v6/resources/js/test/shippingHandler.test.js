jest.mock( '../endpointsAdapter', () => ( {
	updateShipping: jest.fn().mockResolvedValue( undefined ),
	updateCustomerAddress: jest.fn(),
	selectShippingRate: jest.fn(),
} ) );

import {
	handleShippingAddressChange,
	handleShippingOptionsChange,
} from '../sessions/shippingHandler';
import {
	updateShipping,
	updateCustomerAddress,
	selectShippingRate,
} from '../endpointsAdapter';

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

beforeEach( () => {
	updateShipping.mockClear();
	updateCustomerAddress.mockClear().mockResolvedValue( {} );
	selectShippingRate.mockClear().mockResolvedValue( {} );
} );

describe( 'handleShippingAddressChange', () => {
	test( 'maps v6 Orders-v2 address fields to WC state and city before patching the order', async () => {
		// v6 payloads name these adminArea1/adminArea2 (not state/city);
		// a wrong key silently posts empty fields and skews tax/shipping.
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

		expect( updateCustomerAddress ).toHaveBeenCalledWith( config, {
			country: 'US',
			state: 'CA',
			postcode: '94105',
			city: 'San Francisco',
		} );
		expect( updateShipping ).toHaveBeenCalledWith( config, 'ORDER1' );
	} );

	test( 'propagates Store API failures to the caller, without patching the order', async () => {
		updateCustomerAddress.mockRejectedValueOnce( new Error( 'down' ) );

		await expect(
			handleShippingAddressChange(
				{ orderId: 'O', shippingAddress: {} },
				config
			)
		).rejects.toThrow( 'down' );
		expect( updateShipping ).not.toHaveBeenCalled();
	} );
} );

describe( 'handleShippingOptionsChange', () => {
	test( 'selects the rate then patches the order', async () => {
		await handleShippingOptionsChange(
			{
				orderId: 'ORDER2',
				selectedShippingOption: { id: 'flat_rate:1' },
			},
			config
		);

		expect( selectShippingRate ).toHaveBeenCalledWith(
			config,
			'flat_rate:1'
		);
		expect( updateShipping ).toHaveBeenCalledWith( config, 'ORDER2' );
	} );

	test( 'skips rate selection without a selected option', async () => {
		await handleShippingOptionsChange( { orderId: 'ORDER3' }, config );

		expect( selectShippingRate ).not.toHaveBeenCalled();
		expect( updateShipping ).toHaveBeenCalledWith( config, 'ORDER3' );
	} );
} );
