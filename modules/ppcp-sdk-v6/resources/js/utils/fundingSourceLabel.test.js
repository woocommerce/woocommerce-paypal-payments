import { fundingSourceLabel } from './fundingSourceLabel';
import { FundingSources } from './fundingSources';

describe( 'fundingSourceLabel', () => {
	test.each( [
		[ FundingSources.VENMO, 'Venmo' ],
		[ FundingSources.GOOGLEPAY, 'Google Pay' ],
		[ FundingSources.APPLEPAY, 'Apple Pay' ],
		[ FundingSources.PAYLATER, 'Pay Later' ],
		[ FundingSources.PAYPAL, 'PayPal' ],
	] )( 'labels %s as "%s"', ( fundingSource, expectedLabel ) => {
		expect( fundingSourceLabel( fundingSource ) ).toBe( expectedLabel );
	} );

	test.each( [ 'unknown', undefined, null, '' ] )(
		'falls back to the PayPal label for an unrecognized funding source %p',
		( fundingSource ) => {
			expect( fundingSourceLabel( fundingSource ) ).toBe( 'PayPal' );
		}
	);
} );
