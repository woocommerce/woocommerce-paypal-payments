import { quoteFromCart, resolveOptionId } from './shippingQuote';

const cart = ( overrides = {} ) => ( {
	needs_shipping: true,
	totals: {
		total_price: '11050',
		total_shipping: '500',
		total_items: '10000',
		total_tax: '550',
		total_discount: '0',
		currency_minor_unit: 2,
	},
	shipping_rates: [
		{
			shipping_rates: [
				{
					rate_id: 'flat_rate:1',
					name: 'Flat rate',
					price: '500',
					currency_minor_unit: 2,
					selected: true,
				},
				{
					rate_id: 'flat_rate:2',
					name: 'Express',
					price: '1500',
					currency_minor_unit: 2,
					selected: false,
				},
			],
		},
	],
	...overrides,
} );

describe( 'quoteFromCart()', () => {
	test( 'normalises the Store API totals into decimal strings', () => {
		const quote = quoteFromCart( cart() );

		expect( quote.total ).toBe( '110.50' );
		expect( quote.shippingFee ).toBe( '5.00' );
		expect( quote.subtotal ).toBe( '100.00' );
		expect( quote.tax ).toBe( '5.50' );
		expect( quote.discount ).toBe( '0.00' );
	} );

	test( 'carries needsShipping straight from the cart', () => {
		expect( quoteFromCart( cart( { needs_shipping: true } ) ).needsShipping ).toBe(
			true
		);
		expect(
			quoteFromCart( cart( { needs_shipping: false } ) ).needsShipping
		).toBe( false );
	} );

	test( 'maps the first package rates to options, each with its own exponent', () => {
		const quote = quoteFromCart(
			cart( {
				shipping_rates: [
					{
						shipping_rates: [
							{
								rate_id: 'flat_rate:1',
								name: 'Flat rate',
								price: '500',
								currency_minor_unit: 3,
								selected: true,
							},
						],
					},
				],
			} )
		);

		expect( quote.options ).toEqual( [
			{ id: 'flat_rate:1', label: 'Flat rate', cost: '0.500' },
		] );
	} );

	test( 'reports the selected rate id, or null when nothing is selected', () => {
		expect( quoteFromCart( cart() ).selectedId ).toBe( 'flat_rate:1' );

		const noneSelected = cart();
		noneSelected.shipping_rates[ 0 ].shipping_rates.forEach(
			( rate ) => ( rate.selected = false )
		);
		expect( quoteFromCart( noneSelected ).selectedId ).toBeNull();
	} );

	test( 'reads only the first shipping package when there is more than one', () => {
		const quote = quoteFromCart(
			cart( {
				shipping_rates: [
					{
						shipping_rates: [
							{
								rate_id: 'flat_rate:1',
								name: 'Flat rate',
								price: '500',
								currency_minor_unit: 2,
								selected: true,
							},
						],
					},
					{
						shipping_rates: [
							{
								rate_id: 'flat_rate:9',
								name: 'Second package rate',
								price: '900',
								currency_minor_unit: 2,
								selected: false,
							},
						],
					},
				],
			} )
		);

		expect( quote.options ).toEqual( [
			{ id: 'flat_rate:1', label: 'Flat rate', cost: '5.00' },
		] );
		expect( console ).toHaveWarned();
	} );

	test( 'does not throw and returns empty totals/options for a null cart', () => {
		expect( () => quoteFromCart( null ) ).not.toThrow();

		const quote = quoteFromCart( null );

		expect( quote.total ).toBe( '' );
		expect( quote.needsShipping ).toBe( false );
		expect( quote.selectedId ).toBeNull();
		expect( quote.options ).toEqual( [] );
	} );

	test( 'returns no options when the cart has no shipping packages', () => {
		const quote = quoteFromCart( cart( { shipping_rates: [] } ) );

		expect( quote.options ).toEqual( [] );
		expect( quote.selectedId ).toBeNull();
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
