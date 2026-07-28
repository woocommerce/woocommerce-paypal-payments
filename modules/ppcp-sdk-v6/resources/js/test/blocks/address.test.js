import { paypalOrderToWcAddresses } from '../../blocks/address';

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

	test( 'splits a full name into first and remaining parts', () => {
		const { shippingAddress } = paypalOrderToWcAddresses(
			shippingOnlyOrder
		);

		expect( shippingAddress.first_name ).toBe( 'John' );
		expect( shippingAddress.last_name ).toBe( 'Van Doe' );
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
