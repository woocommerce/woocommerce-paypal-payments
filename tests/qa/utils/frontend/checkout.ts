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
import { PayPalUi } from './paypal-ui';

export class Checkout extends CheckoutBase {
	payPalUi: PayPalUi;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
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

	makeOrder = async ( data: WooCommerce.ShopOrder ) => {
		await this.visit();

		// Add coupons if needed
		await this.applyCouponIfNeeded( data.coupons );

		// Fill billing details
		await this.fillCheckoutForm( data.customer );

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( data.shipping.settings.title );

		// Make payment with tested method
		await this.payPalUi.makePayment( {
			merchant: data.merchant,
			payment: data.payment,
		} );
	};

	completeOrderFromProduct = async ( data: WooCommerce.ShopOrder ) => {
		await this.assertUrl();
		await expect(
			this.page.getByText(
				`You are currently paying with ${ data.payment.gatewayName }.`
			)
		).toBeVisible();

		// Add coupons if needed
		await this.applyCouponIfNeeded( data.coupons );

		// Fill billing details
		await this.fillCheckoutForm( data.customer );

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( data.shipping.settings.title );

		// Make payment with tested method
		await this.placeOrder();
	};

	// Assertions
}
