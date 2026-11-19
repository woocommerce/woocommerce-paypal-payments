import '@ppcp-test/helpers/silenceConsole';

import {
	cardSubmitFailureDetail,
	describeError,
	logEvent,
} from './diagnostics';

const config = ( overrides = {} ) => ( {
	ajax: {
		frontend_log: {
			endpoint: '/wc-ajax/ppc-frontend-log',
			nonce: 'n1',
		},
	},
	...overrides,
} );

describe( 'describeError()', () => {
	test( 'prefixes the message with the HTTP status when the error carries one', () => {
		const error = { status: 422, message: 'Unprocessable' };

		expect( describeError( error ) ).toBe( 'HTTP 422 Unprocessable' );
	} );

	test( 'returns just the message when the error has no status', () => {
		const error = new Error( 'network down' );

		expect( describeError( error ) ).toBe( 'network down' );
	} );

	test( 'falls back to "unknown error" when the error has no message', () => {
		expect( describeError( {} ) ).toBe( 'unknown error' );
	} );
} );

describe( 'cardSubmitFailureDetail()', () => {
	test.each( [ 'classic', 'block' ] )(
		'names the %s checkout, the order ID, and the state',
		( checkout ) => {
			const detail = cardSubmitFailureDetail( checkout, 'ORDER1', {
				state: 'failed',
			} );

			expect( detail ).toContain( checkout );
			expect( detail ).toContain( 'ORDER1' );
			expect( detail ).toContain( 'failed' );
			expect( detail ).not.toContain( 'errorCode' );
		}
	);

	test( 'includes the error code when the result data carries one', () => {
		const detail = cardSubmitFailureDetail( 'classic', 'ORDER1', {
			state: 'failed',
			data: { errorCode: 'PAYER_CANNOT_PAY' },
		} );

		expect( detail ).toContain( 'PAYER_CANNOT_PAY' );
	} );

	test( 'leaves out any other result data, such as address or card values', () => {
		const detail = cardSubmitFailureDetail( 'block', 'ORDER1', {
			state: 'failed',
			data: {
				errorCode: 'X',
				billingAddress: { postalCode: '90001', countryCode: 'US' },
				cardNumber: '4111111111111111',
			},
		} );

		expect( detail ).not.toContain( '90001' );
		expect( detail ).not.toContain( 'US' );
		expect( detail ).not.toContain( '4111' );
	} );

	test.each( [ undefined, null, {} ] )(
		'does not throw when the result is %p',
		( result ) => {
			expect( () =>
				cardSubmitFailureDetail( 'classic', 'ORDER1', result )
			).not.toThrow();
		}
	);
} );

describe( 'logEvent()', () => {
	afterEach( () => {
		global.fetch = undefined;
	} );

	test( 'posts the nonce, tag, event and detail as a message to the frontend log endpoint', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );

		await logEvent(
			config(),
			'apple-pay-shipping-abort',
			'Rate not found'
		);

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wc-ajax/ppc-frontend-log' );
		expect( JSON.parse( options.body ) ).toEqual( {
			nonce: 'n1',
			tag: 'SDK v6',
			event: 'apple-pay-shipping-abort',
			message: 'Rate not found',
			level: 'error',
		} );
	} );

	test.each( [
		[ 'the config is missing entirely', undefined ],
		[ 'ajax is missing', {} ],
		[ 'frontend_log is missing', { ajax: {} } ],
		[
			'frontend_log has no endpoint',
			{ ajax: { frontend_log: { nonce: 'n1' } } },
		],
	] )( 'skips the POST when %s', async ( _label, brokenConfig ) => {
		global.fetch = jest.fn();

		await logEvent( brokenConfig, 'event', 'detail' );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'never rejects when fetch is undefined', async () => {
		global.fetch = undefined;

		await expect(
			logEvent( config(), 'event', 'detail' )
		).resolves.toBeUndefined();
	} );

	test( 'never rejects when fetch rejects', async () => {
		global.fetch = jest.fn().mockRejectedValue( new Error( 'network down' ) );

		await expect(
			logEvent( config(), 'event', 'detail' )
		).resolves.toBeUndefined();
	} );

	test( 'never rejects when reading the config throws', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );
		const poisonedConfig = {
			get ajax() {
				throw new Error( 'boom' );
			},
		};

		await expect(
			logEvent( poisonedConfig, 'event', 'detail' )
		).resolves.toBeUndefined();
		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
