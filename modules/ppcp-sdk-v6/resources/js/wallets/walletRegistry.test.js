import {
	WALLET_METHODS,
	walletConfig,
	isWalletEnabled,
	walletSdkComponents,
} from './walletRegistry';

describe( 'WALLET_METHODS', () => {
	test( 'lists googlepay as the only wallet funding source', () => {
		expect( WALLET_METHODS ).toEqual( [ 'googlepay' ] );
	} );
} );

describe( 'walletConfig()', () => {
	test( 'returns the google_pay config subtree for the googlepay method', () => {
		const googlePay = { enabled: true };

		expect( walletConfig( { google_pay: googlePay }, 'googlepay' ) ).toBe(
			googlePay
		);
	} );

	test( 'returns undefined when the config has no google_pay subtree', () => {
		expect( walletConfig( {}, 'googlepay' ) ).toBeUndefined();
	} );

	test( 'returns undefined for a method that is not a wallet', () => {
		expect(
			walletConfig( { google_pay: { enabled: true } }, 'paypal' )
		).toBeUndefined();
	} );
} );

describe( 'isWalletEnabled()', () => {
	test( 'is true when the wallet subtree has enabled set', () => {
		expect(
			isWalletEnabled( { google_pay: { enabled: true } }, 'googlepay' )
		).toBe( true );
	} );

	test.each( [
		[
			'the wallet subtree has enabled set to false',
			{ google_pay: { enabled: false } },
			'googlepay',
		],
		[ 'the config has no google_pay subtree', {}, 'googlepay' ],
		[
			'the method is not a wallet',
			{ google_pay: { enabled: true } },
			'paypal',
		],
	] )( 'is false when %s', ( _label, config, method ) => {
		expect( isWalletEnabled( config, method ) ).toBe( false );
	} );
} );

describe( 'walletSdkComponents()', () => {
	test( 'includes googlepay-payments when Google Pay is enabled', () => {
		expect(
			walletSdkComponents( { google_pay: { enabled: true } } )
		).toEqual( [ 'googlepay-payments' ] );
	} );

	test.each( [
		[ 'Google Pay is disabled', { google_pay: { enabled: false } } ],
		[ 'the config has no google_pay subtree', {} ],
	] )( 'returns an empty list when %s', ( _label, config ) => {
		expect( walletSdkComponents( config ) ).toEqual( [] );
	} );
} );
