/**
 * External dependencies
 */
import { Cart as CartBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class Cart extends CartBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators

	// Actions

	makeOrder = async ( tested ) => {
		await this.visit();

		// Add coupons if needed
		if ( tested.coupons ) {
			for ( const coupon of tested.coupons ) {
				await this.applyCoupon( coupon.code );
			}
		}

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( tested.shipping.settings.title );

		// Make payment with tested method
		await this.ppui.makePayment( {
			merchant: tested.merchant,
			payment: tested.payment,
		} );
	};

	// Assertions
}
