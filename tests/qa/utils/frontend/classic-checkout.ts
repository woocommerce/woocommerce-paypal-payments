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

	makeOrder = async ( tested ) => {
		await this.visit();

		// Add coupons if needed
		await this.applyCouponIfNeeded( tested.coupons );

		// Select shipping or initial shipment (for subscriptions) option:
		if (
			tested.products.some(
				( product ) => product.type === 'subscription'
			)
		) {
			await this.selectInitialShipment( tested.shipping.settings.title );
		} else {
			await this.selectShippingMethod( tested.shipping.settings.title );
		}

		// Fill billing details
		await this.fillCheckoutForm( tested.customer );

		// Make payment with tested method
		await this.payPalUi.makePayment( {
			merchant: tested.merchant,
			payment: tested.payment,
		} );
	};

	/**
	 * Completes order payed via PayPal on product page
	 *
	 * @param tested
	 */
	completeOrderFromProduct = async ( tested ) => {
		await this.assertUrl();
		await expect(
			this.page.getByText(
				`You are currently paying with ${ tested.payment.gatewayName }.`
			)
		).toBeVisible();

		// Add coupons if needed
		await this.applyCouponIfNeeded( tested.coupons );

		// Select shipping or initial shipment (for subscriptions) option:
		if (
			tested.products.some(
				( product ) => product.type === 'subscription'
			)
		) {
			await this.selectInitialShipment( tested.shipping.settings.title );
		} else {
			await this.selectShippingMethod( tested.shipping.settings.title );
		}

		// Fill billing details
		await this.fillCheckoutForm( tested.customer );

		// Make payment with tested method
		await this.placeOrder();
	};

	// Assertions
}
