import { loadScript, loadGoogleSdk } from './scriptLoaders';

afterEach( () => {
	document.head.innerHTML = '';
	delete window.google;
} );

describe( 'loadScript', () => {
	test( 'concurrent calls for the same URL share one script tag and one promise', () => {
		const url = 'https://example.test/shared.js';

		const first = loadScript( url );
		const second = loadScript( url );

		expect( first ).toBe( second );
		expect(
			document.head.querySelectorAll( `script[src="${ url }"]` )
		).toHaveLength( 1 );

		document.head.querySelector( `script[src="${ url }"]` ).onload();

		return expect(
			Promise.all( [ first, second ] )
		).resolves.toBeDefined();
	} );

	test( 'a failed load rejects awaiting callers, removes the tag and clears the cache', async () => {
		const url = 'https://example.test/failing.js';

		const pending = loadScript( url );
		document.head.querySelector( `script[src="${ url }"]` ).onerror();

		await expect( pending ).rejects.toThrow(
			`Failed to load script: ${ url }`
		);
		expect(
			document.head.querySelectorAll( `script[src="${ url }"]` )
		).toHaveLength( 0 );
	} );

	test( 'a later call after a failed load inserts a fresh script tag instead of reusing the poisoned promise', async () => {
		const url = 'https://example.test/retry.js';

		const firstAttempt = loadScript( url );
		document.head.querySelector( `script[src="${ url }"]` ).onerror();
		await expect( firstAttempt ).rejects.toThrow();

		const retry = loadScript( url );
		expect( retry ).not.toBe( firstAttempt );
		const tags = document.head.querySelectorAll( `script[src="${ url }"]` );
		expect( tags ).toHaveLength( 1 );

		tags[ 0 ].onload();
		await expect( retry ).resolves.toBeUndefined();
	} );
} );

describe( 'loadScript with a targetWindow', () => {
	function fakeWindow() {
		return {
			document: {
				head: { appendChild: jest.fn() },
				createElement: jest.fn( () => ( {
					remove: jest.fn(),
					setAttribute: jest.fn(),
				} ) ),
			},
		};
	}

	test( 'appends the script tag into the given window instead of the global one', () => {
		const otherWindow = fakeWindow();
		const url = 'https://example.test/other-window.js';

		loadScript( url, otherWindow );

		expect( otherWindow.document.createElement ).toHaveBeenCalledWith(
			'script'
		);
		expect( otherWindow.document.head.appendChild ).toHaveBeenCalled();
		expect(
			document.head.querySelectorAll( `script[src="${ url }"]` )
		).toHaveLength( 0 );
	} );

	test( 'caches the load promise per window, so the same URL loads once per window', () => {
		const windowA = fakeWindow();
		const windowB = fakeWindow();
		const url = 'https://example.test/per-window-cache.js';

		loadScript( url, windowA );
		loadScript( url, windowA );
		loadScript( url, windowB );

		expect( windowA.document.createElement ).toHaveBeenCalledTimes( 1 );
		expect( windowB.document.createElement ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'loads into the global window by default', () => {
		const url = 'https://example.test/default-window.js';

		loadScript( url );

		expect(
			document.head.querySelectorAll( `script[src="${ url }"]` )
		).toHaveLength( 1 );
	} );
} );

describe( 'loadGoogleSdk', () => {
	test( 'resolves once the script loads and the Google Pay global is present', async () => {
		const url = 'https://example.test/pay.js';

		const pending = loadGoogleSdk( url );
		window.google = {
			payments: { api: { PaymentsClient() {} } },
		};
		document.head.querySelector( `script[src="${ url }"]` ).onload();

		await expect( pending ).resolves.toBeUndefined();
	} );

	test( 'rejects when the script loads but the Google Pay global is absent', async () => {
		const url = 'https://example.test/pay-missing-global.js';

		const pending = loadGoogleSdk( url );
		document.head.querySelector( `script[src="${ url }"]` ).onload();

		await expect( pending ).rejects.toThrow(
			'Google Pay global not found after script load.'
		);
	} );
} );
