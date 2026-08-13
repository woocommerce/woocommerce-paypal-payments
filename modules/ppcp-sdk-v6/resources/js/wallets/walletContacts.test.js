import { googlePayPayer, googlePayShippingAddress } from './walletContacts';

const googlePayResponse = ( overrides = {} ) => ( {
	email: 'jane@example.test',
	paymentMethodData: {
		info: {
			billingAddress: {
				name: 'Jane Van Doe',
				countryCode: 'US',
				address1: 'WooVille 12',
				address2: 'Suite 4',
				administrativeArea: 'IA',
				locality: 'Des Moines',
				postalCode: '12862',
			},
		},
	},
	...overrides,
} );

describe( 'googlePayPayer', () => {
	test( 'maps the Google Pay billing address keys to the PayPal address keys', () => {
		const { address } = googlePayPayer( googlePayResponse() );

		expect( address.country_code ).toBe( 'US' );
		expect( address.address_line_1 ).toBe( 'WooVille 12' );
		expect( address.address_line_2 ).toBe( 'Suite 4' );
		expect( address.admin_area_1 ).toBe( 'IA' );
		expect( address.admin_area_2 ).toBe( 'Des Moines' );
		expect( address.postal_code ).toBe( '12862' );
	} );

	test( 'maps the top-level email to email_address', () => {
		const payer = googlePayPayer( googlePayResponse() );

		expect( payer.email_address ).toBe( 'jane@example.test' );
	} );

	test.each( [
		[ 'Jane', 'Jane', '' ],
		[ 'Jane Doe', 'Jane', 'Doe' ],
		[ 'Jane Van Doe', 'Jane Van', 'Doe' ],
	] )(
		'splits the billing address name "%s" into given name "%s" and surname "%s"',
		( name, givenName, surname ) => {
			const { name: payerName } = googlePayPayer(
				googlePayResponse( {
					paymentMethodData: {
						info: { billingAddress: { name } },
					},
				} )
			);

			expect( payerName.given_name ).toBe( givenName );
			expect( payerName.surname ).toBe( surname );
		}
	);

	test( 'does not throw and returns empty name parts when paymentMethodData is absent', () => {
		expect( () =>
			googlePayPayer( { email: 'jane@example.test' } )
		).not.toThrow();

		const payer = googlePayPayer( { email: 'jane@example.test' } );

		expect( payer.name.given_name ).toBe( '' );
		expect( payer.name.surname ).toBe( '' );
		expect( payer.address.country_code ).toBeUndefined();
	} );
} );

describe( 'googlePayShippingAddress', () => {
	test( 'maps the shippingAddress when present, taking full_name from its name', () => {
		const response = {
			shippingAddress: {
				name: 'Jane Van Doe',
				countryCode: 'US',
				address1: 'WooVille 12',
				administrativeArea: 'IA',
				locality: 'Des Moines',
				postalCode: '12862',
			},
		};

		const shipping = googlePayShippingAddress( response );

		expect( shipping.name.full_name ).toBe( 'Jane Van Doe' );
		expect( shipping.address.country_code ).toBe( 'US' );
		expect( shipping.address.address_line_1 ).toBe( 'WooVille 12' );
		expect( shipping.address.admin_area_1 ).toBe( 'IA' );
		expect( shipping.address.admin_area_2 ).toBe( 'Des Moines' );
		expect( shipping.address.postal_code ).toBe( '12862' );
	} );

	test( 'falls back to the billing address when shippingAddress is absent', () => {
		const shipping = googlePayShippingAddress( googlePayResponse() );

		expect( shipping.name.full_name ).toBe( 'Jane Van Doe' );
		expect( shipping.address.country_code ).toBe( 'US' );
		expect( shipping.address.address_line_1 ).toBe( 'WooVille 12' );
	} );

	test( 'does not throw when neither shippingAddress nor billingAddress is present', () => {
		expect( () => googlePayShippingAddress( {} ) ).not.toThrow();

		const shipping = googlePayShippingAddress( {} );

		expect( shipping.name.full_name ).toBeUndefined();
		expect( shipping.address.country_code ).toBeUndefined();
	} );
} );
