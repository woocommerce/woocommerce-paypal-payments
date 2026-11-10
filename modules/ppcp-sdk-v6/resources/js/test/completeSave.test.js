const mockPostJson = jest.fn();
jest.mock( '../utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockNavigationAssign = jest.fn();
jest.mock( '../utils/navigation', () => ( {
	navigation: { assign: ( ...args ) => mockNavigationAssign( ...args ) },
} ) );

import { completeSave } from '../sessions/completeSave';

const baseConfig = ( overrides = {} ) => ( {
	payment_methods_page: '/my-account/payment-methods/',
	...overrides,
} );

const subscriptionChange = () => ( {
	subscription_id: 123,
	redirect_url: '/my-account/view-subscription/123/',
	ajax: { endpoint: '/change', nonce: 'n-change' },
} );

beforeEach( () => {
	jest.clearAllMocks();
	mockPostJson.mockReset();
} );

describe( 'completeSave', () => {
	test( 'navigates to the payment methods page without any request when not changing a subscription payment', async () => {
		await completeSave( baseConfig(), 42, 'ppcp-gateway' );

		expect( mockNavigationAssign ).toHaveBeenCalledWith(
			'/my-account/payment-methods/'
		);
		expect( mockPostJson ).not.toHaveBeenCalled();
	} );

	test( 'assigns the token to the subscription and navigates to its redirect url', async () => {
		const change = subscriptionChange();
		mockPostJson.mockResolvedValueOnce( {} );

		await completeSave(
			baseConfig( { subscription_change: change } ),
			42,
			'ppcp-gateway'
		);

		expect( mockPostJson ).toHaveBeenCalledWith( change.ajax, {
			subscription_id: 123,
			payment_method: 'ppcp-gateway',
			wc_payment_token_id: 42,
		} );
		expect( mockNavigationAssign ).toHaveBeenCalledWith(
			change.redirect_url
		);
	} );

	test.each( [ [ 0 ], [ null ], [ undefined ], [ '' ] ] )(
		'rejects without a request or navigation when the token id is %p',
		async ( wcTokenId ) => {
			await expect(
				completeSave(
					baseConfig( { subscription_change: subscriptionChange() } ),
					wcTokenId,
					'ppcp-gateway'
				)
			).rejects.toThrow();

			expect( mockPostJson ).not.toHaveBeenCalled();
			expect( mockNavigationAssign ).not.toHaveBeenCalled();
		}
	);

	test( 'rejects and does not navigate when the subscription change request fails', async () => {
		mockPostJson.mockRejectedValueOnce( new Error( 'change failed' ) );

		await expect(
			completeSave(
				baseConfig( { subscription_change: subscriptionChange() } ),
				42,
				'ppcp-gateway'
			)
		).rejects.toThrow( 'change failed' );

		expect( mockNavigationAssign ).not.toHaveBeenCalled();
	} );
} );
