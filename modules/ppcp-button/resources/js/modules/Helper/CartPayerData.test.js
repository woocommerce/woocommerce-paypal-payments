import { cartPayerData } from './CartPayerData';

const setBillingAddress = ( billingAddress ) => {
	window.wp = {
		data: {
			select: jest.fn().mockReturnValue( {
				getCustomerData: jest.fn().mockReturnValue( {
					billingAddress,
				} ),
			} ),
		},
	};
};

describe( 'cartPayerData', () => {
	afterEach( () => {
		delete window.wp;
	} );

	it( 'returns null when there is no email, since Payer::to_array() would send two empty strings for name and risk the order being rejected', () => {
		setBillingAddress( { first_name: 'Jane', last_name: 'Doe' } );
		expect( cartPayerData() ).toBeNull();
	} );

	it( 'returns null when the email is an empty string', () => {
		setBillingAddress( { email: '' } );
		expect( cartPayerData() ).toBeNull();
	} );

	it( 'returns null when the email is whitespace only', () => {
		setBillingAddress( { email: '   ' } );
		expect( cartPayerData() ).toBeNull();
	} );

	it( 'returns null when window.wp is unavailable', () => {
		delete window.wp;
		expect( cartPayerData() ).toBeNull();
	} );

	it( 'returns null when window.wp.data is unavailable', () => {
		window.wp = {};
		expect( cartPayerData() ).toBeNull();
	} );

	it( 'returns null when window.wp.data.select is unavailable', () => {
		window.wp = { data: {} };
		expect( cartPayerData() ).toBeNull();
	} );

	it( 'returns null when getCustomerData() returns undefined', () => {
		window.wp = {
			data: {
				select: jest.fn().mockReturnValue( {
					getCustomerData: jest.fn().mockReturnValue( undefined ),
				} ),
			},
		};
		expect( cartPayerData() ).toBeNull();
	} );

	it( 'returns the email and name when billing has an email and full name', () => {
		setBillingAddress( {
			email: 'jane@example.com',
			first_name: 'Jane',
			last_name: 'Doe',
		} );

		expect( cartPayerData() ).toEqual( {
			email_address: 'jane@example.com',
			name: {
				given_name: 'Jane',
				surname: 'Doe',
			},
		} );
	} );

	it( 'fills missing name parts with empty strings rather than undefined', () => {
		setBillingAddress( { email: 'jane@example.com' } );

		expect( cartPayerData() ).toEqual( {
			email_address: 'jane@example.com',
			name: {
				given_name: '',
				surname: '',
			},
		} );
	} );

	it( 'trims the email address', () => {
		setBillingAddress( { email: '  jane@example.com  ' } );

		expect( cartPayerData().email_address ).toBe( 'jane@example.com' );
	} );

	it( 'includes the phone stripped to digits and capped at 14 characters', () => {
		setBillingAddress( {
			email: 'jane@example.com',
			phone: '+1 (555) 010-9999',
		} );

		expect( cartPayerData().phone ).toEqual( {
			phone_type: 'HOME',
			phone_number: { national_number: '15550109999' },
		} );
	} );

	it( 'omits the phone when no digits remain after stripping', () => {
		setBillingAddress( {
			email: 'jane@example.com',
			phone: '(none)',
		} );

		expect( cartPayerData().phone ).toBeUndefined();
	} );

	it( 'includes the address with defaults for missing parts when a country is present', () => {
		setBillingAddress( {
			email: 'jane@example.com',
			country: 'US',
		} );

		expect( cartPayerData().address ).toEqual( {
			country_code: 'US',
			address_line_1: '',
			address_line_2: '',
			admin_area_1: '',
			admin_area_2: '',
			postal_code: '',
		} );
	} );

	it( 'omits the address entirely when there is no country', () => {
		setBillingAddress( {
			email: 'jane@example.com',
			address_1: 'Main St 1',
			city: 'Anytown',
		} );

		expect( cartPayerData().address ).toBeUndefined();
	} );
} );
