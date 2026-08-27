import { quoteFromResponse, resolveOptionId } from './shippingQuote';

describe( 'quoteFromResponse()', () => {
	const response = ( overrides = {} ) => ( {
		total: '110.50',
		shipping_fee: '5.00',
		subtotal: '100.00',
		tax: '5.50',
		discount: '0.00',
		needs_shipping: true,
		selected_rate_id: 'flat_rate:1',
		options: [ { id: 'flat_rate:1', label: 'Flat rate', cost: '5.00' } ],
		...overrides,
	} );

	test( 'maps the snake_case endpoint fields onto the camelCase quote shape', () => {
		expect( quoteFromResponse( response() ) ).toEqual( {
			total: '110.50',
			shippingFee: '5.00',
			subtotal: '100.00',
			tax: '5.50',
			discount: '0.00',
			needsShipping: true,
			selectedId: 'flat_rate:1',
			options: [
				{ id: 'flat_rate:1', label: 'Flat rate', cost: '5.00' },
			],
		} );
	} );

	test.each( [
		[ 'null', null ],
		[ 'an empty string', '' ],
		[ 'undefined', undefined ],
	] )(
		'reports selectedId as null when selected_rate_id is %s',
		( label, selectedRateId ) => {
			expect(
				quoteFromResponse(
					response( { selected_rate_id: selectedRateId } )
				).selectedId
			).toBeNull();
		}
	);

	test( 'defaults options to an empty list when the response carries none', () => {
		expect(
			quoteFromResponse( response( { options: undefined } ) ).options
		).toEqual( [] );
	} );

	test( 'does not throw and returns empty totals/options for a null response', () => {
		expect( () => quoteFromResponse( null ) ).not.toThrow();

		const quote = quoteFromResponse( null );

		expect( quote.total ).toBe( '' );
		expect( quote.needsShipping ).toBe( false );
		expect( quote.selectedId ).toBeNull();
		expect( quote.options ).toEqual( [] );
	} );
} );

describe( 'resolveOptionId()', () => {
	const quote = ( overrides = {} ) => ( {
		selectedId: 'flat_rate:1',
		options: [
			{ id: 'flat_rate:1', label: 'Flat rate', cost: '5.00' },
			{ id: 'flat_rate:2', label: 'Express', cost: '15.00' },
		],
		...overrides,
	} );

	test( 'returns null when the quote has no options at all', () => {
		expect(
			resolveOptionId( quote( { options: [] } ), 'flat_rate:1' )
		).toBeNull();
	} );

	test( 'returns the requested id when it matches an existing option', () => {
		expect( resolveOptionId( quote(), 'flat_rate:2' ) ).toBe(
			'flat_rate:2'
		);
	} );

	test( "treats Google's unselected sentinel as no request and falls back to the current selection", () => {
		expect(
			resolveOptionId( quote(), 'shipping_option_unselected' )
		).toBe( 'flat_rate:1' );
	} );

	test( 'falls back to the current selection when the requested id no longer exists', () => {
		expect( resolveOptionId( quote(), 'flat_rate:stale' ) ).toBe(
			'flat_rate:1'
		);
	} );

	test( 'falls back to the first option when nothing is requested and nothing is selected', () => {
		expect(
			resolveOptionId( quote( { selectedId: null } ), null )
		).toBe( 'flat_rate:1' );
	} );

	test( 'falls back to the first option when neither the request nor the current selection can be honoured', () => {
		expect(
			resolveOptionId(
				quote( { selectedId: null } ),
				'flat_rate:stale'
			)
		).toBe( 'flat_rate:1' );
	} );
} );
