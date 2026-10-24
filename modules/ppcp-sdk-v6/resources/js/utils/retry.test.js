import { backoffDelay, isRetryable } from './retry';

const failure = ( status ) =>
	Object.assign( new Error( 'failed' ), status ? { status } : {} );

describe( 'isRetryable()', () => {
	test.each( [
		[ 'a failure without an answer', failure(), true ],
		[ 'a request timeout (408)', failure( 408 ), true ],
		[ 'a rate limit (429)', failure( 429 ), true ],
		[ 'a server error (500)', failure( 500 ), true ],
		[ 'an unavailable server (503)', failure( 503 ), true ],
		[ 'a success without usable JSON (200)', failure( 200 ), true ],
		[ 'a bad request (400)', failure( 400 ), false ],
		[ 'a forbidden request (403)', failure( 403 ), false ],
		[ 'a missing endpoint (404)', failure( 404 ), false ],
	] )( 'answers for %s: %s', ( label, error, expected ) => {
		expect( isRetryable( error ) ).toBe( expected );
	} );

	test( 'treats a missing error as worth retrying', () => {
		expect( isRetryable( undefined ) ).toBe( true );
	} );
} );

describe( 'backoffDelay()', () => {
	test.each( [
		[ 1, 5 ],
		[ 2, 10 ],
		[ 3, 20 ],
		[ 4, 40 ],
		[ 5, 60 ],
		[ 10, 60 ],
	] )( 'waits after %d failures %d, capped at 60', ( failures, expected ) => {
		expect( backoffDelay( failures, 5, 60 ) ).toBe( expected );
	} );
} );
