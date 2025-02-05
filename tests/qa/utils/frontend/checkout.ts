/**
 * External dependencies
 */
import {
	Checkout as CheckoutBase,
	expect,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class Checkout extends CheckoutBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators
	proceedToPayPalButton = () =>
		this.page.getByRole( 'button', { name: 'Proceed to PayPal' } );

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

		// Fill billing details
		await this.fillCheckoutForm( tested.customer );

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( tested.shipping.settings.title );

		// Make payment with tested method
		await this.ppui.makePayment( {
			merchant: tested.merchant,
			payment: tested.payment,
		} );
		await this.placeOrder();
	};

	completeOrderFromProduct = async ( tested ) => {
		await this.assertUrl();
		await expect(
			this.page.getByText(
				`You are currently paying with ${ tested.payment.gatewayName }.`
			)
		).toBeVisible();

		// Add coupons if needed
		await this.applyCouponIfNeeded( tested.coupons );

		// Fill billing details
		await this.fillCheckoutForm( tested.customer );

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( tested.shipping.settings.title );

		// Make payment with tested method
		await this.placeOrder();
	};

	// Assertions
}
