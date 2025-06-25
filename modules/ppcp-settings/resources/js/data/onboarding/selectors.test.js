import '@testing-library/jest-dom';

import { PRODUCT_TYPES } from './configuration';
import { determineProductsAndCaps } from './selectors';

describe( 'determineProductsAndCaps selector [casual seller]', () => {
	const testCases = [
		{
			name: 'should return EXPRESS_CHECKOUT when card payments are not available',
			state: {
				data: {
					isCasualSeller: true,
					areOptionalPaymentMethodsEnabled: true,
				},
				flags: { canUseCardPayments: false, canUseVaulting: false },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: false },
			},
		},
		{
			name: 'should return EXPRESS_CHECKOUT when optional payment methods are disabled',
			state: {
				data: {
					isCasualSeller: true,
					areOptionalPaymentMethodsEnabled: false,
				},
				flags: { canUseCardPayments: true, canUseVaulting: false },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: false },
			},
		},
		{
			name: 'should return EXPRESS_CHECKOUT for casual sellers with card payments',
			state: {
				data: {
					isCasualSeller: true,
					areOptionalPaymentMethodsEnabled: true,
				},
				flags: { canUseCardPayments: true, canUseVaulting: false },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: true },
			},
		},
		{
			name: 'should return EXPRESS_CHECKOUT and ADVANCED_VAULTING when card payments are not available but vaulting is',
			state: {
				data: {
					isCasualSeller: true,
					areOptionalPaymentMethodsEnabled: true,
				},
				flags: { canUseCardPayments: false, canUseVaulting: true },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: false },
			},
		},
		{
			name: 'should ignore SUBSCRIPTION product for casual sellers',
			state: {
				data: {
					isCasualSeller: true,
					areOptionalPaymentMethodsEnabled: true,
					products: [ PRODUCT_TYPES.SUBSCRIPTIONS ],
				},
				flags: { canUseCardPayments: false, canUseVaulting: true },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: false },
			},
		},
	];

	test.each( testCases )( '$name', ( { state, expected } ) => {
		const result = determineProductsAndCaps( state );
		expect( result ).toEqual( expected );
	} );
} );

describe( 'determineProductsAndCaps selector [business seller]', () => {
	const testCases = [
		{
			name: 'should return EXPRESS_CHECKOUT when card payments are not available',
			state: {
				data: {
					isCasualSeller: false,
					areOptionalPaymentMethodsEnabled: true,
				},
				flags: { canUseCardPayments: false, canUseVaulting: false },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: false },
			},
		},
		{
			name: 'should return EXPRESS_CHECKOUT when optional payment methods are disabled',
			state: {
				data: {
					isCasualSeller: false,
					areOptionalPaymentMethodsEnabled: false,
				},
				flags: { canUseCardPayments: true, canUseVaulting: false },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: false },
			},
		},
		{
			name: 'should return PPCP for business merchants with card payments',
			state: {
				data: {
					isCasualSeller: false,
					areOptionalPaymentMethodsEnabled: true,
				},
				flags: { canUseCardPayments: true, canUseVaulting: false },
			},
			expected: {
				products: [ 'PPCP' ],
				options: { useSubscriptions: false, useCardPayments: true },
			},
		},
		{
			name: 'should include ADVANCED_VAULTING when vaulting is available',
			state: {
				data: {
					isCasualSeller: false,
					areOptionalPaymentMethodsEnabled: true,
				},
				flags: { canUseCardPayments: true, canUseVaulting: true },
			},
			expected: {
				products: [ 'PPCP', 'ADVANCED_VAULTING' ],
				options: { useSubscriptions: false, useCardPayments: true },
			},
		},
		{
			name: 'should return EXPRESS_CHECKOUT and ADVANCED_VAULTING when card payments are not available but vaulting is',
			state: {
				data: {
					isCasualSeller: false,
					areOptionalPaymentMethodsEnabled: true,
					products: [ PRODUCT_TYPES.VIRTUAL ],
				},
				flags: { canUseCardPayments: false, canUseVaulting: true },
			},
			expected: {
				products: [ 'EXPRESS_CHECKOUT' ],
				options: { useSubscriptions: false, useCardPayments: false },
			},
		},
		{
			name: 'should enable the SUBSCRIPTIONS option when a business seller selects the subscriptions-product',
			state: {
				data: {
					isCasualSeller: false,
					areOptionalPaymentMethodsEnabled: true,
					products: [ PRODUCT_TYPES.SUBSCRIPTIONS ],
				},
				flags: { canUseCardPayments: true, canUseVaulting: true },
			},
			expected: {
				products: [ 'PPCP', 'ADVANCED_VAULTING' ],
				options: { useSubscriptions: true, useCardPayments: true },
			},
		},
	];

	test.each( testCases )( '$name', ( { state, expected } ) => {
		const result = determineProductsAndCaps( state );
		expect( result ).toEqual( expected );
	} );
} );
