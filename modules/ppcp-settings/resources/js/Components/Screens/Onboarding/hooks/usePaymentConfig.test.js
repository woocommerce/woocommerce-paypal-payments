/* global describe, test, expect, jest */
import { renderHook } from '@testing-library/react';
import { usePaymentConfig } from './usePaymentConfig';

jest.mock( '../../../../data', () => ( {
	initStores: jest.fn(),
} ) );

const EXPECTED_PAYMENT_METHODS = [
	[
		'US',
		[ 'PayWithPayPal', 'PayLater', 'Venmo', 'Crypto' ],
		[ 'CardFields', 'DigitalWallets', 'APMs', 'Fastlane' ],
	],
	[
		'GB',
		[ 'PayWithPayPal', 'PayInThree' ],
		[ 'CardFields', 'DigitalWallets', 'APMs', 'Fastlane' ],
	],
	[
		'AU',
		[ 'PayWithPayPal', 'PayLater' ],
		[ 'CardFields', 'DigitalWallets', 'APMs', 'Fastlane' ],
	],
	[ 'MX', [ 'PayWithPayPal', 'PayLater' ], [ 'CreditDebitCards', 'APMs' ] ],
];

describe( 'usePaymentConfig hook', () => {
	describe( 'Payment Methods for countries', () => {
		test.each( EXPECTED_PAYMENT_METHODS )(
			'Country %s should have valid methods',
			( country, includedMethods, optionalMethods ) => {
				const { result } = renderHook( () =>
					usePaymentConfig( country, true, true, false )
				);

				expect( result.current.includedMethods ).toHaveLength(
					includedMethods.length
				);
				expect(
					result.current.includedMethods.map(
						( method ) => method.name
					)
				).toEqual( includedMethods );

				expect(
					result.current.optionalMethods.map(
						( method ) => method.name
					)
				).toEqual( optionalMethods );
			}
		);
		test.each( [ 'US', 'GB', 'AU' ] )(
			'Country %s should contain Fastlane method if hasFastlane is true',
			( country ) => {
				const { result } = renderHook( () =>
					usePaymentConfig( country, true, true, false )
				);
				const methodNames = result.current.optionalMethods.map(
					( method ) => method.name
				);
				expect( methodNames ).toContain( 'Fastlane' );
			}
		);

		test.each( [ 'US', 'GB', 'AU' ] )(
			'Country %s should NOT contain Fastlane method if hasFastlane is false',
			( country ) => {
				const { result } = renderHook( () =>
					usePaymentConfig( country, true, false, false )
				);
				const methodNames = result.current.optionalMethods.map(
					( method ) => method.name
				);
				expect( methodNames ).not.toContain( 'Fastlane' );
			}
		);

		test.each( [ 'US', 'GB', 'AU' ] )(
			'Country %s should contain only ACDC methods when canUseCardPayments is false',
			( country ) => {
				const { result } = renderHook( () =>
					usePaymentConfig( country, false, false, false )
				);
				const methodNames = result.current.optionalMethods.map(
					( method ) => method.name
				);
				expect( methodNames ).toContain( 'CreditDebitCards' );
			}
		);

		test.each( [ 'US', 'GB', 'AU' ] )(
			'Country %s should NOT contain ACDC methods when canUseCardPayments is true',
			( country ) => {
				const { result } = renderHook( () =>
					usePaymentConfig( country, true, false, false )
				);
				const methodNames = result.current.optionalMethods.map(
					( method ) => method.name
				);
				expect( methodNames ).not.toContain( 'CreditDebitCards' );
			}
		);

		test.each( [ 'US', 'GB', 'AU' ] )(
			'Country %s should contain only OwnBrand methods when ownBrandOnly is true',
			( country ) => {
				const { result } = renderHook( () =>
					usePaymentConfig( country, true, true, true )
				);

				expect(
					result.current.optionalMethods.map(
						( method ) => method.name
					)
				).toEqual( [ 'APMs' ] );
			}
		);

		test( 'Country MX should contain non ACDC methods when canUseCardPayments is true', () => {
			const { result } = renderHook( () =>
				usePaymentConfig( 'MX', true, false, false )
			);
			const methodNames = result.current.optionalMethods.map(
				( method ) => method.name
			);
			expect( methodNames ).toContain( 'CreditDebitCards' );
			expect( methodNames ).toContain( 'APMs' );
		} );

		test( 'Country MX should contain non ACDC methods when canUseCardPayments is false', () => {
			const { result } = renderHook( () =>
				usePaymentConfig( 'MX', false, false, false )
			);
			const methodNames = result.current.optionalMethods.map(
				( method ) => method.name
			);
			expect( methodNames ).toContain( 'CreditDebitCards' );
			expect( methodNames ).toContain( 'APMs' );
		} );
	} );
} );
