const mockCreatePayPalMessages = jest.fn( () => ( {} ) );
const mockLoadSdkV6 = jest.fn( () =>
	Promise.resolve( { createPayPalMessages: mockCreatePayPalMessages } )
);
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

import {
	initMessages,
	renderMessages,
	updateMessagesAmount,
	resetMessages,
} from '../messages/renderer';

// Matches the module's RESCAN_DEBOUNCE_MS. Not exported, so mirrored here.
const RESCAN_DEBOUNCE_MS = 100;

// `messages` is spread last so a partial `overrides.messages` merges into the
// defaults rather than replacing them — spreading `...overrides` afterwards
// would put the raw partial back over the merged object.
const baseConfig = ( overrides = {} ) => ( {
	currency: 'USD',
	buyer_country: 'US',
	...overrides,
	messages: {
		enabled: true,
		is_hidden: false,
		wrapper: '.ppcp-messages',
		amount: '100.00',
		page_type: 'product-details',
		style: {
			logoType: 'WORDMARK',
			logoPosition: 'LEFT',
			textColor: 'BLACK',
			fontSize: '',
		},
		...overrides.messages,
	},
} );

beforeEach( () => {
	jest.clearAllMocks();
	resetMessages();
	document.body.innerHTML = '';
} );

describe( 'renderMessages()', () => {
	test( 'appends one paypal-message per placeholder with the configured attributes', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';

		await renderMessages( baseConfig(), 'product' );

		const message = document.querySelector( 'paypal-message' );
		expect( message ).not.toBeNull();
		expect( message.getAttribute( 'auto-bootstrap' ) ).toBe( '' );
		expect( message.getAttribute( 'amount' ) ).toBe( '100.00' );
		expect( message.getAttribute( 'currency-code' ) ).toBe( 'USD' );
		expect( message.getAttribute( 'page-type' ) ).toBe( 'product-details' );
		expect( message.getAttribute( 'logo-type' ) ).toBe( 'WORDMARK' );
		expect( message.getAttribute( 'logo-position' ) ).toBe( 'LEFT' );
		expect( message.getAttribute( 'text-color' ) ).toBe( 'BLACK' );
		expect(
			message.style.getPropertyValue( '--paypal-message-font-size' )
		).toBe( '' );
	} );

	test( 'sets the font-size custom property when the config style has one', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';

		await renderMessages(
			baseConfig( {
				messages: {
					style: {
						logoType: 'WORDMARK',
						logoPosition: 'LEFT',
						textColor: 'BLACK',
						fontSize: '14px',
					},
				},
			} ),
			'product'
		);

		const message = document.querySelector( 'paypal-message' );
		expect(
			message.style.getPropertyValue( '--paypal-message-font-size' )
		).toBe( '14px' );
	} );

	test( 'never loads the SDK when there is no placeholder on the page', async () => {
		const count = await renderMessages( baseConfig(), 'product' );

		expect( count ).toBe( 0 );
		expect( mockLoadSdkV6 ).not.toHaveBeenCalled();
	} );

	test.each( [
		[ 'messages.enabled is false', { enabled: false } ],
		[ 'messages.is_hidden is true', { is_hidden: true } ],
	] )( 'never loads the SDK when %s', async ( _label, override ) => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';

		const count = await renderMessages(
			baseConfig( { messages: override } ),
			'product'
		);

		expect( count ).toBe( 0 );
		expect( mockLoadSdkV6 ).not.toHaveBeenCalled();
	} );

	test( 'appends only one component when called twice for the same placeholder', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		const config = baseConfig();

		await renderMessages( config, 'product' );
		await renderMessages( config, 'product' );

		expect(
			document.querySelectorAll( 'paypal-message' )
		).toHaveLength( 1 );
	} );

	test( 'fills a wrapper again after WooCommerce empties and re-adds it on updated_checkout', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		const config = baseConfig();

		await renderMessages( config, 'product' );

		// WooCommerce replaces the surrounding DOM wholesale, giving back a
		// fresh, unclaimed wrapper element.
		document.body.innerHTML = '<div class="ppcp-messages"></div>';

		await renderMessages( config, 'product' );

		expect(
			document.querySelectorAll( 'paypal-message' )
		).toHaveLength( 1 );
	} );

	test( 'produces exactly one message when two renderMessages calls race on the same wrapper', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		const config = baseConfig();

		const first = renderMessages( config, 'product' );
		const second = renderMessages( config, 'product' );
		await Promise.all( [ first, second ] );

		expect(
			document.querySelectorAll( 'paypal-message' )
		).toHaveLength( 1 );
		expect( mockCreatePayPalMessages ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'releases a failed wrapper so a later pass can retry it', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		const config = baseConfig();
		mockLoadSdkV6.mockRejectedValueOnce( new Error( 'sdk failed to load' ) );

		await expect( renderMessages( config, 'product' ) ).rejects.toThrow(
			'sdk failed to load'
		);
		expect( document.querySelector( 'paypal-message' ) ).toBeNull();

		await renderMessages( config, 'product' );

		expect( document.querySelector( 'paypal-message' ) ).not.toBeNull();
	} );

	test( 'creates the messages component exactly once across multiple render passes, with only the config currency', async () => {
		document.body.innerHTML =
			'<div class="ppcp-messages"></div><div class="ppcp-messages"></div>';
		const config = baseConfig();

		await renderMessages( config, 'product' );

		document.body.innerHTML += '<div class="ppcp-messages"></div>';
		await renderMessages( config, 'product' );

		expect( mockCreatePayPalMessages ).toHaveBeenCalledTimes( 1 );
		// No buyerCountry: it's PayPal's sandbox override for simulating a
		// buyer's location, not a field to populate from the billing address.
		// A country the store currency has no Pay Later offer for makes
		// fetch-presentment-messages fail with a hard 422 and the message
		// silently never renders.
		expect( mockCreatePayPalMessages ).toHaveBeenCalledWith(
			expect.not.objectContaining( { buyerCountry: expect.anything() } )
		);
		expect( mockCreatePayPalMessages ).toHaveBeenCalledWith( {
			currencyCode: 'USD',
		} );
	} );

	describe( 'per-wrapper style overrides', () => {
		test( "a wrapper's own data-pp-style-* attributes win over the config style", async () => {
			document.body.innerHTML = `
				<div
					class="ppcp-messages"
					data-pp-style-logo-type="alternative"
					data-pp-style-logo-position="top"
					data-pp-style-text-color="grayscale"
					data-pp-style-text-size="20"
				></div>
			`;

			await renderMessages(
				baseConfig( {
					messages: {
						style: {
							logoType: 'WORDMARK',
							logoPosition: 'LEFT',
							textColor: 'BLACK',
							fontSize: '',
						},
					},
				} ),
				'product'
			);

			const message = document.querySelector( 'paypal-message' );
			expect( message.getAttribute( 'logo-type' ) ).toBe( 'MONOGRAM' );
			expect( message.getAttribute( 'logo-position' ) ).toBe( 'TOP' );
			expect( message.getAttribute( 'text-color' ) ).toBe( 'MONOCHROME' );
			expect(
				message.style.getPropertyValue( '--paypal-message-font-size' )
			).toBe( '16px' );
		} );

		test( 'data-pp-style-logo-type="inline" forces the INLINE logo position', async () => {
			document.body.innerHTML = `
				<div class="ppcp-messages" data-pp-style-logo-type="inline"></div>
			`;

			await renderMessages( baseConfig(), 'product' );

			const message = document.querySelector( 'paypal-message' );
			expect( message.getAttribute( 'logo-position' ) ).toBe( 'INLINE' );
		} );
	} );

	describe( 'per-wrapper page type overrides', () => {
		test.each( [
			[ 'payment', 'checkout' ],
			[ 'cart', 'cart' ],
			[ 'product', 'product-details' ],
			[ 'product-list', 'product-listing' ],
			[ 'home', 'home' ],
		] )(
			'data-pp-placement="%s" renders page-type="%s"',
			async ( placement, expectedPageType ) => {
				document.body.innerHTML = `<div class="ppcp-messages" data-pp-placement="${ placement }"></div>`;

				await renderMessages( baseConfig(), 'product' );

				expect(
					document
						.querySelector( 'paypal-message' )
						.getAttribute( 'page-type' )
				).toBe( expectedPageType );
			}
		);

		test( 'falls back to config.messages.page_type when data-pp-placement is absent', async () => {
			document.body.innerHTML = '<div class="ppcp-messages"></div>';

			await renderMessages(
				baseConfig( { messages: { page_type: 'mini-cart' } } ),
				'product'
			);

			expect(
				document
					.querySelector( 'paypal-message' )
					.getAttribute( 'page-type' )
			).toBe( 'mini-cart' );
		} );
	} );
} );

