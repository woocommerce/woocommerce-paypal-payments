/**
 * Apple Pay's in-sheet shipping callbacks.
 *
 * Translates between Apple's ApplePaySession protocol and the shared shipping
 * quote; everything about pricing lives in walletShipping.js.
 *
 * @package
 */

import { resolveOptionId } from './shippingQuote';
import { walletAddressToWc } from './walletContacts';

/**
 * Builds the line items the sheet itemises the total with.
 *
 * @param {Object} quote  - The shipping quote.
 * @param {Object} labels - The shopper-facing labels.
 * @return {Object[]} Apple line items.
 */
function lineItems( quote, labels ) {
	const items = [ { label: labels.subtotal, amount: quote.subtotal } ];

	if ( parseFloat( quote.shippingFee ) > 0 ) {
		items.push( { label: labels.shipping, amount: quote.shippingFee } );
	}

	if ( parseFloat( quote.tax ) > 0 ) {
		items.push( { label: labels.tax, amount: quote.tax } );
	}

	if ( parseFloat( quote.discount ) > 0 ) {
		// Negative, because Apple sums the line items it is given.
		items.push( {
			label: labels.discount,
			amount: `-${ quote.discount }`,
		} );
	}

	return items.map( ( item ) => ( { ...item, type: 'final' } ) );
}

/**
 * Maps a quote's options to Apple's shape.
 *
 * The selected one goes first: Apple presents the head of the list as chosen.
 *
 * @param {Object} quote - The shipping quote.
 * @return {Object[]} Apple shipping methods.
 */
function shippingMethods( quote ) {
	const methods = quote.options.map( ( option ) => ( {
		label: option.label,
		detail: '',
		amount: option.cost,
		identifier: option.id,
	} ) );

	const selectedAt = methods.findIndex(
		( method ) => method.identifier === quote.selectedId
	);

	if ( selectedAt > 0 ) {
		methods.unshift( methods.splice( selectedAt, 1 )[ 0 ] );
	}

	return methods;
}

/**
 * Builds the sheet update that describes a priced quote.
 *
 * @param {Object} quote            - The shipping quote.
 * @param {Object} args             - Presentation inputs.
 * @param {string} args.displayName - The shop name, labelling the total.
 * @param {Object} args.labels      - The shopper-facing labels.
 * @return {Object} The Apple sheet update.
 */
export function applePayShippingUpdate( quote, { displayName, labels } ) {
	return {
		newTotal: {
			label: displayName,
			type: 'final',
			amount: quote.total,
		},
		newLineItems: lineItems( quote, labels ),
		newShippingMethods: shippingMethods( quote ),
	};
}

/**
 * Builds the update that tells the sheet an address cannot be shipped to.
 *
 * Carries whatever last priced, because Apple rejects a completion with no total
 * at all. The error goes against the address field, so the shopper can correct it.
 *
 * @param {?Object} lastQuote        - The last quote that priced, if any.
 * @param {Object}  args             - Presentation inputs.
 * @param {string}  args.displayName - The shop name.
 * @param {Object}  args.labels      - The shopper-facing labels.
 * @param {string}  args.message     - The error to show.
 * @return {Object} The Apple sheet update.
 */
export function applePayUnserviceableUpdate(
	lastQuote,
	{ displayName, labels, message }
) {
	const errors = [];

	// Guarded: ApplePayError is only defined inside a live session.
	if ( typeof window.ApplePayError === 'function' ) {
		errors.push(
			new window.ApplePayError(
				'shippingContactInvalid',
				'postalAddress',
				message
			)
		);
	}

	const base = lastQuote
		? applePayShippingUpdate( lastQuote, { displayName, labels } )
		: {
				newTotal: {
					label: displayName,
					type: 'pending',
					amount: '0',
				},
				newLineItems: [],
		  };

	return {
		...base,
		newShippingMethods: [],
		errors,
	};
}

/**
 * Wires an ApplePaySession's shipping callbacks to the shared controller.
 *
 * @param {Object} appleSession     - The live ApplePaySession.
 * @param {Object} args             - The wiring inputs.
 * @param {Object} args.config      - The wc_ppcp_sdk_v6 config object.
 * @param {string} args.displayName - The shop name.
 * @param {Object} args.shipping    - The shared shipping controller.
 */
export function attachShippingHandlers(
	appleSession,
	{ config, displayName, shipping }
) {
	const labels = {
		subtotal: config.labels?.subtotal ?? 'Subtotal',
		shipping: config.labels?.shipping ?? 'Shipping',
		tax: config.labels?.tax ?? 'Tax',
		discount: config.labels?.discount ?? 'Discount',
	};
	const message = config.labels?.shipping_unserviceable ?? '';

	// Remembered for the method callback, which prices against whatever address
	// the contact callback last set.
	let address = null;

	/**
	 * Prices a selection and answers the sheet, or reports it cannot be shipped.
	 *
	 * @param {Function} complete  - The session's completion method.
	 * @param {Object}   selection - What to price.
	 */
	async function respond( complete, selection ) {
		try {
			const quote = await shipping.quote( selection );

			if ( ! quote.options.length ) {
				complete(
					applePayUnserviceableUpdate( shipping.current(), {
						displayName,
						labels,
						message,
					} )
				);
				return;
			}

			complete( applePayShippingUpdate( quote, { displayName, labels } ) );
		} catch ( error ) {
			// A sheet that cannot price what it is about to charge must not go on,
			// and nothing can be shown to the shopper behind it.
			// eslint-disable-next-line no-console
			console.error(
				'[PPCP SDK v6] Apple Pay shipping update failed: ' +
					error.message
			);
			appleSession.abort();
		}
	}

	appleSession.onshippingcontactselected = ( event ) => {
		address = walletAddressToWc( event.shippingContact );

		respond(
			( update ) => appleSession.completeShippingContactSelection( update ),
			{ address }
		);
	};

	appleSession.onshippingmethodselected = ( event ) => {
		const rateId = resolveOptionId(
			shipping.current(),
			event.shippingMethod?.identifier
		);

		respond(
			( update ) => appleSession.completeShippingMethodSelection( update ),
			{ address, rateId }
		);
	};
}
