import jQuery from 'jquery';

const mockProductForm = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	productForm: () => mockProductForm(),
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

import { watchProductAmount } from '../messages/productAmount';

// Mirrors REPRICE_DEBOUNCE_MS in the source.
const DEBOUNCE_MS = 150;

const config = ( amount ) =>
	amount === undefined ? { messages: {} } : { messages: { amount } };

/**
 * Builds a product form with the given quantity, registers it as what
 * productForm() returns, and hands it back for dispatching events.
 *
 * @param {string|number|undefined} quantity - The quantity field's value;
 *                                              omitted entirely when undefined.
 * @return {HTMLFormElement} The form.
 */
function renderProductForm( quantity ) {
	const quantityField =
		quantity === undefined
			? ''
			: `<input name="quantity" value="${ quantity }" />`;
	document.body.innerHTML = `<form>${ quantityField }</form>`;

	const form = document.querySelector( 'form' );
	mockProductForm.mockReturnValue( form );

	return form;
}

beforeEach( () => {
	jest.clearAllMocks();
	jest.useFakeTimers();
	mockHasJQuery.mockReturnValue( true );
	document.body.innerHTML = '';
	global.jQuery = jQuery;
} );

afterEach( () => {
	jest.useRealTimers();
} );

describe( 'watchProductAmount()', () => {
	test( 'the seed amount times the quantity is reported after a form event settles', () => {
		const form = renderProductForm( 3 );
		const onChange = jest.fn();
		watchProductAmount( config( '25.00' ), onChange );

		form.dispatchEvent( new Event( 'input' ) );
		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).toHaveBeenCalledWith( '75.00' );
	} );

	test( 'changing the quantity re-prices only once the debounce window elapses', () => {
		const form = renderProductForm( 1 );
		const onChange = jest.fn();
		watchProductAmount( config( '25.00' ), onChange );

		form.querySelector( '[name="quantity"]' ).value = '4';
		form.dispatchEvent( new Event( 'change' ) );

		expect( onChange ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).toHaveBeenCalledWith( '100.00' );
	} );

	test( 'found_variation switches the unit price to display_price, and the quantity still multiplies it', () => {
		const form = renderProductForm( 2 );
		const onChange = jest.fn();
		watchProductAmount( config( '25.00' ), onChange );

		jQuery( form ).trigger( 'found_variation', [ { display_price: 40 } ] );
		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).toHaveBeenCalledWith( '80.00' );
	} );

	test( 'reset_data reverts the unit price to the seed', () => {
		const form = renderProductForm( 2 );
		const onChange = jest.fn();
		watchProductAmount( config( '25.00' ), onChange );

		jQuery( form ).trigger( 'found_variation', [ { display_price: 40 } ] );
		jest.advanceTimersByTime( DEBOUNCE_MS );
		onChange.mockClear();

		jQuery( form ).trigger( 'reset_data' );
		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).toHaveBeenCalledWith( '50.00' );
	} );

	test( 'a second form event that recomputes the same amount does not call onChange again', () => {
		const form = renderProductForm( 1 );
		const onChange = jest.fn();
		watchProductAmount( config( '25.00' ), onChange );

		form.dispatchEvent( new Event( 'input' ) );
		jest.advanceTimersByTime( DEBOUNCE_MS );
		expect( onChange ).toHaveBeenCalledTimes( 1 );

		form.dispatchEvent( new Event( 'input' ) );
		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [
		[ 'the quantity field is absent', undefined ],
		[ 'the quantity field is not a number', 'abc' ],
		[ 'the quantity field is zero', '0' ],
		[ 'the quantity field is negative', '-5' ],
	] )( 'a missing or invalid quantity is treated as 1 when %s', ( _label, quantity ) => {
		const form = renderProductForm( quantity );
		const onChange = jest.fn();
		watchProductAmount( config( '25.00' ), onChange );

		form.dispatchEvent( new Event( 'input' ) );
		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).toHaveBeenCalledWith( '25.00' );
	} );

	test.each( [
		[ 'the seed is absent', undefined ],
		[ 'the seed is not a number', 'not-a-price' ],
	] )( 'onChange is never called with NaN when %s', ( _label, amount ) => {
		const form = renderProductForm( 2 );
		const onChange = jest.fn();
		watchProductAmount( config( amount ), onChange );

		form.dispatchEvent( new Event( 'input' ) );
		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).not.toHaveBeenCalled();
	} );

	test( 'the returned function unsubscribes, so further form events produce no onChange', () => {
		const form = renderProductForm( 1 );
		const onChange = jest.fn();
		const unsubscribe = watchProductAmount( config( '25.00' ), onChange );

		form.dispatchEvent( new Event( 'input' ) );
		jest.advanceTimersByTime( DEBOUNCE_MS );
		expect( onChange ).toHaveBeenCalledTimes( 1 );

		unsubscribe();

		form.querySelector( '[name="quantity"]' ).value = '9';
		form.dispatchEvent( new Event( 'input' ) );
		jQuery( form ).trigger( 'found_variation', [ { display_price: 99 } ] );
		jest.advanceTimersByTime( DEBOUNCE_MS );

		expect( onChange ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'returns a no-op unsubscribe and never calls onChange when there is no product form', () => {
		mockProductForm.mockReturnValue( null );
		const onChange = jest.fn();

		let unsubscribe;
		expect( () => {
			unsubscribe = watchProductAmount( config( '25.00' ), onChange );
		} ).not.toThrow();

		expect( () => unsubscribe() ).not.toThrow();
		expect( onChange ).not.toHaveBeenCalled();
	} );
} );
