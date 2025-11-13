/**
 * External dependencies
 */
import { ClassicCart as ClassicCartBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUiClassic } from './paypal-ui-classic';

export class ClassicCart extends ClassicCartBase {
	payPalUi: PayPalUiClassic;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	makeOrder = async ( data: WooCommerce.ShopOrder ) => {
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
