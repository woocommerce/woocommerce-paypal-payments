import {
	googlePayPayer,
	googlePayShippingAddress,
	googlePayWcShippingAddress,
	googlePayWcBillingAddress,
	applePayPayer,
	applePayShippingAddress,
	applePayWcShippingAddress,
	applePayWcBillingAddress,
	walletAddressToWc,
	wcAddressToApplePay,
} from './walletContacts';

describe( 'walletAddressToWc()', () => {
	test( 'maps countryCode/administrativeArea/postalCode/locality to WC address fields', () => {
		expect(
			walletAddressToWc( {
				countryCode: 'US',
				administrativeArea: 'CA',
				postalCode: '94105',
				locality: 'San Francisco',
			} )
		).toEqual( {
			country: 'US',
			state: 'CA',
			postcode: '94105',
			city: 'San Francisco',
		} );
	} );

	test( 'defaults every field to an empty string when the source carries none of them', () => {
		expect( walletAddressToWc( {} ) ).toEqual( {
			country: '',
			state: '',
			postcode: '',
			city: '',
		} );
	} );

	test( 'does not throw and defaults every field when the source is absent', () => {
		expect( () => walletAddressToWc( null ) ).not.toThrow();
		expect( walletAddressToWc( undefined ) ).toEqual( {
			country: '',
			state: '',
			postcode: '',
			city: '',
		} );
	} );
} );

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

describe( 'googlePayWcShippingAddress', () => {
	test( 'maps the shippingAddress to complete WC fields, including administrativeArea to state', () => {
		const address = googlePayWcShippingAddress( {
			shippingAddress: {
				name: 'Jane Van Doe',
				countryCode: 'US',
				address1: 'WooVille 12',
				address2: 'Suite 4',
				administrativeArea: 'IA',
				locality: 'Des Moines',
				postalCode: '12862',
			},
		} );

		expect( address ).toEqual( {
			country: 'US',
			state: 'IA',
			postcode: '12862',
			city: 'Des Moines',
			address_1: 'WooVille 12',
			address_2: 'Suite 4',
			first_name: 'Jane Van',
			last_name: 'Doe',
		} );
	} );

	test( 'defaults every field to an empty string when shippingAddress is absent', () => {
		expect( googlePayWcShippingAddress( {} ) ).toEqual( {
			country: '',
			state: '',
			postcode: '',
			city: '',
			address_1: '',
			address_2: '',
			first_name: '',
			last_name: '',
		} );
	} );

	test( 'does not throw when the response is absent', () => {
		expect( () => googlePayWcShippingAddress( null ) ).not.toThrow();
		expect( () => googlePayWcShippingAddress( undefined ) ).not.toThrow();
	} );
} );

describe( 'googlePayWcBillingAddress', () => {
	test( 'maps the billing address to complete WC fields, including administrativeArea to state', () => {
		const address = googlePayWcBillingAddress( {
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
		} );

		expect( address ).toEqual( {
			country: 'US',
			state: 'IA',
			postcode: '12862',
			city: 'Des Moines',
			address_1: 'WooVille 12',
			address_2: 'Suite 4',
			first_name: 'Jane Van',
			last_name: 'Doe',
		} );
	} );

	test( 'defaults every field to an empty string when the wallet returns no billing address', () => {
		expect( googlePayWcBillingAddress( {} ) ).toEqual( {
			country: '',
			state: '',
			postcode: '',
			city: '',
			address_1: '',
			address_2: '',
			first_name: '',
			last_name: '',
		} );
	} );

	test( 'does not throw when the response is absent', () => {
		expect( () => googlePayWcBillingAddress( null ) ).not.toThrow();
		expect( () => googlePayWcBillingAddress( undefined ) ).not.toThrow();
	} );
} );

const applePayPayment = ( overrides = {} ) => ( {
	billingContact: {
		givenName: 'Jane',
		familyName: 'Doe',
		countryCode: 'US',
		addressLines: [ 'WooVille 12', 'Suite 4' ],
		administrativeArea: 'IA',
		locality: 'Des Moines',
		postalCode: '12862',
	},
	shippingContact: {
		emailAddress: 'jane@example.test',
		givenName: 'Jane',
		familyName: 'Doe',
		countryCode: 'US',
		addressLines: [ 'WooVille 12', 'Suite 4' ],
		administrativeArea: 'IA',
		locality: 'Des Moines',
		postalCode: '12862',
	},
	...overrides,
} );

