import jQuery from 'jquery';

const mockGetCurrentPaymentMethod = jest.fn();
const mockIsSavedPayPalTokenSelected = jest.fn();
jest.mock( '@ppcp-button/Helper/CheckoutMethodState', () => ( {
	getCurrentPaymentMethod: () => mockGetCurrentPaymentMethod(),
	isSavedPayPalTokenSelected: () => mockIsSavedPayPalTokenSelected(),
	ORDER_BUTTON_SELECTOR: '#place_order',
	PaymentContext: { Gateways: [ 'checkout', 'pay-now' ] },
	PaymentMethods: { PAYPAL: 'ppcp-gateway' },
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

// The module keeps its registered wallet rows and listener flag at module
// scope, so each test needs a fresh module instance.
let revealMethodGateway;
let placeExpressButtons;
let setExpressButtonsFailed;

beforeEach( () => {
	jest.resetModules();
	mockGetCurrentPaymentMethod.mockReset();
	mockIsSavedPayPalTokenSelected.mockReset();
	mockIsSavedPayPalTokenSelected.mockReturnValue( false );
	mockHasJQuery.mockReset();
	mockHasJQuery.mockReturnValue( true );
	// document.body.innerHTML only replaces the body's children, so handlers
	// bound to the body itself, or delegated from document, by a previous
	// test's module instance survive unless unbound here.
	jQuery( document.body ).off();
	jQuery( document ).off( 'change', 'input[name="wc-ppcp-gateway-payment-token"]' );
	document.body.innerHTML = '';
	global.jQuery = jQuery;
	( {
		revealMethodGateway,
		placeExpressButtons,
		setExpressButtonsFailed,
	} = require( './gatewayPlacement' ) );
} );

/**
 * Registers the common googlepay/#wallet-wrapper row, and the express
 * container when an expressSelector is given, so each test states only what
 * it overrides.
 *
 * @param {Object} [overrides] - Overrides for methodId/wrapperSelector/expressSelector/pageContext.
 *                             The page context defaults to 'checkout'.
 * @return {void}
 */
function reveal( overrides = {} ) {
	const {
		methodId = 'googlepay',
		wrapperSelector = '#wallet-wrapper',
		expressSelector,
		pageContext = 'checkout',
	} = overrides;

	revealMethodGateway( { id: methodId, wrapper: wrapperSelector } );

	if ( expressSelector ) {
		placeExpressButtons( {
			page_context: pageContext,
			wrapper: expressSelector,
		} );
	}
}

/**
 * Registers the express container the way the render does.
 *
 * @param {string} [pageContext] - The page context, 'checkout' by default.
 * @return {void}
 */
function placeExpress( pageContext = 'checkout' ) {
	placeExpressButtons( {
		page_context: pageContext,
		wrapper: '#express-wrapper',
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

describe( 'revealMethodGateway()', () => {
	describe( 'revealing the payment-method row', () => {
		test( 'removes only the hide-gateway style tag for this method', () => {
			document.body.innerHTML =
				'<style data-hide-gateway="googlepay"></style>' +
				'<style data-hide-gateway="applepay"></style>';

			reveal();

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

				reveal();

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

		test( 'does not throw when neither the style tag nor the row exist', () => {
			expect( () => reveal() ).not.toThrow();
		} );

		test(
			'does nothing when gateway is falsy: no style tag removed, ' +
				'no listener registered, "Place order" untouched',
			() => {
				document.body.innerHTML =
					'<style data-hide-gateway="googlepay"></style>' +
					'<div id="place_order" style="display: none"></div>';

				revealMethodGateway( null );

				expect(
					document.querySelector(
						'style[data-hide-gateway="googlepay"]'
					)
				).not.toBeNull();
				expect( displayOf( '#place_order' ) ).toBe( 'none' );

				// No listener registered: triggering the checkout event has
				// nothing to react to it.
				expect( () =>
					jQuery( document.body ).trigger(
						'payment_method_selected'
					)
				).not.toThrow();
				expect( displayOf( '#place_order' ) ).toBe( 'none' );
			}
		);
	} );

	describe( 'keeping the row, "Place order" and the express wrapper mutually exclusive', () => {
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

		test(
			'shows the wallet wrapper and hides "Place order" when this ' +
				'method is currently selected',
			() => {
				setDom();
				mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );

				reveal();

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

				reveal();

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

				reveal();
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

				reveal();
				reveal();
				reveal( { methodId: 'applepay' } );

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

				expect( () => reveal() ).not.toThrow();
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

				reveal( { expressSelector: '#express-wrapper' } );
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

				reveal();

				expect( displayOf( '#place_order' ) ).toBe( '' );
			}
		);

		test(
			'keeps "Place order" when the selected method is not a ' +
				"registered wallet row nor PayPal's own row",
			() => {
				setDom();
				mockGetCurrentPaymentMethod.mockReturnValue( 'bacs' );

				reveal();

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
				reveal( { wrapperSelector: '#googlepay-wrapper' } );
				reveal( {
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
					reveal( { wrapperSelector: '#googlepay-wrapper' } );
					reveal( {
						methodId: 'applepay',
						wrapperSelector: '#applepay-wrapper',
					} );
				} ).not.toThrow();

				expect( displayOf( '#applepay-wrapper' ) ).toBe( '' );
				expect( displayOf( '#place_order' ) ).toBe( 'none' );
			}
		);

		test(
			"selecting the card button's row hides the wallet " +
				'container, the express wrapper and "Place order", while ' +
				"showing the card row's own button",
			() => {
				document.body.innerHTML =
					'<div id="wallet-wrapper"><button></button></div>' +
					'<div id="card-button-wrapper"><button></button></div>' +
					'<div id="express-wrapper"></div>' +
					'<div id="place_order"></div>';

				reveal( { expressSelector: '#express-wrapper' } );
				reveal( {
					methodId: 'ppcp-card-button-gateway',
					wrapperSelector: '#card-button-wrapper',
				} );
				mockGetCurrentPaymentMethod.mockReturnValue(
					'ppcp-card-button-gateway'
				);
				jQuery( document.body ).trigger( 'payment_method_selected' );

				expect( displayOf( '#wallet-wrapper' ) ).toBe( 'none' );
				expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
				expect( displayOf( '#place_order' ) ).toBe( 'none' );
				expect( displayOf( '#card-button-wrapper' ) ).toBe( '' );
			}
		);

		test(
			'restyles freshly-inserted elements after a checkout ' +
				'update replaces the order-review DOM',
			() => {
				setDom();
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

				reveal();

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

	describe( "PayPal's row and the express container", () => {
		const cardRow = {
			methodId: 'ppcp-card-button-gateway',
			wrapperSelector: '#card-button-wrapper',
			expressSelector: '#express-wrapper',
		};

		function setDomWithEmptyExpress() {
			document.body.innerHTML =
				'<div id="card-button-wrapper"><button></button></div>' +
				'<div id="express-wrapper"></div>' +
				'<div id="place_order"></div>';
		}

		test.each( [ 'checkout', 'pay-now' ] )(
			'hides "Place order" on the %s page while the express buttons ' +
				'have not rendered yet',
			( pageContext ) => {
				setDomWithEmptyExpress();
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

				reveal( { ...cardRow, pageContext } );

				expect( displayOf( '#place_order' ) ).toBe( 'none' );
			}
		);

		test(
			'keeps "Place order" and leaves the express container alone ' +
				'outside the checkout page contexts',
			() => {
				document.body.innerHTML =
					'<div id="card-button-wrapper"><button></button></div>' +
					'<div id="express-wrapper"><button></button></div>' +
					'<div id="place_order"></div>';
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

				reveal( { ...cardRow, pageContext: '' } );

				expect( displayOf( '#place_order' ) ).toBe( '' );
				expect( displayOf( '#express-wrapper' ) ).toBe( '' );
			}
		);

		test( 'keeps "Place order" when the express container is not in the DOM', () => {
			document.body.innerHTML =
				'<div id="card-button-wrapper"><button></button></div>' +
				'<div id="place_order"></div>';
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

			reveal( cardRow );

			expect( displayOf( '#place_order' ) ).toBe( '' );
		} );

		test(
			'keeps "Place order" hidden after switching to the card row, ' +
				'with the express container hidden too',
			() => {
				setDomWithEmptyExpress();
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
				reveal( cardRow );

				mockGetCurrentPaymentMethod.mockReturnValue(
					'ppcp-card-button-gateway'
				);
				jQuery( document.body ).trigger( 'payment_method_selected' );

				expect( displayOf( '#place_order' ) ).toBe( 'none' );
				expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
			}
		);
	} );

	describe( "PayPal's row with a saved payment token", () => {
		function setDomWithSavedToken() {
			document.body.innerHTML =
				'<div id="express-wrapper"><button></button></div>' +
				'<div id="place_order"></div>' +
				'<input type="radio" name="wc-ppcp-gateway-payment-token" ' +
				'value="2" checked />' +
				'<input type="radio" name="wc-ppcp-gateway-payment-token" ' +
				'value="new" />';
		}

		test(
			'hides the express wrapper and keeps "Place order" when ' +
				"PayPal's row is selected with a saved token",
			() => {
				setDomWithSavedToken();
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
				mockIsSavedPayPalTokenSelected.mockReturnValue( true );

				reveal( { expressSelector: '#express-wrapper' } );

				expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
				expect( displayOf( '#place_order' ) ).toBe( '' );
			}
		);

		test(
			'shows the express wrapper and hides "Place order" when ' +
				'"Use a new payment method" is selected instead',
			() => {
				setDomWithSavedToken();
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
				mockIsSavedPayPalTokenSelected.mockReturnValue( false );

				reveal( { expressSelector: '#express-wrapper' } );

				expect( displayOf( '#express-wrapper' ) ).toBe( '' );
				expect( displayOf( '#place_order' ) ).toBe( 'none' );
			}
		);

		test(
			're-evaluates visibility when the payment-token radio ' +
				'changes, without a checkout DOM rebuild',
			() => {
				setDomWithSavedToken();
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
				mockIsSavedPayPalTokenSelected.mockReturnValue( false );

				reveal( { expressSelector: '#express-wrapper' } );
				expect( displayOf( '#express-wrapper' ) ).toBe( '' );
				expect( displayOf( '#place_order' ) ).toBe( 'none' );

				mockIsSavedPayPalTokenSelected.mockReturnValue( true );
				jQuery(
					'input[name="wc-ppcp-gateway-payment-token"][value="2"]'
				).trigger( 'change' );

				expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
				expect( displayOf( '#place_order' ) ).toBe( '' );
			}
		);
	} );
} );

describe( 'placeExpressButtons()', () => {
	function setDomWithEmptyExpress() {
		document.body.innerHTML =
			'<div id="express-wrapper"></div>' + '<div id="place_order"></div>';
	}

	describe( "PayPal's row with no wallet rows registered", () => {
		test.each( [ 'checkout', 'pay-now' ] )(
			'hides "Place order" and shows the empty express container on the %s page',
			( pageContext ) => {
				setDomWithEmptyExpress();
				mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

				placeExpress( pageContext );

				expect( displayOf( '#place_order' ) ).toBe( 'none' );
				expect( displayOf( '#express-wrapper' ) ).toBe( '' );
			}
		);

		test( 'shows "Place order" and hides the express container once another method is selected', () => {
			setDomWithEmptyExpress();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
			placeExpress();

			mockGetCurrentPaymentMethod.mockReturnValue( 'bacs' );
			jQuery( document.body ).trigger( 'payment_method_selected' );

			expect( displayOf( '#place_order' ) ).toBe( '' );
			expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
		} );

		test( 'keeps "Place order" and hides the express container when a saved token is selected', () => {
			setDomWithEmptyExpress();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
			mockIsSavedPayPalTokenSelected.mockReturnValue( true );

			placeExpress();

			expect( displayOf( '#place_order' ) ).toBe( '' );
			expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
		} );

		test( 'keeps "Place order" when the express container is not in the DOM', () => {
			document.body.innerHTML = '<div id="place_order"></div>';
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

			placeExpress();

			expect( displayOf( '#place_order' ) ).toBe( '' );
		} );
	} );

	test.each( [ 'cart', '' ] )(
		'does nothing on the %p page context: no visibility pass, no listener',
		( pageContext ) => {
			setDomWithEmptyExpress();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

			placeExpress( pageContext );

			expect( displayOf( '#place_order' ) ).toBe( '' );
			expect( displayOf( '#express-wrapper' ) ).toBe( '' );

			mockGetCurrentPaymentMethod.mockReturnValue( 'bacs' );
			jQuery( document.body ).trigger( 'payment_method_selected' );

			expect( displayOf( '#place_order' ) ).toBe( '' );
			expect( displayOf( '#express-wrapper' ) ).toBe( '' );
		}
	);

	test( 'does not register a listener when jQuery is absent', () => {
		setDomWithEmptyExpress();
		mockHasJQuery.mockReturnValue( false );
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

		placeExpress();
		mockGetCurrentPaymentMethod.mockReturnValue( 'bacs' );
		jQuery( document.body ).trigger( 'payment_method_selected' );

		expect( displayOf( '#place_order' ) ).toBe( 'none' );
	} );

	test( 'binds the checkout listeners once across placeExpressButtons() and revealMethodGateway()', () => {
		setDomWithEmptyExpress();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

		placeExpress();
		revealMethodGateway( { id: 'googlepay', wrapper: '#wallet-wrapper' } );
		placeExpress();

		mockGetCurrentPaymentMethod.mockClear();
		jQuery( document.body ).trigger( 'payment_method_selected' );

		expect( mockGetCurrentPaymentMethod ).toHaveBeenCalledTimes( 1 );
	} );

	describe( 'inline display on "Place order"', () => {
		test( 'is hidden with important priority', () => {
			setDomWithEmptyExpress();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

			placeExpress();

			const style = document.querySelector( '#place_order' ).style;
			expect( style.display ).toBe( 'none' );
			expect( style.getPropertyPriority( 'display' ) ).toBe(
				'important'
			);
		} );

		test( 'is removed entirely when shown again', () => {
			setDomWithEmptyExpress();
			mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
			placeExpress();

			mockGetCurrentPaymentMethod.mockReturnValue( 'bacs' );
			jQuery( document.body ).trigger( 'payment_method_selected' );

			const style = document.querySelector( '#place_order' ).style;
			expect( style.display ).toBe( '' );
			expect( style.getPropertyPriority( 'display' ) ).toBe( '' );
		} );
	} );
} );

describe( 'setExpressButtonsFailed()', () => {
	function setDomWithEmptyExpress() {
		document.body.innerHTML =
			'<div id="express-wrapper"></div>' + '<div id="place_order"></div>';
	}

	beforeEach( () => {
		setDomWithEmptyExpress();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
		placeExpress();
	} );

	test( 'shows "Place order" and hides the empty express container when PayPal is selected', () => {
		expect( displayOf( '#place_order' ) ).toBe( 'none' );

		setExpressButtonsFailed( true );

		expect( displayOf( '#place_order' ) ).toBe( '' );
		expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
	} );

	test( 'keeps the failure after the checkout DOM is replaced and updated_checkout fires', () => {
		setExpressButtonsFailed( true );

		setDomWithEmptyExpress();
		jQuery( document.body ).trigger( 'updated_checkout' );

		expect( displayOf( '#place_order' ) ).toBe( '' );
		expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
	} );

	test( 'keeps the failure after switching to another method and back', () => {
		setExpressButtonsFailed( true );

		mockGetCurrentPaymentMethod.mockReturnValue( 'bacs' );
		jQuery( document.body ).trigger( 'payment_method_selected' );
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
		jQuery( document.body ).trigger( 'payment_method_selected' );

		expect( displayOf( '#place_order' ) ).toBe( '' );
		expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
	} );

	test( 'hides "Place order" and shows the express container again when the failure is cleared', () => {
		setExpressButtonsFailed( true );

		setExpressButtonsFailed( false );

		expect( displayOf( '#place_order' ) ).toBe( 'none' );
		expect( displayOf( '#express-wrapper' ) ).toBe( '' );
	} );

	test( 'does not affect a selected wallet row that has a rendered button', () => {
		document.body.innerHTML =
			'<div id="wallet-wrapper"><button></button></div>' +
			'<div id="express-wrapper"></div>' +
			'<div id="place_order"></div>';
		mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );
		revealMethodGateway( { id: 'googlepay', wrapper: '#wallet-wrapper' } );

		setExpressButtonsFailed( true );

		expect( displayOf( '#place_order' ) ).toBe( 'none' );
		expect( displayOf( '#wallet-wrapper' ) ).toBe( '' );
	} );

	test.each( [ true, false ] )(
		'changes nothing with a saved token selected (failed: %p)',
		( failed ) => {
			mockIsSavedPayPalTokenSelected.mockReturnValue( true );

			setExpressButtonsFailed( failed );

			expect( displayOf( '#place_order' ) ).toBe( '' );
			expect( displayOf( '#express-wrapper' ) ).toBe( 'none' );
		}
	);
} );
