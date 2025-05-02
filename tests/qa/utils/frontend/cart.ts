/**
 * External dependencies
 */
import { Cart as CartBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUi } from './paypal-ui';

export class Cart extends CartBase {
	payPalUi: PayPalUi;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	makeOrder = async ( data: WooCommerce.ShopOrder ) => {
		await this.visit();

		// Add coupons if needed
		if ( data.coupons ) {
			for ( const coupon of data.coupons ) {
				await this.applyCoupon( coupon.code );
			}
		}

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( data.shipping.settings.title );

		// Make payment with tested method
		await this.payPalUi.makePayment( {
			merchant: data.merchant,
			payment: data.payment,
		} );
	};

	// Assertions
}
