import { recordDomainValidation } from './applePayValidation';

const config = ( overrides = {} ) => ( {
	apple_pay: {
		validation: {
			endpoint: 'https://shop.test/wc-ajax=ppc-validate-domain',
			action: 'ppc-validate-domain',
			nonce: 'a-nonce',
		},
	},
	...overrides,
} );

let originalFetch;

beforeEach( () => {
	originalFetch = global.fetch;
	global.fetch = jest.fn().mockResolvedValue( { ok: true } );
} );

afterEach( () => {
	global.fetch = originalFetch;
} );

describe( 'recordDomainValidation()', () => {
	test.each( [
		[
			'config.apple_pay.validation.endpoint is missing',
			{ apple_pay: {} },
		],
		[ 'config.apple_pay is entirely absent', { apple_pay: undefined } ],
	] )( 'does nothing when %s', async ( _label, overrides ) => {
		await recordDomainValidation( config( overrides ), true );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'POSTs a form-encoded body carrying the action, checkout nonce, and validation flag', async () => {
		await recordDomainValidation( config(), true );

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( 'https://shop.test/wc-ajax=ppc-validate-domain' );
		expect( options.method ).toBe( 'POST' );
		expect( options.credentials ).toBe( 'same-origin' );
		expect( options.headers[ 'Content-Type' ] ).toBe(
			'application/x-www-form-urlencoded'
		);

		const body = new URLSearchParams( options.body );
		expect( body.get( 'action' ) ).toBe( 'ppc-validate-domain' );
		expect( body.get( 'woocommerce-process-checkout-nonce' ) ).toBe(
			'a-nonce'
		);
		expect( body.get( 'validation' ) ).toBe( 'true' );
	} );

	test( 'sends the validation flag as a stringified false when validation failed', async () => {
		await recordDomainValidation( config(), false );

		const body = new URLSearchParams(
			global.fetch.mock.calls[ 0 ][ 1 ].body
		);
		expect( body.get( 'validation' ) ).toBe( 'false' );
	} );

	test( 'never rejects, even when fetch rejects', async () => {
		global.fetch.mockRejectedValueOnce( new Error( 'network down' ) );

		await expect(
			recordDomainValidation( config(), true )
		).resolves.toBeUndefined();
	} );
} );