describe( 'applePayPayer', () => {
	test( 'maps the billing contact address keys to the PayPal address keys', () => {
		const { address } = applePayPayer( applePayPayment() );

		expect( address.country_code ).toBe( 'US' );
		expect( address.address_line_1 ).toBe( 'WooVille 12' );
		expect( address.address_line_2 ).toBe( 'Suite 4' );
		expect( address.admin_area_1 ).toBe( 'IA' );
		expect( address.admin_area_2 ).toBe( 'Des Moines' );
		expect( address.postal_code ).toBe( '12862' );
	} );

	test( 'takes given_name and surname straight from the billing contact, unsplit', () => {
		const { name } = applePayPayer( applePayPayment() );

		expect( name.given_name ).toBe( 'Jane' );
		expect( name.surname ).toBe( 'Doe' );
	} );

	test( 'takes email_address from the shipping contact, because Apple never returns a billing email', () => {
		const payer = applePayPayer( applePayPayment() );

		expect( payer.email_address ).toBe( 'jane@example.test' );
	} );

	test( 'does not throw and leaves fields undefined when neither contact is present', () => {
		expect( () => applePayPayer( {} ) ).not.toThrow();

		const payer = applePayPayer( {} );

		expect( payer.email_address ).toBeUndefined();
		expect( payer.name.given_name ).toBeUndefined();
		expect( payer.name.surname ).toBeUndefined();
		expect( payer.address.country_code ).toBeUndefined();
	} );
} );

describe( 'applePayShippingAddress', () => {
	test( 'maps the shipping contact when it carries a countryCode', () => {
		const shipping = applePayShippingAddress( applePayPayment() );

		expect( shipping.name.full_name ).toBe( 'Jane Doe' );
		expect( shipping.address.country_code ).toBe( 'US' );
		expect( shipping.address.address_line_1 ).toBe( 'WooVille 12' );
		expect( shipping.address.address_line_2 ).toBe( 'Suite 4' );
		expect( shipping.address.admin_area_1 ).toBe( 'IA' );
		expect( shipping.address.admin_area_2 ).toBe( 'Des Moines' );
		expect( shipping.address.postal_code ).toBe( '12862' );
	} );

	test( 'falls back to the billing contact when the shipping contact has no countryCode, as on classic checkout', () => {
		const shipping = applePayShippingAddress(
			applePayPayment( {
				shippingContact: { emailAddress: 'jane@example.test' },
			} )
		);

		expect( shipping.address.country_code ).toBe( 'US' );
		expect( shipping.address.address_line_1 ).toBe( 'WooVille 12' );
		expect( shipping.name.full_name ).toBe( 'Jane Doe' );
	} );

	test( 'does not throw when neither contact is present', () => {
		expect( () => applePayShippingAddress( {} ) ).not.toThrow();

		const shipping = applePayShippingAddress( {} );

		expect( shipping.name.full_name ).toBeUndefined();
		expect( shipping.address.country_code ).toBeUndefined();
	} );

	test( 'omits the full name when the fallback billing contact has no name parts', () => {
		const shipping = applePayShippingAddress( {
			shippingContact: { emailAddress: 'jane@example.test' },
			billingContact: { countryCode: 'US' },
		} );

		expect( shipping.name.full_name ).toBeUndefined();
		expect( shipping.address.country_code ).toBe( 'US' );
	} );
} );

describe( 'applePayWcShippingAddress', () => {
	test( 'maps the shippingContact to complete WC fields, including administrativeArea to state', () => {
		const address = applePayWcShippingAddress( {
			shippingContact: {
				givenName: 'Jane',
				familyName: 'Doe',
				countryCode: 'US',
				addressLines: [ 'WooVille 12', 'Suite 4' ],
				administrativeArea: 'IA',
				locality: 'Des Moines',
				postalCode: '12862',
			},
		} );

		expect( address ).toEqual( {
			country: 'US',
			state: 'IA',
			postcode: '12862',
			city: 'Des Moines',
			address_1: 'WooVille 12',
			address_2: 'Suite 4',
			first_name: 'Jane',
			last_name: 'Doe',
		} );
	} );

	test( 'defaults every field to an empty string when shippingContact is absent', () => {
		expect( applePayWcShippingAddress( {} ) ).toEqual( {
			country: '',
			state: '',
			postcode: '',
			city: '',
			address_1: '',
			address_2: '',
			first_name: '',
			last_name: '',
		} );
	} );

	test( 'does not throw when the payment is absent', () => {
		expect( () => applePayWcShippingAddress( null ) ).not.toThrow();
		expect( () => applePayWcShippingAddress( undefined ) ).not.toThrow();
	} );
} );

