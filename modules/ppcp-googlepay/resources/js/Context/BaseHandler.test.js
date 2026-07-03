/* global describe, test, expect, jest */

jest.mock( '@ppcp-button/ErrorHandler', () => jest.fn() );
jest.mock( '@ppcp-button/ActionHandler/CartActionHandler', () => jest.fn() );
jest.mock( '@ppcp-googlepay/Helper/TransactionInfo' );

import BaseHandler from './BaseHandler';
import PayNowHandler from './PayNowHandler';

describe( 'BaseHandler', () => {
	describe( 'validateContext()', () => {
		test( 'returns true when no subscription product is present', () => {
			const handler = new BaseHandler( {}, {} );

			expect( handler.validateContext() ).toBe( true );
		} );

		test( 'returns false when the cart contains a subscription product', () => {
			const ppcpConfig = {
				locations_with_subscription_product: { cart: true },
			};
			const handler = new BaseHandler( {}, ppcpConfig );

			expect( handler.validateContext() ).toBe( false );
		} );

		test( 'returns false for a pay-for-order subscription renewal when manual renewals are not accepted', () => {
			const ppcpConfig = {
				locations_with_subscription_product: { payorder: true },
				subscriptions_accept_manual_renewals: false,
			};
			const handler = new BaseHandler( {}, ppcpConfig );

			expect( handler.validateContext() ).toBe( false );
		} );

		test( 'returns true for a pay-for-order subscription renewal when manual renewals are accepted', () => {
			const ppcpConfig = {
				locations_with_subscription_product: { payorder: true },
				subscriptions_accept_manual_renewals: true,
			};
			const handler = new BaseHandler( {}, ppcpConfig );

			expect( handler.validateContext() ).toBe( true );
		} );

		test( 'prioritizes the pay-for-order check over the cart check', () => {
			const ppcpConfig = {
				locations_with_subscription_product: {
					payorder: true,
					cart: true,
				},
				subscriptions_accept_manual_renewals: true,
			};
			const handler = new BaseHandler( {}, ppcpConfig );

			expect( handler.validateContext() ).toBe( true );
		} );
	} );
} );

describe( 'PayNowHandler', () => {
	describe( 'validateContext()', () => {
		test( 'inherits the pay-for-order subscription check from BaseHandler', () => {
			const allowedConfig = {
				locations_with_subscription_product: { payorder: true },
				subscriptions_accept_manual_renewals: true,
			};
			const blockedConfig = {
				locations_with_subscription_product: { payorder: true },
				subscriptions_accept_manual_renewals: false,
			};
			const allowedHandler = new PayNowHandler( {}, allowedConfig );
			const blockedHandler = new PayNowHandler( {}, blockedConfig );

			expect( allowedHandler.validateContext() ).toBe( true );
			expect( blockedHandler.validateContext() ).toBe( false );
		} );
	} );
} );
