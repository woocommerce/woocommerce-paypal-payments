/* global jest, describe, test, expect, beforeEach, afterEach */

import jQuery from 'jquery';

// isDisabled() is backed by the wrapper's real class list, and disable()/enable()
// mutate it the same way the real ButtonDisabler's `ppcp-disabled` class does, so
// the module's own transition guard (isDisabled() vs the new state) is genuinely
// exercised rather than assumed.
const getElement = ( selectorOrElement ) =>
	typeof selectorOrElement === 'string'
		? document.querySelector( selectorOrElement )
		: selectorOrElement;

jest.mock( '@ppcp-button/Helper/ButtonDisabler', () => ( {
	disable: jest.fn( ( selectorOrElement ) => {
		getElement( selectorOrElement ).classList.add( 'ppcp-disabled' );
	} ),
	enable: jest.fn( ( selectorOrElement ) => {
		getElement( selectorOrElement ).classList.remove( 'ppcp-disabled' );
	} ),
	isDisabled: jest.fn( ( selectorOrElement ) =>
		getElement( selectorOrElement ).classList.contains( 'ppcp-disabled' )
	),
} ) );

import { disable, enable } from '@ppcp-button/Helper/ButtonDisabler';
import {
	initProductButtonGate,
	resetProductButtonGate,
} from './ProductButtonGate';

const WRAPPER_SELECTOR = '#ppc-button-googlepay-container';

function renderPage( {
	addToCartDisabled = false,
	withAddToCart = true,
} = {} ) {
	document.body.innerHTML = `
		<div id="ppc-button-googlepay-container"></div>
		<form class="cart">
			${
				withAddToCart
					? `<button type="submit" class="single_add_to_cart_button${
							addToCartDisabled ? ' disabled' : ''
					  }"></button>`
					: ''
			}
		</form>
	`;
}

function wrapperIsDisabled() {
	return document
		.querySelector( WRAPPER_SELECTOR )
		.classList.contains( 'ppcp-disabled' );
}

beforeEach( () => {
	jest.clearAllMocks();
	document.body.innerHTML = '';
	resetProductButtonGate();
	global.jQuery = jQuery;
} );

afterEach( () => {
	delete global.jQuery;
	document.body.innerHTML = '';
} );

describe( 'initProductButtonGate()', () => {
	test.each( [ 'checkout', 'cart', undefined ] )(
		'does nothing when context is %s, even with a disabled add-to-cart button',
		( context ) => {
			renderPage( { addToCartDisabled: true } );

			initProductButtonGate( WRAPPER_SELECTOR, context );

			expect( wrapperIsDisabled() ).toBe( false );
			expect( disable ).not.toHaveBeenCalled();
		}
	);

	test( 'does nothing when wrapperSelector is falsy', () => {
		renderPage( { addToCartDisabled: true } );

		initProductButtonGate( '', 'product' );

		expect( wrapperIsDisabled() ).toBe( false );
		expect( disable ).not.toHaveBeenCalled();
	} );

	test( 'does nothing when there is no form.cart on the page', () => {
		document.body.innerHTML = `<div id="ppc-button-googlepay-container"></div>`;

		initProductButtonGate( WRAPPER_SELECTOR, 'product' );

		expect( wrapperIsDisabled() ).toBe( false );
		expect( disable ).not.toHaveBeenCalled();
		expect( enable ).not.toHaveBeenCalled();
	} );

	test( 'disables the wrapper on init when the add-to-cart button is disabled', () => {
		renderPage( { addToCartDisabled: true } );

		initProductButtonGate( WRAPPER_SELECTOR, 'product' );

		expect( wrapperIsDisabled() ).toBe( true );
	} );

	test( 'leaves the wrapper enabled on init when the add-to-cart button is not disabled', () => {
		renderPage( { addToCartDisabled: false } );

		initProductButtonGate( WRAPPER_SELECTOR, 'product' );

		expect( wrapperIsDisabled() ).toBe( false );
		expect( disable ).not.toHaveBeenCalled();
	} );

	test( 'leaves the wrapper enabled on init when there is no add-to-cart button at all', () => {
		renderPage( { withAddToCart: false } );

		initProductButtonGate( WRAPPER_SELECTOR, 'product' );

		expect( wrapperIsDisabled() ).toBe( false );
		expect( disable ).not.toHaveBeenCalled();
	} );

	test( 'enables the wrapper once the disabled class is removed and the form changes, as when a shopper picks a variation', () => {
		renderPage( { addToCartDisabled: true } );
		initProductButtonGate( WRAPPER_SELECTOR, 'product' );
		expect( wrapperIsDisabled() ).toBe( true );

		document
			.querySelector( '.single_add_to_cart_button' )
			.classList.remove( 'disabled' );
		document
			.querySelector( 'form.cart' )
			.dispatchEvent( new Event( 'change' ) );

		expect( wrapperIsDisabled() ).toBe( false );
	} );

	test( 'disables the wrapper again once the disabled class returns and the form changes, as when the selection is cleared', () => {
		renderPage( { addToCartDisabled: false } );
		initProductButtonGate( WRAPPER_SELECTOR, 'product' );
		expect( wrapperIsDisabled() ).toBe( false );

		document
			.querySelector( '.single_add_to_cart_button' )
			.classList.add( 'disabled' );
		document
			.querySelector( 'form.cart' )
			.dispatchEvent( new Event( 'change' ) );

		expect( wrapperIsDisabled() ).toBe( true );
	} );

	test( 'a repeated init call does not double-attach: a single transition still disables only once', () => {
		renderPage( { addToCartDisabled: false } );
		initProductButtonGate( WRAPPER_SELECTOR, 'product' );
		initProductButtonGate( WRAPPER_SELECTOR, 'product' );
		disable.mockClear();

		document
			.querySelector( '.single_add_to_cart_button' )
			.classList.add( 'disabled' );
		document
			.querySelector( 'form.cart' )
			.dispatchEvent( new Event( 'change' ) );

		expect( wrapperIsDisabled() ).toBe( true );
		expect( disable ).toHaveBeenCalledTimes( 1 );
	} );
} );
