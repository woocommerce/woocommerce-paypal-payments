import {
	paypalSubscriberToWc,
	paypalSubscriptionToWcAddresses,
} from './Address';

const subscriber = ( overrides = {} ) => ( {
	email_address: 'jane@example.com',
	name: { given_name: 'Jane', surname: 'Doe' },
	payer_id: 'PAYER1',
	shipping_address: {
		address: {
			address_line_1: '1 Main St',
			address_line_2: 'Apt 2',
			admin_area_2: 'Springfield',
			admin_area_1: 'IL',
			postal_code: '62701',
			country_code: 'US',
		},
	},
	...overrides,
} );

describe( 'paypalSubscriberToWc()', () => {
	test( 'maps name, email and the shipping address of the subscriber', () => {
		expect( paypalSubscriberToWc( subscriber() ) ).toMatchObject( {
			first_name: 'Jane',
			last_name: 'Doe',
			email: 'jane@example.com',
			address_1: '1 Main St',
			address_2: 'Apt 2',
			city: 'Springfield',
			state: 'IL',
			postcode: '62701',
			country: 'US',
		} );
	} );

	test( 'returns only name and email when there is no shipping address', () => {
		const result = paypalSubscriberToWc(
			subscriber( { shipping_address: undefined } )
		);

		expect( result ).toEqual( {
			first_name: 'Jane',
			last_name: 'Doe',
			email: 'jane@example.com',
		} );
	} );
} );

describe( 'paypalSubscriptionToWcAddresses()', () => {
	test( 'uses the same address for billing and shipping', () => {
		const { billingAddress, shippingAddress } =
			paypalSubscriptionToWcAddresses( { subscriber: subscriber() } );

		expect( billingAddress ).toEqual( shippingAddress );
		expect( billingAddress.address_1 ).toBe( '1 Main St' );
	} );
} );
