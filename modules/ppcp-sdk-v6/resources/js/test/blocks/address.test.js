import {
	paypalOrderToWcAddresses,
	paypalSubscriberToWcAddresses,
} from '../../blocks/address';

const subscriber = {
	name: { given_name: 'Jane', surname: 'Roe' },
	email_address: 'jane@example.test',
	shipping_address: {
		address: {
			address_line_1: 'Sub Street 1',
			admin_area_1: 'CA',
			admin_area_2: 'Los Angeles',
			postal_code: '90001',
			country_code: 'US',
		},
	},
};

describe( 'paypalSubscriberToWcAddresses', () => {
	test( 'maps the subscriber address and name to WC shipping fields', () => {
		const { shippingAddress } = paypalSubscriberToWcAddresses( subscriber );

		expect( shippingAddress ).toMatchObject( {
			first_name: 'Jane',
			last_name: 'Roe',
			address_1: 'Sub Street 1',
			state: 'CA',
			city: 'Los Angeles',
			postcode: '90001',
			country: 'US',
		} );
		expect( shippingAddress.email ).toBeUndefined();
	} );

	test( 'uses the same address for billing and adds the email', () => {
		const { billingAddress, shippingAddress } =
			paypalSubscriberToWcAddresses( subscriber );

		expect( billingAddress ).toEqual( {
			...shippingAddress,
			email: 'jane@example.test',
		} );
		expect( billingAddress ).not.toBe( shippingAddress );
	} );

	test( 'keeps the name and email when the subscriber has no address', () => {
		const { billingAddress } = paypalSubscriberToWcAddresses( {
			name: { given_name: 'Jane', surname: 'Roe' },
			email_address: 'jane@example.test',
		} );

		expect( billingAddress ).toEqual( {
			first_name: 'Jane',
			last_name: 'Roe',
			email: 'jane@example.test',
		} );
	} );

	test.each( [
		[ 'undefined', undefined ],
		[ 'null', null ],
		[ 'empty', {} ],
	] )( 'returns blank names and email for an %s subscriber', ( label, input ) => {
		const { billingAddress, shippingAddress } =
			paypalSubscriberToWcAddresses( input );

		expect( shippingAddress ).toEqual( { first_name: '', last_name: '' } );
		expect( billingAddress ).toEqual( {
			first_name: '',
			last_name: '',
			email: '',
		} );
	} );
} );

const shippingOnlyOrder = {
	id: 'PPORDER',
	purchase_units: [
		{
			shipping: {
				name: { full_name: 'John Van Doe' },
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
};

describe( 'paypalOrderToWcAddresses', () => {
	test( 'maps the Orders v2 shipping block to WC fields', () => {
		const { shippingAddress } = paypalOrderToWcAddresses(
			shippingOnlyOrder
		);

		expect( shippingAddress.address_1 ).toBe( 'WooVille 12' );
		expect( shippingAddress.state ).toBe( 'IA' );
		expect( shippingAddress.city ).toBe( 'Dubai' );
		expect( shippingAddress.postcode ).toBe( '12862' );
		expect( shippingAddress.country ).toBe( 'US' );
	} );

	test( 'splits the shipping full name into WC first and last name fields', () => {
		const { shippingAddress } = paypalOrderToWcAddresses(
			shippingOnlyOrder
		);

		// 'Van Doe' is a multi-token surname that the last-token rule
		// misidentifies; accepted trade-off, see utils/name.test.js.
		expect( shippingAddress.first_name ).toBe( 'John Van' );
		expect( shippingAddress.last_name ).toBe( 'Doe' );
	} );

	test( 'returns independent billing and shipping objects when there is no payer', () => {
		const { billingAddress, shippingAddress } =
			paypalOrderToWcAddresses( shippingOnlyOrder );

		// Same values, but callers dispatch these as separate payloads — they
		// must not be the same reference.
		expect( billingAddress ).toEqual( shippingAddress );
		expect( billingAddress ).not.toBe( shippingAddress );

		billingAddress.city = 'Mutated';
		expect( shippingAddress.city ).toBe( 'Dubai' );
	} );

	test( 'prefers payer details for billing when present', () => {
		const { billingAddress, shippingAddress } = paypalOrderToWcAddresses( {
			...shippingOnlyOrder,
			payer: {
				name: { given_name: 'Jane', surname: 'Roe' },
				email_address: 'jane@example.test',
				address: {
					address_line_1: 'Payer Street 1',
					country_code: 'DE',
				},
			},
		} );

		expect( billingAddress.first_name ).toBe( 'Jane' );
		expect( billingAddress.address_1 ).toBe( 'Payer Street 1' );
		expect( billingAddress.email ).toBe( 'jane@example.test' );
		// Shipping is untouched by payer data.
		expect( shippingAddress.address_1 ).toBe( 'WooVille 12' );
	} );

	test( 'falls back to the shipping address when the payer has no address', () => {
		const { billingAddress } = paypalOrderToWcAddresses( {
			...shippingOnlyOrder,
			payer: {
				name: { given_name: 'Jane', surname: 'Roe' },
				email_address: 'jane@example.test',
			},
		} );

		// Payer names win, but the shipping country must not be blanked out.
		expect( billingAddress.first_name ).toBe( 'Jane' );
		expect( billingAddress.address_1 ).toBe( 'WooVille 12' );
		expect( billingAddress.country ).toBe( 'US' );
	} );
} );
