import '@ppcp-test/helpers/silenceConsole';

import { describeError, logError } from './diagnostics';

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

describe( 'logError()', () => {
	afterEach( () => {
		global.fetch = undefined;
	} );

	test( 'posts the nonce, tag, event and detail as a message to the frontend log endpoint', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );

		await logError(
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

		await logError( brokenConfig, 'event', 'detail' );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'never rejects when fetch is undefined', async () => {
		global.fetch = undefined;

		await expect(
			logError( config(), 'event', 'detail' )
		).resolves.toBeUndefined();
	} );

	test( 'never rejects when fetch rejects', async () => {
		global.fetch = jest.fn().mockRejectedValue( new Error( 'network down' ) );

		await expect(
			logError( config(), 'event', 'detail' )
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
			logError( poisonedConfig, 'event', 'detail' )
		).resolves.toBeUndefined();
		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