describe( 'applePayWcBillingAddress', () => {
	test( 'maps the billingContact to complete WC fields, including administrativeArea to state', () => {
		const address = applePayWcBillingAddress( {
			billingContact: {
				givenName: 'Jane',
				familyName: 'Doe',
				countryCode: 'US',
				addressLines: [ 'WooVille 12', 'Suite 4' ],
				administrativeArea: 'IA',
				locality: 'Des Moines',
				postalCode: '12862',
			},
		} );

		expect( address ).toEqual( {
			country: 'US',
			state: 'IA',
			postcode: '12862',
			city: 'Des Moines',
			address_1: 'WooVille 12',
			address_2: 'Suite 4',
			first_name: 'Jane',
			last_name: 'Doe',
		} );
	} );

	test( 'defaults every field to an empty string when the wallet returns no billing contact', () => {
		expect( applePayWcBillingAddress( {} ) ).toEqual( {
			country: '',
			state: '',
			postcode: '',
			city: '',
			address_1: '',
			address_2: '',
			first_name: '',
			last_name: '',
		} );
	} );

	test( 'does not throw when the payment is absent', () => {
		expect( () => applePayWcBillingAddress( null ) ).not.toThrow();
		expect( () => applePayWcBillingAddress( undefined ) ).not.toThrow();
	} );
} );

const wcAddress = ( overrides = {} ) => ( {
	country: 'US',
	state: 'IA',
	postcode: '12862',
	city: 'Des Moines',
	address_1: 'WooVille 12',
	address_2: 'Suite 4',
	first_name: 'Jane',
	last_name: 'Doe',
	...overrides,
} );

describe( 'wcAddressToApplePay()', () => {
	test.each( [
		[ undefined ],
		[ null ],
		[ {} ],
		[ { country: '' } ],
	] )( 'returns undefined when the address has no country (%j)', ( address ) => {
		expect( wcAddressToApplePay( address ) ).toBeUndefined();
	} );

	test( 'maps country/state/postcode/city to countryCode/administrativeArea/postalCode/locality', () => {
		const contact = wcAddressToApplePay( wcAddress() );

		expect( contact.countryCode ).toBe( 'US' );
		expect( contact.administrativeArea ).toBe( 'IA' );
		expect( contact.postalCode ).toBe( '12862' );
		expect( contact.locality ).toBe( 'Des Moines' );
	} );

	test( 'maps first_name/last_name to givenName/familyName', () => {
		const contact = wcAddressToApplePay( wcAddress() );

		expect( contact.givenName ).toBe( 'Jane' );
		expect( contact.familyName ).toBe( 'Doe' );
	} );

	test( 'puts address_1 and address_2 into addressLines, in order', () => {
		const contact = wcAddressToApplePay( wcAddress() );

		expect( contact.addressLines ).toEqual( [
			'WooVille 12',
			'Suite 4',
		] );
	} );

	test( 'skips an empty address_2 in addressLines rather than keeping an empty entry', () => {
		const contact = wcAddressToApplePay(
			wcAddress( { address_2: '' } )
		);

		expect( contact.addressLines ).toEqual( [ 'WooVille 12' ] );
	} );

	test( 'includes emailAddress and phoneNumber only when the address carries them', () => {
		const withoutContact = wcAddressToApplePay( wcAddress() );

		expect( withoutContact ).not.toHaveProperty( 'emailAddress' );
		expect( withoutContact ).not.toHaveProperty( 'phoneNumber' );

		const withContact = wcAddressToApplePay(
			wcAddress( { email: 'jane@example.test', phone: '555-0100' } )
		);

		expect( withContact.emailAddress ).toBe( 'jane@example.test' );
		expect( withContact.phoneNumber ).toBe( '555-0100' );
	} );

	test( 'defaults state/postcode/city and name parts to empty strings when absent', () => {
		const contact = wcAddressToApplePay( { country: 'US' } );

		expect( contact.administrativeArea ).toBe( '' );
		expect( contact.postalCode ).toBe( '' );
		expect( contact.locality ).toBe( '' );
		expect( contact.givenName ).toBe( '' );
		expect( contact.familyName ).toBe( '' );
		expect( contact.addressLines ).toEqual( [] );
	} );
} );
