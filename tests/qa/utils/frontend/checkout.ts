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

	// Actions

	completeCheckoutDetails = async ( data: WooCommerce.ShopOrder ) => {
		const { payment, coupons, shipping, customer } = data;
		const isFastlane = payment.gateway.shortcut === 'fastlane';

		// Add coupons if needed
		for ( const coupon of coupons ?? [] ) {
			await this.applyCoupon( coupon.code );
		}

		if ( isFastlane ) {
			await this.fillFastlaneDetails( customer, payment.fastlaneFlow );
		} else {
			// Fill billing details
			await this.fillCheckoutForm( customer );
		}

		// Select shipping or initial + monthly shipment (for subscriptions) option:
		const shippingRadios = await this.shippingMethodRadio(
			shipping.settings.title
		).all();
		for ( const radio of shippingRadios ) {
			await radio.click();
		}
	};

	fillFastlaneDetails = async (
		customer: WooCommerce.CreateCustomer,
		fastlaneFlow: 'gary' | 'ryan'
	) => {
		await expect( this.payPalUi.fastlaneEmailInput(), 'Assert fastlane email input is visible' ).toBeVisible();
		await this.payPalUi.fastlaneEmailInput().fill( customer.email );

		await expect( this.payPalUi.fastlaneContinueButton(), 'Assert fastlane continue button is visible' ).toBeVisible();
		await expect( this.payPalUi.fastlaneContinueButton(), 'Assert fastlane continue button is enabled' ).toBeEnabled();
		await this.payPalUi.fastlaneContinueButton().click();

		if ( fastlaneFlow === 'ryan' ) {
			// For "Ryan's flow" the OTP is required
			await this.payPalUi.provideFastlaneOtp();
			// Checkout form and payment card is already prefilled
			await this.assertBillingAddressIsPopulated( customer.billing );
		} else {
			await this.fillCheckoutForm( customer );
		}
	};

	completeOrderFromProduct = async ( data: WooCommerce.ShopOrder ) => {
		const { payment, coupons, shipping, customer } = data;
		await this.assertUrl();
		await expect(
			this.page.getByText(
				`You are currently paying with ${ payment.gatewayName }.`
			),
			`Assert "You are currently paying with ${ payment.gatewayName }." message is visible`
		).toBeVisible();

		// Add coupons if needed
		for ( const coupon of coupons ?? [] ) {
			await this.applyCoupon( coupon.code );
		}

		// Fill billing details
		await this.fillCheckoutForm( customer );

		// Select shipping or initial shipment (for subscriptions) option:
		await this.selectShippingMethod( shipping.settings.title );

		// Make payment with tested method
		await this.placeOrder();
	};

	// Assertions

	assertShippingAddressIsPopulated = async (
		shipping: WooCommerce.Shipping
	) => {
		const shippingAddress = this.shippingAddressContainer();
		await expect( shippingAddress, 'Assert shipping address has first name' ).toContainText( shipping.first_name );
		await expect( shippingAddress, 'Assert shipping address has last name' ).toContainText( shipping.last_name );
		await expect( shippingAddress, 'Assert shipping address has address 1' ).toContainText( shipping.address_1 );
		await expect( shippingAddress, 'Assert shipping address has city' ).toContainText( shipping.city );
		// await expect( shippingAddress ).toContainText( shipping.state ); // TODO: fix for the full state name
		await expect( shippingAddress, 'Assert shipping address has postcode' ).toContainText( shipping.postcode );
		await expect( shippingAddress, 'Assert shipping address has country name' ).toContainText( shipping.countryName );
	};

	assertBillingAddressIsPopulated = async (
		billing: WooCommerce.Shipping
	) => {
		const billingAddress = this.billingAddressContainer();
		await expect( billingAddress, 'Assert billing address has first name' ).toContainText( billing.first_name );
		await expect( billingAddress, 'Assert billing address has last name' ).toContainText( billing.last_name );
		await expect( billingAddress, 'Assert billing address has address 1' ).toContainText( billing.address_1 );
		await expect( billingAddress, 'Assert billing address has city' ).toContainText( billing.city );
		// await expect( billingAddress ).toContainText( billing.state ); // TODO: fix for the full state name
		await expect( billingAddress, 'Assert billing address has postcode' ).toContainText( billing.postcode );
		await expect( billingAddress, 'Assert billing address has country name' ).toContainText( billing.countryName );
	};
}
