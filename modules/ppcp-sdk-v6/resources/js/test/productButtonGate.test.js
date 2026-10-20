import jQuery from 'jquery';

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

jest.mock( '../endpointsAdapter', () => ( {
	// Not stubbed: it only reads the DOM the fixtures below build, and the
	// selector pair it uses must live in one place.
	productForm: jest.requireActual( '../endpointsAdapter' ).productForm,
} ) );

import {
	initProductButtonGate,
	resetProductButtonGate,
} from '../utils/productButtonGate';

const WRAPPER_ID = 'ppc-button-ppcp-gateway-v6';
const WRAPPER_SELECTOR = `#${ WRAPPER_ID }`;

const baseConfig = ( overrides = {} ) => ( {
	page_context: 'product',
	wrapper: WRAPPER_SELECTOR,
	...overrides,
} );

/**
 * Builds a classic product form plus its native add-to-cart button and the
 * express-button wrapper this gate controls.
 *
 * The add-to-cart button is deliberately `type="button"`, not `type="submit"`,
 * so it never itself matches the `:submit` selector the gate's mouseup handler
 * clicks — keeping the "did the native submit get triggered" assertions
 * unambiguous.
 *
 * @param {Object}  options
 * @param {boolean} options.hasAddToCartButton
 * @param {boolean} options.disabled
 * @return {HTMLFormElement} The rendered product form.
 */
function buildDom( { hasAddToCartButton = true, disabled = false } = {} ) {
	const addToCartButtonHtml = hasAddToCartButton
		? `<button type="button" class="single_add_to_cart_button${
				disabled ? ' disabled' : ''
		  }"></button>`
		: '';

	document.body.innerHTML = `
		<form class="cart variations_form">
			<input name="add-to-cart" value="1" />
			${ addToCartButtonHtml }
			<button type="submit" class="submit-trigger"></button>
		</form>
		<div id="${ WRAPPER_ID }"></div>
	`;

	return document.querySelector( 'form' );
}

function wrapperEl() {
	return document.querySelector( WRAPPER_SELECTOR );
}

/**
 * Attaches a spy to the form's native submit trigger and returns it, so tests
 * can assert whether the gate's mouseup handler routed a click to it.
 *
 * @param {HTMLFormElement} form
 * @return {jest.Mock}
 */
function spyOnSubmit( form ) {
	const spy = jest.fn();
	form.querySelector( '.submit-trigger' ).addEventListener( 'click', spy );
	return spy;
}

// jsdom's internal form-submission algorithm (run when a submit button's
// default click action fires) hits an unimplemented path and throws. Every
// test in this file that fires a wrapper mouseup while disabled ends up
// clicking the form's :submit control, so the real submission is cancelled
// globally by capturing the submit event itself, rather than in only the
// tests that happen to spy on the click.
const preventSubmit = ( event ) => event.preventDefault();

beforeEach( () => {
	mockHasJQuery.mockReturnValue( true );
	global.jQuery = jQuery;
	document.addEventListener( 'submit', preventSubmit, true );
} );

afterEach( () => {
	resetProductButtonGate();
	document.body.innerHTML = '';
	delete global.jQuery;
	document.removeEventListener( 'submit', preventSubmit, true );
} );

