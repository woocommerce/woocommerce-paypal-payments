jest.mock( './Components/paypal', () => ( {
	shouldEnableAppSwitch: jest.fn( () => false ),
} ) );
jest.mock( '@ppcp-button/Helper/CartPayerData', () => ( {
	cartPayerData: jest.fn(),
} ) );

import { handleApproveSubscription } from './paypal-config';

const config = {
	scriptData: {
		ajax: { approve_subscription: { endpoint: 'x', nonce: 'n' } },
		labels: { error: { generic: 'err' } },
	},
};

const subscription = {
	subscriber: {
		email_address: 'jane@example.com',
		name: { given_name: 'Jane', surname: 'Doe' },
		shipping_address: {
			address: {
				address_line_1: '1 Main St',
				admin_area_2: 'Springfield',
				admin_area_1: 'IL',
				postal_code: '62701',
				country_code: 'US',
			},
		},
	},
};

describe( 'handleApproveSubscription()', () => {
	let cart;
	let onSubmit;
	let originalFetch;
	let originalWp;

	const approve = ( shippingData ) =>
		handleApproveSubscription(
			{ orderID: 'O1', subscriptionID: 'S1' },
			{
				subscription: {
					get: jest.fn().mockResolvedValue( subscription ),
				},
			},
			config,
			shippingData,
			jest.fn(),
			jest.fn(),
			jest.fn(),
			jest.fn(),
			onSubmit,
			jest.fn(),
			jest.fn()
		);

	beforeEach( () => {
		jest.clearAllMocks();
		originalFetch = global.fetch;
		originalWp = global.wp;
		global.fetch = jest.fn().mockResolvedValue( {
			json: () => ( { success: true } ),
		} );
		cart = {
			updateCustomerData: jest.fn().mockResolvedValue( undefined ),
			setBillingAddress: jest.fn().mockResolvedValue( undefined ),
			setShippingAddress: jest.fn().mockResolvedValue( undefined ),
		};
		global.wp = { data: { dispatch: jest.fn( () => cart ) } };
		onSubmit = jest.fn();
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		global.wp = originalWp;
	} );

	test( 'sets billing and shipping addresses in the UI and submits when shipping is needed', async () => {
		await approve( { needsShipping: true } );

		const expected = expect.objectContaining( {
			email: 'jane@example.com',
			address_1: '1 Main St',
		} );
		expect( cart.setBillingAddress ).toHaveBeenCalledWith( expected );
		expect( cart.setShippingAddress ).toHaveBeenCalledWith( expected );
		expect( onSubmit ).toHaveBeenCalled();
	} );

	test( 'sets only the billing address in the UI when shipping is not needed', async () => {
		await approve( { needsShipping: false } );

		expect( cart.setBillingAddress ).toHaveBeenCalledWith(
			expect.objectContaining( { email: 'jane@example.com' } )
		);
		expect( cart.setShippingAddress ).not.toHaveBeenCalled();
		expect( onSubmit ).toHaveBeenCalled();
	} );
} );
