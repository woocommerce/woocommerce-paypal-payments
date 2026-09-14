import {
	MERCHANT_PRESENTED_METHODS,
	methodConfig,
	isMethodEnabled,
	methodSdkComponents,
} from './methodRegistry';

describe( 'MERCHANT_PRESENTED_METHODS', () => {
	test( 'lists googlepay and applepay as the wallet funding sources', () => {
		expect( MERCHANT_PRESENTED_METHODS ).toEqual( [ 'googlepay', 'applepay' ] );
	} );
} );

describe( 'methodConfig()', () => {
	test.each( [
		[
			'the google_pay subtree for the googlepay method',
			{ google_pay: { enabled: true } },
			'googlepay',
			'google_pay',
		],
		[
			'the apple_pay subtree for the applepay method',
			{ apple_pay: { enabled: true } },
			'applepay',
			'apple_pay',
		],
	] )( 'returns %s', ( _label, config, method, key ) => {
		expect( methodConfig( config, method ) ).toBe( config[ key ] );
	} );

	test.each( [ 'googlepay', 'applepay' ] )(
		'returns undefined when the config has no subtree for %s',
		( method ) => {
			expect( methodConfig( {}, method ) ).toBeUndefined();
		}
	);

	test( 'returns undefined for a method that is not a wallet', () => {
		expect(
			methodConfig( { google_pay: { enabled: true } }, 'paypal' )
		).toBeUndefined();
	} );
} );

describe( 'isMethodEnabled()', () => {
	test.each( [
		[ 'googlepay', { google_pay: { enabled: true } } ],
		[ 'applepay', { apple_pay: { enabled: true } } ],
	] )( 'is true when %s has enabled set', ( method, config ) => {
		expect( isMethodEnabled( config, method ) ).toBe( true );
	} );

	test.each( [
		[
			'the googlepay subtree has enabled set to false',
			{ google_pay: { enabled: false } },
			'googlepay',
		],
		[
			'the applepay subtree has enabled set to false',
			{ apple_pay: { enabled: false } },
			'applepay',
		],
		[ 'the config has no google_pay subtree', {}, 'googlepay' ],
		[ 'the config has no apple_pay subtree', {}, 'applepay' ],
		[
			'the method is not a wallet',
			{ google_pay: { enabled: true } },
			'paypal',
		],
	] )( 'is false when %s', ( _label, config, method ) => {
		expect( isMethodEnabled( config, method ) ).toBe( false );
	} );
} );

describe( 'methodSdkComponents()', () => {
	test.each( [
		[
			'only Google Pay is enabled',
			{ google_pay: { enabled: true } },
			[ 'googlepay-payments' ],
		],
		[
			'only Apple Pay is enabled',
			{ apple_pay: { enabled: true } },
			[ 'applepay-payments' ],
		],
		[
			'both wallets are enabled',
			{
				google_pay: { enabled: true },
				apple_pay: { enabled: true },
			},
			[ 'googlepay-payments', 'applepay-payments' ],
		],
	] )(
		'returns the matching components when %s',
		( _label, config, expected ) => {
			expect( methodSdkComponents( config ) ).toEqual( expected );
		}
	);

	test.each( [
		[ 'Google Pay is disabled', { google_pay: { enabled: false } } ],
		[ 'the config has no google_pay subtree', {} ],
	] )( 'returns an empty list when %s', ( _label, config ) => {
		expect( methodSdkComponents( config ) ).toEqual( [] );
	} );
} );
