import '@ppcp-test/helpers/silenceConsole';

import {
	applePayFailure,
	applePayShippingUpdate,
	applePayUnserviceableUpdate,
	attachShippingHandlers,
} from './applePayShipping';

/**
 * Drains pending microtasks.
 *
 * @return {Promise<void>}
 */
const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

const labels = {
	subtotal: 'Subtotal',
	shipping: 'Shipping',
	tax: 'Tax',
	discount: 'Discount',
};

const quote = ( overrides = {} ) => ( {
	total: '110.50',
	shippingFee: '5.00',
	subtotal: '100.00',
	tax: '5.50',
	discount: '0.00',
	selectedId: 'flat_rate:1',
	options: [
		{ id: 'flat_rate:1', label: 'Flat rate', cost: '5.00' },
		{ id: 'flat_rate:2', label: 'Express', cost: '15.00' },
	],
	...overrides,
} );

describe( 'applePayShippingUpdate()', () => {
	test( 'builds a final total from the quote and the display name', () => {
		const update = applePayShippingUpdate( quote(), {
			displayName: 'WooShop',
			labels,
		} );

		expect( update.newTotal ).toEqual( {
			label: 'WooShop',
			type: 'final',
			amount: '110.50',
		} );
	} );

	test( 'always includes the subtotal line item', () => {
		const update = applePayShippingUpdate(
			quote( { shippingFee: '0.00', tax: '0.00', discount: '0.00' } ),
			{ displayName: 'WooShop', labels }
		);

		expect( update.newLineItems ).toEqual( [
			{ label: 'Subtotal', amount: '100.00', type: 'final' },
		] );
	} );

	test( 'adds shipping, tax and discount line items only when each is greater than zero, negating the discount', () => {
		const update = applePayShippingUpdate(
			quote( {
				shippingFee: '5.00',
				tax: '5.50',
				discount: '2.00',
			} ),
			{ displayName: 'WooShop', labels }
		);

		expect( update.newLineItems ).toEqual( [
			{ label: 'Subtotal', amount: '100.00', type: 'final' },
			{ label: 'Shipping', amount: '5.00', type: 'final' },
			{ label: 'Tax', amount: '5.50', type: 'final' },
			{ label: 'Discount', amount: '-2.00', type: 'final' },
		] );
	} );

	test( 'moves the selected shipping method to the head of the list', () => {
		const update = applePayShippingUpdate(
			quote( { selectedId: 'flat_rate:2' } ),
			{ displayName: 'WooShop', labels }
		);

		expect( update.newShippingMethods ).toEqual( [
			{
				label: 'Express',
				detail: '',
				amount: '15.00',
				identifier: 'flat_rate:2',
			},
			{
				label: 'Flat rate',
				detail: '',
				amount: '5.00',
				identifier: 'flat_rate:1',
			},
		] );
	} );

	test( 'leaves the order unchanged when the first option is already selected', () => {
		const update = applePayShippingUpdate( quote(), {
			displayName: 'WooShop',
			labels,
		} );

		expect( update.newShippingMethods.map( ( m ) => m.identifier ) ).toEqual( [
			'flat_rate:1',
			'flat_rate:2',
		] );
	} );
} );

describe( 'applePayUnserviceableUpdate()', () => {
	afterEach( () => {
		delete window.ApplePayError;
	} );

	test( 'carries the last good total and line items, with no shipping methods', () => {
		const lastQuote = quote();

		const update = applePayUnserviceableUpdate( lastQuote, {
			displayName: 'WooShop',
			labels,
			message: 'Cannot ship here.',
		} );

		expect( update.newTotal ).toEqual( {
			label: 'WooShop',
			type: 'final',
			amount: '110.50',
		} );
		expect( update.newShippingMethods ).toEqual( [] );
	} );

	test( 'reports a zero, pending total when no quote has ever priced', () => {
		const update = applePayUnserviceableUpdate( null, {
			displayName: 'WooShop',
			labels,
			message: 'Cannot ship here.',
		} );

		expect( update.newTotal ).toEqual( {
			label: 'WooShop',
			type: 'pending',
			amount: '0',
		} );
		expect( update.newLineItems ).toEqual( [] );
		expect( update.newShippingMethods ).toEqual( [] );
	} );

	test( 'adds an ApplePayError describing the address problem when the class is available', () => {
		window.ApplePayError = function ( code, field, message ) {
			this.code = code;
			this.field = field;
			this.message = message;
		};

		const update = applePayUnserviceableUpdate( quote(), {
			displayName: 'WooShop',
			labels,
			message: 'Cannot ship here.',
		} );

		expect( update.errors ).toHaveLength( 1 );
		expect( update.errors[ 0 ] ).toMatchObject( {
			code: 'shippingContactInvalid',
			field: 'postalAddress',
			message: 'Cannot ship here.',
		} );
	} );

	test( 'reports no errors when ApplePayError is unavailable, as outside a live session', () => {
		const update = applePayUnserviceableUpdate( quote(), {
			displayName: 'WooShop',
			labels,
			message: 'Cannot ship here.',
		} );

		expect( update.errors ).toEqual( [] );
	} );
} );

