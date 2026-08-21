jest.mock( '@ppcp-sdk-v6/sdkLoader', () => ( {
	loadSdkV6: jest.fn(),
} ) );

import { loadSdkV6 } from '@ppcp-sdk-v6/sdkLoader';
import Fastlane from './Fastlane';

const namespace = 'ppcpPaypalClassicAxo';

function fastlaneConnection( overrides = {} ) {
	return {
		identity: { id: 'identity' },
		profile: { id: 'profile' },
		FastlaneCardComponent: { id: 'card' },
		FastlanePaymentComponent: { id: 'payment' },
		FastlaneWatermarkComponent: { id: 'watermark' },
		setLocale: jest.fn(),
		...overrides,
	};
}

beforeEach( () => {
	jest.clearAllMocks();
	delete window.wc_ppcp_sdk_v6;
	delete window[ namespace ];
} );

describe( 'Fastlane', () => {
	describe( 'connect()', () => {
		test( 'takes Fastlane from the v6 SDK when window.wc_ppcp_sdk_v6.fastlane.enabled is true', async () => {
			const v6Config = { fastlane: { enabled: true }, page_context: 'cart' };
			window.wc_ppcp_sdk_v6 = v6Config;

			const connection = fastlaneConnection();
			const createFastlane = jest.fn().mockResolvedValue( connection );
			loadSdkV6.mockResolvedValue( { createFastlane } );

			const fastlane = new Fastlane( namespace );
			const options = { locale: 'en_US' };
			await fastlane.connect( options );

			expect( loadSdkV6 ).toHaveBeenCalledWith( v6Config, 'cart' );
			expect( createFastlane ).toHaveBeenCalledWith( options );
			expect( fastlane.identity ).toBe( connection.identity );
			expect( fastlane.profile ).toBe( connection.profile );
			expect( fastlane.FastlaneCardComponent ).toBe(
				connection.FastlaneCardComponent
			);
			expect( fastlane.FastlanePaymentComponent ).toBe(
				connection.FastlanePaymentComponent
			);
			expect( fastlane.FastlaneWatermarkComponent ).toBe(
				connection.FastlaneWatermarkComponent
			);

			fastlane.setLocale( 'de_DE' );
			expect( connection.setLocale ).toHaveBeenCalledWith( 'de_DE' );
		} );

		test( 'defaults the loader context to checkout when page_context is absent', async () => {
			const v6Config = { fastlane: { enabled: true } };
			window.wc_ppcp_sdk_v6 = v6Config;
			loadSdkV6.mockResolvedValue( {
				createFastlane: jest.fn().mockResolvedValue(
					fastlaneConnection()
				),
			} );

			await new Fastlane( namespace ).connect( {} );

			expect( loadSdkV6 ).toHaveBeenCalledWith( v6Config, 'checkout' );
		} );

		test.each( [
			[ 'window.wc_ppcp_sdk_v6 is absent', undefined ],
			[ 'window.wc_ppcp_sdk_v6 has no fastlane key', {} ],
			[
				'window.wc_ppcp_sdk_v6.fastlane.enabled is false',
				{ fastlane: { enabled: false } },
			],
		] )( 'falls back to the v5 namespace when %s', async ( label, config ) => {
			if ( config !== undefined ) {
				window.wc_ppcp_sdk_v6 = config;
			}

			const connection = fastlaneConnection();
			window[ namespace ] = {
				Fastlane: jest.fn().mockResolvedValue( connection ),
			};

			const fastlane = new Fastlane( namespace );
			await fastlane.connect( {} );

			expect( loadSdkV6 ).not.toHaveBeenCalled();
			expect( fastlane.identity ).toBe( connection.identity );
		} );

		test( 'rejects when loadSdkV6 rejects', async () => {
			window.wc_ppcp_sdk_v6 = { fastlane: { enabled: true } };
			const error = new Error( 'sdk load failed' );
			loadSdkV6.mockRejectedValue( error );

			await expect(
				new Fastlane( namespace ).connect( {} )
			).rejects.toThrow( error );
		} );

		test( 'rejects when createFastlane rejects', async () => {
			window.wc_ppcp_sdk_v6 = { fastlane: { enabled: true } };
			const error = new Error( 'createFastlane failed' );
			loadSdkV6.mockResolvedValue( {
				createFastlane: jest.fn().mockRejectedValue( error ),
			} );

			await expect(
				new Fastlane( namespace ).connect( {} )
			).rejects.toThrow( error );
		} );

		test( 'rejects when the v5 namespace is not found on window', async () => {
			await expect(
				new Fastlane( namespace ).connect( {} )
			).rejects.toThrow( /not found on window object/ );
		} );
	} );
} );
