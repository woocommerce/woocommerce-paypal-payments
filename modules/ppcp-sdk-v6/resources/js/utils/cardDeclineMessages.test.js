import { userFacingMessage, userFacingError } from './cardDeclineMessages';

describe( 'userFacingMessage()', () => {
	test( 'returns the fallback when the error is not marked user-facing', () => {
		const error = new Error( 'PayPal SDK internal failure XYZ' );

		expect( userFacingMessage( error, 'Fallback message.' ) ).toBe(
			'Fallback message.'
		);
	} );

	test( 'returns the error message when it is marked user-facing', () => {
		const error = new Error( 'Your card was declined by the bank.' );
		error.isUserFacing = true;

		expect( userFacingMessage( error, 'Fallback message.' ) ).toBe(
			'Your card was declined by the bank.'
		);
	} );

	test( 'returns the fallback when a user-facing error has no message', () => {
		const error = new Error( '' );
		error.isUserFacing = true;

		expect( userFacingMessage( error, 'Fallback message.' ) ).toBe(
			'Fallback message.'
		);
	} );

	test( 'returns the fallback for a null error', () => {
		expect( userFacingMessage( null, 'Fallback message.' ) ).toBe(
			'Fallback message.'
		);
	} );
} );

describe( 'userFacingError()', () => {
	test( 'builds an Error carrying the given message and the isUserFacing flag', () => {
		const error = userFacingError( 'This card could not be authorized.' );

		expect( error ).toBeInstanceOf( Error );
		expect( error.message ).toBe( 'This card could not be authorized.' );
		expect( error.isUserFacing ).toBe( true );
	} );
} );
