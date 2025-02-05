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
import { PayPalUI } from './paypal-ui';

export class ClassicCheckout extends ClassicCheckoutBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
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
		await this.ppui.makeClassicPayment( {
			merchant: tested.merchant,
			payment: tested.payment,
		} );
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
