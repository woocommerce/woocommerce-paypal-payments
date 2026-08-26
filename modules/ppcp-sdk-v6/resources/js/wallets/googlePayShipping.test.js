import { buildPaymentDataCallbacks } from './googlePayShipping';

const config = ( overrides = {} ) => ( {
	labels: { shipping_unserviceable: 'We cannot ship to this address.' },
	...overrides,
} );

const quote = ( overrides = {} ) => ( {
	total: '10.00',
	selectedId: 'flat_rate:1',
	options: [
		{ id: 'flat_rate:1', label: 'Flat rate', cost: '5.00' },
		{ id: 'flat_rate:2', label: 'Express', cost: '15.00' },
	],
	...overrides,
} );

const paymentData = ( overrides = {} ) => ( {
	shippingAddress: {
		countryCode: 'US',
		administrativeArea: 'CA',
		postalCode: '94105',
		locality: 'San Francisco',
	},
	callbackTrigger: 'INITIALIZE',
	...overrides,
} );

function makeShipping( implementation, current = null ) {
	return {
		quote: jest.fn( implementation ),
		current: jest.fn( () => current ),
	};
}

function callbacks( { shipping, currencyCode = 'USD', countryCode = 'US', overrideConfig } ) {
	return buildPaymentDataCallbacks( {
		config: overrideConfig ?? config(),
		currencyCode,
		countryCode,
		shipping,
	} ).onPaymentDataChanged;
}

describe( 'buildPaymentDataCallbacks().onPaymentDataChanged()', () => {
	test( 'prices the address, then answers with the transaction total and mapped options', async () => {
		const shipping = makeShipping( async () => quote() );

		const result = await callbacks( { shipping } )( paymentData() );

		expect( shipping.quote ).toHaveBeenCalledTimes( 1 );
		expect( result.newTransactionInfo ).toEqual( {
			countryCode: 'US',
			currencyCode: 'USD',
			totalPriceStatus: 'FINAL',
			totalPrice: '10.00',
		} );
		expect( result.newShippingOptionParameters ).toEqual( {
			defaultSelectedOptionId: 'flat_rate:1',
			shippingOptions: [
				{ id: 'flat_rate:1', label: 'Flat rate', description: '$5.00' },
				{ id: 'flat_rate:2', label: 'Express', description: '$15.00' },
			],
		} );
	} );

	test( "prices the address against the sheet's picked option, resolved from the previous quote", async () => {
		const shipping = makeShipping(
			async () => quote( { selectedId: 'flat_rate:2', total: '20.00' } ),
			quote()
		);

		const result = await callbacks( { shipping } )(
			paymentData( { shippingOptionData: { id: 'flat_rate:2' } } )
		);

		expect( shipping.quote ).toHaveBeenCalledTimes( 1 );
		expect( shipping.quote ).toHaveBeenCalledWith(
			expect.objectContaining( { rateId: 'flat_rate:2' } )
		);
		expect( result.newTransactionInfo.totalPrice ).toBe( '20.00' );
	} );

	test( "falls back to the previous quote's selected rate when the sheet reports no option", async () => {
		const shipping = makeShipping( async () => quote(), quote() );

		await callbacks( { shipping } )( paymentData() );

		expect( shipping.quote ).toHaveBeenCalledWith(
			expect.objectContaining( { rateId: 'flat_rate:1' } )
		);
	} );

	test( "treats Google's unselected sentinel as no request, falling back to the previous selection", async () => {
		const shipping = makeShipping( async () => quote(), quote() );

		await callbacks( { shipping } )(
			paymentData( {
				shippingOptionData: { id: 'shipping_option_unselected' },
			} )
		);

		expect( shipping.quote ).toHaveBeenCalledWith(
			expect.objectContaining( { rateId: 'flat_rate:1' } )
		);
	} );

	test( 'answers with SHIPPING_ADDRESS_UNSERVICEABLE when the address has no shippable options', async () => {
		const shipping = makeShipping( async () =>
			quote( { options: [], selectedId: null } )
		);

		const result = await callbacks( {
			shipping,
			overrideConfig: config( {
				labels: { shipping_unserviceable: 'Cannot ship here.' },
			} ),
		} )( paymentData() );

		expect( result ).toEqual( {
			error: {
				reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
				intent: 'SHIPPING_ADDRESS',
				message: 'Cannot ship here.',
			},
		} );
	} );

	test.each( [
		[ 'INITIALIZE', true ],
		[ 'SHIPPING_ADDRESS', true ],
		[ 'SHIPPING_OPTION', false ],
	] )(
		'includes newShippingOptionParameters for trigger %s: %s',
		async ( trigger, included ) => {
			const shipping = makeShipping( async () => quote() );

			const result = await callbacks( { shipping } )(
				paymentData( { callbackTrigger: trigger } )
			);

			expect( 'newShippingOptionParameters' in result ).toBe(
				included
			);
		}
	);

	test( 'rethrows when pricing the address fails', async () => {
		const shipping = makeShipping( async () => {
			throw new Error( 'cart down' );
		} );

		await expect(
			callbacks( { shipping } )( paymentData() )
		).rejects.toThrow( 'cart down' );
		expect( console ).toHaveErrored();
	} );
} );
