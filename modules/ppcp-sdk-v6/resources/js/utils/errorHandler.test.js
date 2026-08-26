// Declared outside the factory so their identity is stable across tests;
// the factory only needs to delegate to them.
const mockClear = jest.fn();
const mockMessage = jest.fn();
const mockMessages = jest.fn();
const mockGenericError = jest.fn();

jest.mock( '@ppcp-button/ErrorHandler', () =>
	jest.fn().mockImplementation( () => ( {
		clear: mockClear,
		message: mockMessage,
		messages: mockMessages,
		genericError: mockGenericError,
	} ) )
);

const mockHasJQuery = jest.fn( () => false );
jest.mock( './api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

import ErrorHandler from '@ppcp-button/ErrorHandler';
import { setErrorLabels, handleError, handleWarning } from './errorHandler';

beforeEach( () => {
	jest.clearAllMocks();
	mockHasJQuery.mockReturnValue( false );
	document.body.innerHTML = '';
	setErrorLabels( { generic_error: 'Something went wrong.' } );
} );

describe( 'handleError', () => {
	test( 'shows the server-provided validation messages verbatim and clears any existing notice first', () => {
		handleError( { errors: [ 'Invalid postal code.', 'Missing city.' ] } );

		expect( mockClear ).toHaveBeenCalled();
		expect( mockMessages ).toHaveBeenCalledWith( [
			'Invalid postal code.',
			'Missing city.',
		] );
		expect( mockGenericError ).not.toHaveBeenCalled();
		expect( console ).toHaveErrored();
	} );

	test( 'shows a user-facing message verbatim and clears any existing notice first', () => {
		handleError( { isUserFacing: true, message: 'Card declined.' } );

		expect( mockClear ).toHaveBeenCalled();
		expect( mockMessage ).toHaveBeenCalledWith( 'Card declined.' );
		expect( mockGenericError ).not.toHaveBeenCalled();
		expect( console ).toHaveErrored();
	} );

	test( 'prefers the errors list over a user-facing message when both are present', () => {
		handleError( {
			errors: [ 'Invalid postal code.' ],
			isUserFacing: true,
			message: 'Card declined.',
		} );

		expect( mockMessages ).toHaveBeenCalledWith( [
			'Invalid postal code.',
		] );
		expect( mockMessage ).not.toHaveBeenCalled();
		expect( console ).toHaveErrored();
	} );

	test( 'falls back to the translated generic label for an internal error', () => {
		handleError( new Error( 'Network down.' ) );

		expect( mockGenericError ).toHaveBeenCalled();
		expect( mockMessage ).not.toHaveBeenCalled();
		expect( mockMessages ).not.toHaveBeenCalled();
		expect( console ).toHaveErrored();
	} );

	test( 'shows nothing beyond logging when no generic label is configured for an internal error', () => {
		setErrorLabels( {} );

		handleError( new Error( 'Network down.' ) );

		expect( mockGenericError ).not.toHaveBeenCalled();
		expect( mockMessage ).not.toHaveBeenCalled();
		expect( mockMessages ).not.toHaveBeenCalled();
		expect( console ).toHaveErrored();
	} );

	test( 'treats an undefined labels object the same as an empty one', () => {
		setErrorLabels( undefined );

		handleError( new Error( 'Network down.' ) );

		expect( mockGenericError ).not.toHaveBeenCalled();
		expect( console ).toHaveErrored();
	} );

	test.each( [
		[
			'.woocommerce-notices-wrapper',
			'<div class="woocommerce-notices-wrapper"></div>',
		],
		[ '.woocommerce', '<div class="woocommerce"></div>' ],
	] )( 'renders into the %s element when present', ( selector, html ) => {
		document.body.innerHTML = html;

		handleError( new Error( 'Network down.' ) );

		expect( ErrorHandler ).toHaveBeenCalledWith(
			'Something went wrong.',
			document.querySelector( selector )
		);
		expect( console ).toHaveErrored();
	} );

	test( 'prefers .woocommerce-notices-wrapper over .woocommerce when both are present', () => {
		document.body.innerHTML =
			'<div class="woocommerce-notices-wrapper"></div>' +
			'<div class="woocommerce"></div>';

		handleError( new Error( 'Network down.' ) );

		expect( ErrorHandler ).toHaveBeenCalledWith(
			'Something went wrong.',
			document.querySelector( '.woocommerce-notices-wrapper' )
		);
		expect( console ).toHaveErrored();
	} );

	test( 'falls back to document.body when neither notice wrapper is present', () => {
		handleError( new Error( 'Network down.' ) );

		expect( ErrorHandler ).toHaveBeenCalledWith(
			'Something went wrong.',
			document.body
		);
		expect( console ).toHaveErrored();
	} );

	test( 'triggers update_checkout when the error is a refresh signal and jQuery is available', () => {
		mockHasJQuery.mockReturnValue( true );
		const trigger = jest.fn();
		global.jQuery = jest.fn( () => ( { trigger } ) );

		handleError( { refresh: true } );

		expect( trigger ).toHaveBeenCalledWith( 'update_checkout' );
		expect( console ).toHaveErrored();

		delete global.jQuery;
	} );

	test( 'does not trigger update_checkout when jQuery is unavailable, even for a refresh signal', () => {
		mockHasJQuery.mockReturnValue( false );

		expect( () => handleError( { refresh: true } ) ).not.toThrow();
		expect( console ).toHaveErrored();
	} );
} );

describe( 'handleWarning', () => {
	test( 'logs only, showing nothing, when no card_declined label is configured', () => {
		handleWarning( { code: 'INSTRUMENT_DECLINED' } );

		expect( mockMessage ).not.toHaveBeenCalled();
		expect( mockClear ).not.toHaveBeenCalled();
		expect( console ).toHaveWarned();
	} );

	test( 'shows the translated card_declined message when configured', () => {
		setErrorLabels( {
			generic_error: 'Something went wrong.',
			card_declined: 'Your card was declined.',
		} );

		handleWarning( { code: 'INSTRUMENT_DECLINED' } );

		expect( mockMessage ).toHaveBeenCalledWith( 'Your card was declined.' );
		expect( console ).toHaveWarned();
	} );

	test( 'leaves existing notices alone, unlike handleError', () => {
		setErrorLabels( { card_declined: 'Your card was declined.' } );

		handleWarning( { code: 'INSTRUMENT_DECLINED' } );

		expect( mockClear ).not.toHaveBeenCalled();
		expect( console ).toHaveWarned();
	} );
} );