describe( 'initProductButtonGate()', () => {
	describe( 'page_context guard', () => {
		test.each( [ 'checkout', 'cart', 'mini-cart', undefined ] )(
			'does nothing when page_context is %s',
			( pageContext ) => {
				const form = buildDom( { disabled: true } );

				initProductButtonGate(
					baseConfig( { page_context: pageContext } )
				);

				expect(
					wrapperEl().classList.contains( 'ppcp-disabled' )
				).toBe( false );

				// No listeners were attached: toggling the button and firing
				// the events the gate would otherwise react to changes nothing.
				form
					.querySelector( '.single_add_to_cart_button' )
					.classList.remove( 'disabled' );
				form.dispatchEvent( new Event( 'change' ) );

				expect(
					wrapperEl().classList.contains( 'ppcp-disabled' )
				).toBe( false );
			}
		);
	} );

	describe( 'initial gating decision', () => {
		test( 'adds ppcp-disabled to the wrapper when the add-to-cart button is disabled', () => {
			buildDom( { disabled: true } );

			initProductButtonGate( baseConfig() );

			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( true );
		} );

		test( 'leaves the wrapper enabled when the add-to-cart button is not disabled', () => {
			buildDom( { disabled: false } );

			initProductButtonGate( baseConfig() );

			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( false );
		} );

		test( 'leaves the wrapper enabled when the form has no classic add-to-cart button (simple product)', () => {
			buildDom( { hasAddToCartButton: false } );

			initProductButtonGate( baseConfig() );

			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( false );
		} );

		test( 'does nothing when the page has no product form', () => {
			document.body.innerHTML = `<div id="${ WRAPPER_ID }"></div>`;

			initProductButtonGate( baseConfig() );

			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( false );
		} );
	} );

	describe( 'mouseup on a disabled wrapper', () => {
		test( 'routes the click to the form\'s native submit', () => {
			const form = buildDom( { disabled: true } );
			initProductButtonGate( baseConfig() );
			const submitSpy = spyOnSubmit( form );

			wrapperEl().dispatchEvent(
				new Event( 'mouseup', { bubbles: true } )
			);

			expect( submitSpy ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'stops immediate propagation so no other handler on the wrapper receives it', () => {
			buildDom( { disabled: true } );
			initProductButtonGate( baseConfig() );
			const wrapper = wrapperEl();
			const laterListener = jest.fn();
			wrapper.addEventListener( 'mouseup', laterListener );

			wrapper.dispatchEvent(
				new Event( 'mouseup', { bubbles: true } )
			);

			expect( laterListener ).not.toHaveBeenCalled();
		} );

		test( 'does not submit the form when the wrapper is enabled', () => {
			const form = buildDom( { disabled: false } );
			initProductButtonGate( baseConfig() );
			const submitSpy = spyOnSubmit( form );

			wrapperEl().dispatchEvent(
				new Event( 'mouseup', { bubbles: true } )
			);

			expect( submitSpy ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'transition guard against stacking mouseup handlers', () => {
		/**
		 * Regression test: disable() binds a fresh mouseup handler on every
		 * call, so re-running it without an intervening enable() would stack
		 * handlers and submit the form once per stacked handler.
		 */
		test( 'a burst of sync triggers while the button stays disabled still results in exactly one submit per click', () => {
			const form = buildDom( { disabled: true } );
			initProductButtonGate( baseConfig() );
			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( true );

			form.dispatchEvent( new Event( 'change' ) );
			form.dispatchEvent( new Event( 'change' ) );
			form.dispatchEvent( new Event( 'change' ) );

			const submitSpy = spyOnSubmit( form );
			wrapperEl().dispatchEvent(
				new Event( 'mouseup', { bubbles: true } )
			);

			expect( submitSpy ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'calling init again for the same page does not attach a second set of listeners', () => {
			const form = buildDom( { disabled: false } );
			initProductButtonGate( baseConfig() );
			initProductButtonGate( baseConfig() );

			form
				.querySelector( '.single_add_to_cart_button' )
				.classList.add( 'disabled' );
			form.dispatchEvent( new Event( 'change' ) );
			form.dispatchEvent( new Event( 'change' ) );

			const submitSpy = spyOnSubmit( form );
			wrapperEl().dispatchEvent(
				new Event( 'mouseup', { bubbles: true } )
			);

			expect( submitSpy ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 're-enabling once the button stops being disabled', () => {
		test( 'removes ppcp-disabled from the wrapper and stops routing clicks to the native submit', () => {
			const form = buildDom( { disabled: true } );
			initProductButtonGate( baseConfig() );
			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( true );

			form
				.querySelector( '.single_add_to_cart_button' )
				.classList.remove( 'disabled' );
			form.dispatchEvent( new Event( 'change' ) );

			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( false );

			const submitSpy = spyOnSubmit( form );
			wrapperEl().dispatchEvent(
				new Event( 'mouseup', { bubbles: true } )
			);

			expect( submitSpy ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'MutationObserver on the add-to-cart button', () => {
		test( 're-syncs the wrapper when WooCommerce toggles the disabled class', async () => {
			const form = buildDom( { disabled: false } );
			initProductButtonGate( baseConfig() );
			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( false );

			form
				.querySelector( '.single_add_to_cart_button' )
				.classList.add( 'disabled' );
			await Promise.resolve();
			await Promise.resolve();

			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( true );
		} );
	} );

	describe( 'jQuery variation events', () => {
		test.each( [ 'found_variation', 'reset_data' ] )(
			'a %s event on the form re-syncs the wrapper',
			( eventName ) => {
				const form = buildDom( { disabled: false } );
				initProductButtonGate( baseConfig() );

				form
					.querySelector( '.single_add_to_cart_button' )
					.classList.add( 'disabled' );
				jQuery( form ).trigger( eventName );

				expect(
					wrapperEl().classList.contains( 'ppcp-disabled' )
				).toBe( true );
			}
		);

		test( 'is not wired up when hasJQuery() reports jQuery is unavailable', () => {
			mockHasJQuery.mockReturnValue( false );
			const form = buildDom( { disabled: false } );
			initProductButtonGate( baseConfig() );

			form
				.querySelector( '.single_add_to_cart_button' )
				.classList.add( 'disabled' );
			jQuery( form ).trigger( 'found_variation' );

			expect(
				wrapperEl().classList.contains( 'ppcp-disabled' )
			).toBe( false );
		} );
	} );
} );
