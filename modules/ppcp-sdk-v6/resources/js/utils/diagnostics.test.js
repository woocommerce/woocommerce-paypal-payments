import '@ppcp-test/helpers/silenceConsole';

import { logError } from './diagnostics';

const config = ( overrides = {} ) => ( {
	ajax: {
		frontend_log: {
			endpoint: '/wc-ajax/ppc-frontend-log',
			nonce: 'n1',
		},
	},
	...overrides,
} );

describe( 'logError()', () => {
	afterEach( () => {
		global.fetch = undefined;
	} );

	test( 'posts the nonce, tag, event and detail as context to the frontend log endpoint', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );

		await logError( config(), 'apple-pay-shipping-abort', {
			message: 'Rate not found',
		} );

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wc-ajax/ppc-frontend-log' );
		expect( JSON.parse( options.body ) ).toEqual( {
			nonce: 'n1',
			tag: 'SDK v6',
			event: 'apple-pay-shipping-abort',
			context: { message: 'Rate not found' },
		} );
	} );

	test( 'stringifies non-string detail values', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );

		await logError( config(), 'confirm-order-not-approved', {
			status: 'DECLINED',
			result: { status: 'DECLINED' },
			count: 3,
		} );

		const [ , options ] = global.fetch.mock.calls[ 0 ];
		expect( JSON.parse( options.body ).context ).toEqual( {
			status: 'DECLINED',
			result: '{"status":"DECLINED"}',
			count: '3',
		} );
	} );

	test( 'drops undefined and null detail values from the posted context', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );

		await logError( config(), 'event', {
			present: 'value',
			missing: undefined,
			absent: null,
		} );

		const [ , options ] = global.fetch.mock.calls[ 0 ];
		expect( JSON.parse( options.body ).context ).toEqual( {
			present: 'value',
		} );
	} );

	test( 'clamps a long value to 500 characters', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );

		await logError( config(), 'event', {
			body: 'x'.repeat( 600 ),
		} );

		const [ , options ] = global.fetch.mock.calls[ 0 ];
		const posted = JSON.parse( options.body ).context.body;
		expect( posted ).toHaveLength( 500 );
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

		await logError( brokenConfig, 'event', {} );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'never rejects when fetch is undefined', async () => {
		global.fetch = undefined;

		await expect(
			logError( config(), 'event', {} )
		).resolves.toBeUndefined();
	} );

	test( 'never rejects when fetch rejects', async () => {
		global.fetch = jest.fn().mockRejectedValue( new Error( 'network down' ) );

		await expect(
			logError( config(), 'event', {} )
		).resolves.toBeUndefined();
	} );

	test( 'stringifies a circular detail value instead of throwing', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );
		const circular = {};
		circular.self = circular;

		await expect(
			logError( config(), 'event', { circular } )
		).resolves.toBeUndefined();

		const [ , options ] = global.fetch.mock.calls[ 0 ];
		expect( JSON.parse( options.body ).context.circular ).toBe(
			'[object Object]'
		);
	} );

	test( 'never throws and skips the POST when a detail value has a throwing getter', async () => {
		global.fetch = jest.fn().mockResolvedValue( {} );

		await expect(
			logError( config(), 'event', {
				get poison() {
					throw new Error( 'boom' );
				},
			} )
		).resolves.toBeUndefined();

		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
