import { formatPrice } from './formatPrice';

describe( 'formatPrice', () => {

    const cases = [
        [ 100, 'USD', '$100.00 USD' ],
        [ 100, 'CAD', '$100.00 CAD' ],
        [ 100, 'AUD', '$100.00 AUD' ],
        [ 100, 'EUR', '€100.00' ],
        [ 100, 'GBP', '£100.00' ],
        [ 100.999, 'GBP', '£101.00' ],
        [ 100.4, 'GBP', '£100.40' ],
    ];

    it.each(cases)('%d %s should be formatted as %s', (amount, currency, expectation) => {
       expect(formatPrice(amount, currency)).toBe(expectation);
    });

    it( 'should handle when currency is not supported', () => {
        const spy = jest.spyOn( console, 'error').mockImplementation(() => {});
        expect( formatPrice( 100.00, 'XYZ' ) ).toBe( '100.00' );

        expect(spy).toHaveBeenCalledWith( 'Unsupported currency: XYZ' );
        spy.mockRestore();
    } );

});
