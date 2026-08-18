import jQuery from 'jquery';

const mockGetCurrentPaymentMethod = jest.fn();
jest.mock( '@ppcp-button/Helper/CheckoutMethodState', () => ( {
	getCurrentPaymentMethod: () => mockGetCurrentPaymentMethod(),
	ORDER_BUTTON_SELECTOR: '#place_order',
	PaymentMethods: { PAYPAL: 'ppcp-gateway' },
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

// The module keeps its registered wallet rows and listener flag at module
// scope, so each test needs a fresh module instance.
let revealGateway;
let syncGatewayVisibility;

beforeEach( () => {
	jest.resetModules();
	mockGetCurrentPaymentMethod.mockReset();
	mockHasJQuery.mockReset();
	mockHasJQuery.mockReturnValue( true );
	// document.body.innerHTML only replaces the body's children, so handlers
	// bound to the body itself by a previous test's module instance survive
	// unless unbound here.
	jQuery( document.body ).off();
	document.body.innerHTML = '';
	global.jQuery = jQuery;
	( {
		revealGateway,
		syncGatewayVisibility,
	} = require( './gatewayPlacement' ) );
} );

describe( 'revealGateway()', () => {
	test( 'removes only the hide-gateway style tag for this method', () => {
		document.body.innerHTML =
			'<style data-hide-gateway="googlepay"></style>' +
			'<style data-hide-gateway="applepay"></style>';

		revealGateway( 'googlepay' );

		expect(
			document.querySelector( 'style[data-hide-gateway="googlepay"]' )
		).toBeNull();
		expect(
			document.querySelector( 'style[data-hide-gateway="applepay"]' )
		).not.toBeNull();
	} );

	test(
		"clears an inline display:none on this method's row, " +
			"leaving another method's row hidden",
		() => {
			document.body.innerHTML =
				'<div class="wc_payment_method payment_method_googlepay" ' +
				'style="display: none"></div>' +
				'<div class="wc_payment_method payment_method_applepay" ' +
				'style="display: none"></div>';

			revealGateway( 'googlepay' );

			expect(
				document.querySelector( '.payment_method_googlepay' ).style
					.display
			).toBe( '' );
			expect(
				document.querySelector( '.payment_method_applepay' ).style
					.display
			).toBe( 'none' );
		}
	);

	test( 'does nothing when neither the style tag nor the row exist', () => {
		expect( () => revealGateway( 'googlepay' ) ).not.toThrow();
	} );
} );

describe( 'syncGatewayVisibility()', () => {
	function setDom() {
		// The wrapper carries a child so hasRenderedButton() treats it as an
		// eligible wallet's already-rendered button, as it is in real usage.
		document.body.innerHTML =
			'<div id="wallet-wrapper"><button></button></div>' +
			'<div id="place_order"></div>';
	}

	function setDomWithExpress() {
		document.body.innerHTML =
			'<div id="wallet-wrapper"><button></button></div>' +
			'<div id="express-wrapper"></div>' +
			'<div id="place_order"></div>';
	}

	/**
	 * Calls syncGatewayVisibility() with the common googlepay/#wallet-wrapper
	 * defaults, so each test states only what it overrides.
	 *
	 * @param {Object} [overrides] - Overrides for methodId/wrapperSelector/expressSelector.
	 * @return {void}
	 */
	function sync( overrides = {} ) {
		syncGatewayVisibility( {
			methodId: 'googlepay',
			wrapperSelector: '#wallet-wrapper',
			...overrides,
		} );
	}

	/**
	 * The display style of the element matching the given selector.
	 *
	 * @param {string} selector - The element's selector.
	 * @return {string} Its style.display.
	 */
	function displayOf( selector ) {
		return document.querySelector( selector ).style.display;
	}

	test(
		'shows the wallet wrapper and hides "Place order" when this ' +
			'method is currently selected',
		() => {
			setDom();
			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );

			sync();

			expect( displayOf( '#wallet-wrapper' ) ).toBe( '' );
			expect( displayOf( '#place_order' ) ).toBe( 'none' );
		}
	);

	test(
		'hides the wallet wrapper and shows "Place order" when a ' +
			'different method is selected',
		() => {
			setDom();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

			sync();

			expect( displayOf( '#wallet-wrapper' ) ).toBe( 'none' );
			expect( displayOf( '#place_order' ) ).toBe( '' );
		}
	);

	test(
		're-evaluates and swaps visibility when the checkout fires ' +
			'payment_method_selected',
		() => {
			setDom();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

			sync();
			expect( displayOf( '#wallet-wrapper' ) ).toBe( 'none' );

			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );
			jQuery( document.body ).trigger( 'payment_method_selected' );

			expect( displayOf( '#wallet-wrapper' ) ).toBe( '' );
			expect( displayOf( '#place_order' ) ).toBe( 'none' );
		}
	);

	test(
		'registers the checkout-update listener only once across ' +
			'repeated calls, even for a different method',
		() => {
			setDom();
			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );

			sync();
			sync();
			sync( { methodId: 'applepay' } );

			// A stacked listener would call this three times per trigger
			// instead of once.
			mockGetCurrentPaymentMethod.mockClear();
			jQuery( document.body ).trigger( 'payment_method_selected' );

			expect( mockGetCurrentPaymentMethod ).toHaveBeenCalledTimes( 1 );
		}
	);

	test(
		'reveals the wallet wrapper without throwing when jQuery is ' +
			'absent, and never registers a listener',
		() => {
			setDom();
			mockHasJQuery.mockReturnValue( false );
			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );

			expect( () => sync() ).not.toThrow();
			expect( displayOf( '#wallet-wrapper' ) ).toBe( '' );

			// Nothing is listening, so a different selection leaves the
			// wrapper's display unchanged.
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
			jQuery( document.body ).trigger( 'payment_method_selected' );

			expect( displayOf( '#wallet-wrapper' ) ).toBe( '' );
		}
	);

	test(
		'hides the express wrapper while the wallet row is ' +
			"selected, and shows it once PayPal's own row is selected",
		() => {
			setDomWithExpress();
			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );

			sync( { expressSelector: '#express-wrapper' } );
			expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );

			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
			jQuery( document.body ).trigger( 'payment_method_selected' );

			expect( displayOf( '#express-wrapper' ) ).toBe( '' );
		}
	);

	test(
		'keeps "Place order" when the selected wallet row\'s ' +
			'container has not rendered a button yet',
		() => {
			document.body.innerHTML =
				'<div id="wallet-wrapper"></div>' +
				'<div id="place_order"></div>';
			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );

			sync();

			expect( displayOf( '#place_order' ) ).toBe( '' );
		}
	);

	test(
		'keeps "Place order" when the selected method is not a ' +
			"registered wallet row nor PayPal's own row",
		() => {
			setDom();
			mockGetCurrentPaymentMethod.mockReturnValue( 'bacs' );

			sync();

			expect( displayOf( '#place_order' ) ).toBe( '' );
		}
	);

	test(
		"shows only the selected wallet's wrapper when two wallet " +
			'rows are registered',
		() => {
			document.body.innerHTML =
				'<div id="googlepay-wrapper"><button></button></div>' +
				'<div id="applepay-wrapper"><button></button></div>' +
				'<div id="place_order"></div>';

			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );
			sync( { wrapperSelector: '#googlepay-wrapper' } );
			sync( {
				methodId: 'applepay',
				wrapperSelector: '#applepay-wrapper',
			} );

			expect( displayOf( '#googlepay-wrapper' ) ).toBe( '' );
			expect( displayOf( '#applepay-wrapper' ) ).toBe( 'none' );
		}
	);

	test(
		"does not throw when a registered row's container is missing " +
			'from the DOM, and still updates the other registered row ' +
			'and "Place order" on the same pass',
		() => {
			document.body.innerHTML =
				'<div id="applepay-wrapper"><button></button></div>' +
				'<div id="place_order"></div>';
			// No element for '#googlepay-wrapper': the row is registered,
			// but its container does not exist in the DOM.

			mockGetCurrentPaymentMethod.mockReturnValue( 'applepay' );

			expect( () => {
				sync( { wrapperSelector: '#googlepay-wrapper' } );
				sync( {
					methodId: 'applepay',
					wrapperSelector: '#applepay-wrapper',
				} );
			} ).not.toThrow();

			expect( displayOf( '#applepay-wrapper' ) ).toBe( '' );
			expect( displayOf( '#place_order' ) ).toBe( 'none' );
		}
	);

	test(
		'restyles freshly-inserted elements after a checkout ' +
			'update replaces the order-review DOM',
		() => {
			setDom();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

			sync();

			// Simulates WooCommerce replacing the whole order-review markup.
			document.body.innerHTML =
				'<div id="wallet-wrapper"><button></button></div>' +
				'<div id="place_order"></div>';
			mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );
			jQuery( document.body ).trigger( 'updated_checkout' );

			expect( displayOf( '#wallet-wrapper' ) ).toBe( '' );
			expect( displayOf( '#place_order' ) ).toBe( 'none' );
		}
	);
} );