describe( 'applePayFailure()', () => {
	afterEach( () => {
		delete window.ApplePayError;
	} );

	test( 'reports only the status when the error is not marked user-facing', () => {
		const result = applePayFailure(
			new Error( 'Internal failure' ),
			'STATUS_FAILURE'
		);

		expect( result ).toEqual( { status: 'STATUS_FAILURE' } );
	} );

	test( 'adds an ApplePayError carrying the message when the error is user-facing', () => {
		window.ApplePayError = function ( code, field, message ) {
			this.code = code;
			this.field = field;
			this.message = message;
		};
		const error = new Error( 'This card is not supported.' );
		error.isUserFacing = true;

		const result = applePayFailure( error, 'STATUS_FAILURE' );

		expect( result.status ).toBe( 'STATUS_FAILURE' );
		expect( result.errors ).toHaveLength( 1 );
		expect( result.errors[ 0 ] ).toMatchObject( {
			code: 'unknown',
			message: 'This card is not supported.',
		} );
	} );

	test( 'reports only the status for a user-facing error when ApplePayError is unavailable, as outside a live session', () => {
		const error = new Error( 'This card is not supported.' );
		error.isUserFacing = true;

		expect( () =>
			applePayFailure( error, 'STATUS_FAILURE' )
		).not.toThrow();
		expect( applePayFailure( error, 'STATUS_FAILURE' ) ).toEqual( {
			status: 'STATUS_FAILURE',
		} );
	} );
} );

function makeAppleSession() {
	return {
		completeShippingContactSelection: jest.fn(),
		completeShippingMethodSelection: jest.fn(),
		abort: jest.fn(),
	};
}

function makeShipping( quoteImpl, current = null ) {
	return { quote: jest.fn( quoteImpl ), current: jest.fn( () => current ) };
}

describe( 'attachShippingHandlers()', () => {
	const config = { labels: { shipping_unserviceable: 'Cannot ship here.' } };

	test( 'onshippingcontactselected maps the contact to a WC address, prices it, and completes with the shipping update', async () => {
		const appleSession = makeAppleSession();
		const shipping = makeShipping( async () => quote() );

		attachShippingHandlers( appleSession, {
			config,
			displayName: 'WooShop',
			shipping,
		} );

		appleSession.onshippingcontactselected( {
			shippingContact: {
				countryCode: 'US',
				administrativeArea: 'CA',
				postalCode: '94105',
				locality: 'San Francisco',
			},
		} );
		await flushPromises();

		expect( shipping.quote ).toHaveBeenCalledWith( {
			address: {
				country: 'US',
				state: 'CA',
				postcode: '94105',
				city: 'San Francisco',
			},
		} );
		expect(
			appleSession.completeShippingContactSelection
		).toHaveBeenCalledWith(
			applePayShippingUpdate( quote(), {
				displayName: 'WooShop',
				labels,
			} )
		);
	} );

	test( 'completes with the unserviceable update, carrying the last good quote, when the address has no options', async () => {
		window.ApplePayError = function () {};
		const lastGood = quote();
		const appleSession = makeAppleSession();
		const shipping = makeShipping(
			async () => quote( { options: [], selectedId: null } ),
			lastGood
		);

		attachShippingHandlers( appleSession, {
			config,
			displayName: 'WooShop',
			shipping,
		} );

		appleSession.onshippingcontactselected( { shippingContact: {} } );
		await flushPromises();

		expect(
			appleSession.completeShippingContactSelection
		).toHaveBeenCalledWith(
			expect.objectContaining( {
				newShippingMethods: [],
				errors: expect.any( Array ),
			} )
		);

		delete window.ApplePayError;
	} );

	test( 'onshippingmethodselected resolves the picked rate against the current quote and completes with the shipping update', async () => {
		const current = quote();
		const appleSession = makeAppleSession();
		const shipping = makeShipping(
			async () => quote( { selectedId: 'flat_rate:2' } ),
			current
		);

		attachShippingHandlers( appleSession, {
			config,
			displayName: 'WooShop',
			shipping,
		} );

		appleSession.onshippingmethodselected( {
			shippingMethod: { identifier: 'flat_rate:2' },
		} );
		await flushPromises();

		expect( shipping.quote ).toHaveBeenCalledWith( {
			address: null,
			rateId: 'flat_rate:2',
		} );
		expect(
			appleSession.completeShippingMethodSelection
		).toHaveBeenCalledWith(
			applePayShippingUpdate( quote( { selectedId: 'flat_rate:2' } ), {
				displayName: 'WooShop',
				labels,
			} )
		);
	} );

	test( 'aborts the session and reports the error when pricing throws', async () => {
		const appleSession = makeAppleSession();
		const shipping = makeShipping( async () => {
			throw new Error( 'Store API down' );
		} );

		attachShippingHandlers( appleSession, {
			config,
			displayName: 'WooShop',
			shipping,
		} );

		appleSession.onshippingcontactselected( { shippingContact: {} } );
		await flushPromises();

		expect( appleSession.abort ).toHaveBeenCalledTimes( 1 );
		expect(
			appleSession.completeShippingContactSelection
		).not.toHaveBeenCalled();
	} );
} );
