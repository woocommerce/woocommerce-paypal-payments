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
		const { payment, coupons, shipping, customer, merchant } = data;
		const isFastlane = payment.gateway.shortcut === 'fastlane';
		await this.visit();

		// Add coupons if needed
		await this.applyCouponIfNeeded( coupons );

		if ( isFastlane ) {
			await this.payPalUi.provideFastlaneEmail( customer.email );
		}

		if ( isFastlane && payment.fastlaneFlow === 'ryan' ) {
			// For "Ryan's flow" the OTP is required
			await this.payPalUi.provideFastlaneOtp();
			// Checkout form and payment card is already prefilled
			await this.assertBillingAddressIsPopulated( customer.billing );
		} else {
			// Fill billing details
			await this.fillCheckoutForm( customer );
		}

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( shipping.settings.title );

		// Make payment with tested method
		await this.payPalUi.makePayment( {
			merchant,
			payment,
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

	assertShippingAddressIsPopulated = async (
		shipping: WooCommerce.Shipping
	) => {
		const shippingAddress = this.shippingAddressContainer();
		await expect( shippingAddress ).toContainText( shipping.first_name );
		await expect( shippingAddress ).toContainText( shipping.last_name );
		await expect( shippingAddress ).toContainText( shipping.address_1 );
		await expect( shippingAddress ).toContainText( shipping.city );
		// await expect( shippingAddress ).toContainText( shipping.state ); // TODO: fix for the full state name
		await expect( shippingAddress ).toContainText( shipping.postcode );
		await expect( shippingAddress ).toContainText( shipping.countryName );
	};

	assertBillingAddressIsPopulated = async (
		billing: WooCommerce.Shipping
	) => {
		const billingAddress = this.billingAddressContainer();
		await expect( billingAddress ).toContainText( billing.first_name );
		await expect( billingAddress ).toContainText( billing.last_name );
		await expect( billingAddress ).toContainText( billing.address_1 );
		await expect( billingAddress ).toContainText( billing.city );
		// await expect( billingAddress ).toContainText( billing.state ); // TODO: fix for the full state name
		await expect( billingAddress ).toContainText( billing.postcode );
		await expect( billingAddress ).toContainText( billing.countryName );
	};
}
