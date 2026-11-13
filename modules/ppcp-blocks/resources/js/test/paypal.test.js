import { isInlineExpressFundingSource } from '../Helper/FundingSource';

describe( 'isInlineExpressFundingSource', () => {
	it( 'returns true for the card funding source', () => {
		expect( isInlineExpressFundingSource( 'card' ) ).toBe( true );
	} );

	it.each( [ 'paypal', 'paylater', 'venmo', undefined, '' ] )(
		'returns false for %p',
		( fundingSource ) => {
			expect( isInlineExpressFundingSource( fundingSource ) ).toBe(
				false
			);
		}
	);
} );
