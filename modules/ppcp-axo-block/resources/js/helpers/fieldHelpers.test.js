jest.mock( '@ppcp-axo/Helper/Debug', () => ( {
	log: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	dispatch: jest.fn(),
} ) );

import { dispatch } from '@wordpress/data';
import { populateWooFields } from './fieldHelpers';

function baseProfileData( overrides = {} ) {
	return {
		name: { firstName: 'Jane', lastName: 'Doe' },
		shippingAddress: {
			name: { firstName: 'Jane', lastName: 'Doe' },
			phoneNumber: { nationalNumber: '5551234567' },
			address: {
				addressLine1: '1 Main St',
				addressLine2: '',
				adminArea1: 'CA',
				adminArea2: 'Anytown',
				postalCode: '90210',
				countryCode: 'US',
			},
		},
		...overrides,
	};
}

describe( 'populateWooFields', () => {
	let setWooShippingAddress;
	let setWooBillingAddress;
	let checkoutDispatch;

	beforeEach( () => {
		jest.clearAllMocks();
		setWooShippingAddress = jest.fn();
		setWooBillingAddress = jest.fn();
		checkoutDispatch = {};
		dispatch.mockReturnValue( checkoutDispatch );
	} );

	test( 'sets the shipping address and skips billing when the profile has no saved card', () => {
		populateWooFields(
			baseProfileData(),
			setWooShippingAddress,
			setWooBillingAddress
		);

		expect( setWooShippingAddress ).toHaveBeenCalledWith(
			expect.objectContaining( {
				first_name: 'Jane',
				last_name: 'Doe',
				address_1: '1 Main St',
				city: 'Anytown',
				postcode: '90210',
				country: 'US',
			} )
		);
		expect( setWooBillingAddress ).not.toHaveBeenCalled();
	} );

	test( 'sets both the shipping and billing address when the profile has a saved card', () => {
		const profileData = baseProfileData( {
			card: {
				paymentSource: {
					card: {
						billingAddress: {
							addressLine1: '2 Card St',
							addressLine2: 'Suite 5',
							adminArea1: 'NY',
							adminArea2: 'Cardtown',
							postalCode: '10001',
							countryCode: 'US',
						},
					},
				},
			},
		} );

		populateWooFields(
			profileData,
			setWooShippingAddress,
			setWooBillingAddress
		);

		expect( setWooShippingAddress ).toHaveBeenCalledWith(
			expect.objectContaining( { address_1: '1 Main St' } )
		);
		expect( setWooBillingAddress ).toHaveBeenCalledWith(
			expect.objectContaining( {
				first_name: 'Jane',
				last_name: 'Doe',
				address_1: '2 Card St',
				address_2: 'Suite 5',
				city: 'Cardtown',
				state: 'NY',
				postcode: '10001',
				country: 'US',
			} )
		);
	} );
} );
