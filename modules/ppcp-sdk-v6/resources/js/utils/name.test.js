import { splitFullName } from './name';

describe( 'splitFullName()', () => {
	test.each( [
		[ 'John Doe', [ 'John', 'Doe' ] ],
		[ 'Cher', [ 'Cher', '' ] ],
		[ '', [ '', '' ] ],
		[ '   ', [ '', '' ] ],
		[ ' John Doe ', [ 'John', 'Doe' ] ],
		[ 'John  Doe', [ 'John', 'Doe' ] ],
	] )( '%j splits into %j', ( fullName, expected ) => {
		expect( splitFullName( fullName ) ).toEqual( expected );
	} );

	test( 'a three-word name keeps the middle name with the given name and takes only the last word as the surname', () => {
		expect( splitFullName( 'John Fitzgerald Kennedy' ) ).toEqual( [
			'John Fitzgerald',
			'Kennedy',
		] );
	} );

	test( 'mixed and repeated whitespace collapses and the given name rejoins with single spaces', () => {
		expect( splitFullName( 'John\t Fitzgerald   Kennedy' ) ).toEqual( [
			'John Fitzgerald',
			'Kennedy',
		] );
	} );

	test.each( [ [ 'John\tDoe' ], [ 'John\nDoe' ] ] )(
		'a tab or newline (%j) separates names just like a space does',
		( fullName ) => {
			expect( splitFullName( fullName ) ).toEqual( [ 'John', 'Doe' ] );
		}
	);

	test.each( [
		[ 'Anne-Marie Dupont', [ 'Anne-Marie', 'Dupont' ] ],
		[ "Sinead O'Connor", [ 'Sinead', "O'Connor" ] ],
		[ "Marco D'Angelo", [ 'Marco', "D'Angelo" ] ],
		[ 'Björn Müller', [ 'Björn', 'Müller' ] ],
		[ 'José Silva', [ 'José', 'Silva' ] ],
	] )(
		'special characters inside a token survive untouched: %j splits into %j',
		( fullName, expected ) => {
			expect( splitFullName( fullName ) ).toEqual( expected );
		}
	);

	test( 'a non-breaking space (U+00A0) separates names just like a regular space does', () => {
		expect( splitFullName( 'John\u00A0Doe' ) ).toEqual( [ 'John', 'Doe' ] );
	} );

	describe( 'known limitation of the last-token rule', () => {
		// Multi-token surnames are misidentified because only the last word is
		// treated as the surname. This is the accepted trade-off of the rule,
		// documented here rather than treated as a bug to fix.
		test.each( [
			[ 'Jan van der Berg', [ 'Jan van der', 'Berg' ] ],
			[ 'José García Pérez', [ 'José García', 'Pérez' ] ],
			[ 'John Van Doe', [ 'John Van', 'Doe' ] ],
		] )( '%j splits into %j', ( fullName, expected ) => {
			expect( splitFullName( fullName ) ).toEqual( expected );
		} );
	} );

	test( 'throws when given a non-string argument', () => {
		// This is why both call sites guard non-string input before calling.
		expect( () => splitFullName( undefined ) ).toThrow();
	} );
} );
