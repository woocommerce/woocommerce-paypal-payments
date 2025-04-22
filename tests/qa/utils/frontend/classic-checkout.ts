/**
 * External dependencies
 */
import {
	ClassicCheckout as ClassicCheckoutBase,
	expect,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUiClassic } from './paypal-ui-classic';

export class ClassicCheckout extends ClassicCheckoutBase {
	payPalUi: PayPalUiClassic;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	applyCouponIfNeeded = async ( coupons? ) => {
		if ( coupons ) {
			for ( const coupon of coupons ) {
				await super.applyCoupon( coupon.code );
			}
		}
	};

	makeOrder = async ( data: WooCommerce.ShopOrder ) => {
		await this.visit();

		// Add coupons if needed
		await this.applyCouponIfNeeded( data.coupons );

		// Select shipping or initial shipment (for subscriptions) option:
		if (
			data.products.some(
				( product ) => product.type === 'subscription'
			)
		) {
			await this.selectInitialShipment( data.shipping.settings.title );
		} else {
			await this.selectShippingMethod( data.shipping.settings.title );
		}

		// Fill billing details
		await this.fillCheckoutForm( data.customer );

		// Make payment with tested method
		await this.payPalUi.makePayment( {
			merchant: data.merchant,
			payment: data.payment,
		} );
	};

	/**
	 * Completes order payed via PayPal on product page
	 *
	 * @param data
	 */
	completeOrderFromProduct = async ( data: WooCommerce.ShopOrder ) => {
		await this.assertUrl();
		await expect(
			this.page.getByText(
				`You are currently paying with ${ data.payment.gatewayName }.`
			)
		).toBeVisible();

		// Add coupons if needed
		await this.applyCouponIfNeeded( data.coupons );

		// Select shipping or initial shipment (for subscriptions) option:
		if (
			data.products.some(
				( product ) => product.type === 'subscription'
			)
		) {
			await this.selectInitialShipment( data.shipping.settings.title );
		} else {
			await this.selectShippingMethod( data.shipping.settings.title );
		}

		// Fill billing details
		await this.fillCheckoutForm( data.customer );

		// Make payment with tested method
		await this.placeOrder();
	};

	// Assertions
}
