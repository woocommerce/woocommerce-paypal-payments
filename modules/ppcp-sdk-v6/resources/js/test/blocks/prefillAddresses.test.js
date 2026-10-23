import {
	prefillFromPayPalOrder,
	prefillFromPayPalSubscriber,
} from '../../blocks/prefillAddresses';

const order = {
	purchase_units: [
		{
			shipping: {
				name: { full_name: 'John Doe' },
				address: {
					address_line_1: 'WooVille 12',
					admin_area_2: 'Dubai',
					postal_code: '12862',
					country_code: 'US',
				},
			},
		},
	],
};

const subscriber = {
	name: { given_name: 'Jane', surname: 'Roe' },
	email_address: 'jane@example.test',
	shipping_address: {
		address: { address_line_1: 'Sub Street 1', country_code: 'DE' },
	},
};

let cart;
let customerData;

beforeEach( () => {
	customerData = {
		billingAddress: { company: 'Acme', phone: '555', address_1: 'Old' },
		shippingAddress: { company: 'Acme', address_1: 'Old' },
	};
	cart = {
		updateCustomerData: jest.fn().mockResolvedValue( undefined ),
		setBillingAddress: jest.fn(),
		setShippingAddress: jest.fn(),
	};
	global.wp = {
		data: {
			select: () => ( { getCustomerData: () => customerData } ),
			dispatch: () => cart,
		},
	};
} );

afterEach( () => {
	delete global.wp;
} );

describe.each( [
	[ 'prefillFromPayPalOrder', prefillFromPayPalOrder, order, 'WooVille 12' ],
	[
		'prefillFromPayPalSubscriber',
		prefillFromPayPalSubscriber,
		subscriber,
		'Sub Street 1',
	],
] )( '%s', ( name, prefill, source, address1 ) => {
	test( 'stores the billing address merged over the cart one, keeping values PayPal does not carry', async () => {
		await prefill( source );

		const { billing_address: billing } =
			cart.updateCustomerData.mock.calls[ 0 ][ 0 ];
		expect( billing.address_1 ).toBe( address1 );
		expect( billing.company ).toBe( 'Acme' );
		expect( billing.phone ).toBe( '555' );
	} );

	test( 'stores no shipping address unless the cart ships', async () => {
		await prefill( source );

		expect(
			cart.updateCustomerData.mock.calls[ 0 ][ 0 ].shipping_address
		).toBeUndefined();
	} );

	test( 'stores the shipping address when the cart ships', async () => {
		await prefill( source, { needsShipping: true } );

		expect(
			cart.updateCustomerData.mock.calls[ 0 ][ 0 ].shipping_address
				.address_1
		).toBe( address1 );
	} );

	test( 'leaves the Blocks form state alone unless asked to reflect it', async () => {
		await prefill( source, { needsShipping: true } );

		expect( cart.setBillingAddress ).not.toHaveBeenCalled();
		expect( cart.setShippingAddress ).not.toHaveBeenCalled();
	} );

	test( 'reflects both addresses in the Blocks form state when asked and the cart ships', async () => {
		await prefill( source, { needsShipping: true, reflectInUi: true } );

		expect( cart.setBillingAddress.mock.calls[ 0 ][ 0 ].address_1 ).toBe(
			address1
		);
		expect( cart.setShippingAddress.mock.calls[ 0 ][ 0 ].address_1 ).toBe(
			address1
		);
	} );

	test( 'reflects only the billing address when the cart does not ship', async () => {
		await prefill( source, { reflectInUi: true } );

		expect( cart.setBillingAddress ).toHaveBeenCalled();
		expect( cart.setShippingAddress ).not.toHaveBeenCalled();
	} );
} );

describe( 'prefillFromPayPalSubscriber', () => {
	test( 'stores the subscriber email and name on the billing address', async () => {
		await prefillFromPayPalSubscriber( subscriber );

		expect(
			cart.updateCustomerData.mock.calls[ 0 ][ 0 ].billing_address
		).toMatchObject( {
			first_name: 'Jane',
			last_name: 'Roe',
			email: 'jane@example.test',
		} );
	} );
} );
