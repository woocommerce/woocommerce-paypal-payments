import { renderIsObsolete } from './renderOverrides';

describe( 'renderIsObsolete()', () => {
	test( 'returns false when overrides is undefined', () => {
		expect( renderIsObsolete( undefined ) ).toBe( false );
	} );

	test( 'returns false when overrides carries no isObsolete', () => {
		expect( renderIsObsolete( {} ) ).toBe( false );
	} );

	test.each( [
		[ true, true ],
		[ false, false ],
	] )( 'returns %s when isObsolete() returns %s', ( result, expected ) => {
		expect(
			renderIsObsolete( { isObsolete: () => result } )
		).toBe( expected );
	} );

	test( 'coerces a truthy, non-boolean isObsolete() result to a boolean', () => {
		expect( renderIsObsolete( { isObsolete: () => 1 } ) ).toBe( true );
	} );
} );