describe( 'updateMessagesAmount()', () => {
	test( 'sets .amount on every rendered element', async () => {
		document.body.innerHTML =
			'<div class="ppcp-messages"></div><div class="ppcp-messages"></div>';
		await renderMessages( baseConfig(), 'product' );

		updateMessagesAmount( '250.00' );

		document.querySelectorAll( 'paypal-message' ).forEach( ( element ) => {
			expect( element.amount ).toBe( '250.00' );
		} );
	} );

	test( 'prunes and skips elements that are no longer connected to the document', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		await renderMessages( baseConfig(), 'product' );

		const detached = document.querySelector( 'paypal-message' );
		detached.remove();

		expect( () => updateMessagesAmount( '250.00' ) ).not.toThrow();
		expect( detached.amount ).toBeUndefined();
	} );

	test( 'is a no-op for an empty amount', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		await renderMessages( baseConfig(), 'product' );

		updateMessagesAmount( '' );

		expect(
			document.querySelector( 'paypal-message' ).amount
		).toBeUndefined();
	} );
} );

describe( 'initMessages()', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'renders once a placeholder appears on a later discovery attempt', async () => {
		const config = baseConfig();
		const initPromise = initMessages( config, 'product' );

		await Promise.resolve();
		expect( document.querySelector( 'paypal-message' ) ).toBeNull();

		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		await jest.advanceTimersByTimeAsync( RESCAN_DEBOUNCE_MS );

		await initPromise;
		expect( document.querySelector( 'paypal-message' ) ).not.toBeNull();
	} );

	test( 'resolves to 0 and never calls loadSdkV6 when no placeholder ever appears', async () => {
		const config = baseConfig();

		const count = await initMessages( config, 'product' );

		expect( count ).toBe( 0 );
		expect( mockLoadSdkV6 ).not.toHaveBeenCalled();
	} );

	test.each( [
		[ 'messages.enabled is false', { enabled: false } ],
		[ 'messages.is_hidden is true', { is_hidden: true } ],
	] )( 'resolves to 0 without calling loadSdkV6 when %s', async ( _label, override ) => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';

		const count = await initMessages(
			baseConfig( { messages: override } ),
			'product'
		);

		expect( count ).toBe( 0 );
		expect( mockLoadSdkV6 ).not.toHaveBeenCalled();
	} );

	test( 'mounts a fresh message after WooCommerce Blocks replaces the placeholder node', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		const config = baseConfig();

		await initMessages( config, 'product' );
		expect( document.querySelector( 'paypal-message' ) ).not.toBeNull();

		const freshWrapper = document.createElement( 'div' );
		freshWrapper.className = 'ppcp-messages';
		document.querySelector( '.ppcp-messages' ).replaceWith( freshWrapper );

		await Promise.resolve();
		await jest.advanceTimersByTimeAsync( RESCAN_DEBOUNCE_MS );

		const messages = document.querySelectorAll( 'paypal-message' );
		expect( messages ).toHaveLength( 1 );
		expect( freshWrapper.contains( messages[ 0 ] ) ).toBe( true );
	} );

	test( 'does not keep re-rendering once our own appended message settles', async () => {
		document.body.innerHTML = '<div class="ppcp-messages"></div>';
		const config = baseConfig();

		await initMessages( config, 'product' );
		await Promise.resolve();
		await jest.advanceTimersByTimeAsync( RESCAN_DEBOUNCE_MS );
		await jest.advanceTimersByTimeAsync( RESCAN_DEBOUNCE_MS );

		expect(
			document.querySelectorAll( 'paypal-message' )
		).toHaveLength( 1 );
	} );
} );
