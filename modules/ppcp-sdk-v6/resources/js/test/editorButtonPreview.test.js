const mockLoadScript = jest.fn( () => Promise.resolve() );
jest.mock( '../utils/scriptLoaders', () => ( {
	loadScript: ( ...args ) => mockLoadScript( ...args ),
} ) );

import { loadEditorButtons } from '../blocks/editorButtonPreview';

const baseOptions = ( overrides = {} ) => ( {
	sdkUrl: 'https://example.test/v6-sdk.js',
	clientId: 'client-id',
	locale: 'en_US',
	pageType: 'cart',
	...overrides,
} );

describe( 'loadEditorButtons()', () => {
	test( 'retries without the locale when the SDK rejects it', async () => {
		const createInstance = jest
			.fn()
			.mockRejectedValueOnce(
				Object.assign( new Error(), {
					code: 'ERR_DEV_UNSUPPORTED_LOCALE',
				} )
			)
			.mockResolvedValueOnce( {} );

		await loadEditorButtons(
			{ paypal: { createInstance } },
			baseOptions( { locale: 'tr_TR' } )
		);

		expect( createInstance.mock.calls[ 1 ][ 0 ] ).not.toHaveProperty(
			'locale'
		);
	} );
} );
