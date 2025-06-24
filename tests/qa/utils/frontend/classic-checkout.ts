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
	fastlaneShippingAddressContainer = () =>
		this.page.locator( '#ppcp-axo-shipping-address-container' );
	fastlaneBillingAddressContainer = () =>
		this.page.locator( '#ppcp-axo-billing-address-container' );

	// Actions

	applyCouponIfNeeded = async ( coupons? ) => {
		if ( coupons ) {
			for ( const coupon of coupons ) {
				await super.applyCoupon( coupon.code );
			}
		}
	};

	makeOrder = async ( data: WooCommerce.ShopOrder ) => {
		const { payment, coupons, shipping, customer, merchant } =
			data;
		const isFastlane = payment.gateway.shortcut === 'fastlane';

		await this.visit();

		// Add coupons if needed
		await this.applyCouponIfNeeded( coupons );

		// Select shipping or initial shipment (for subscriptions) option:
		await this.shippingMethodRadio( shipping.settings.title ).click();

		if ( isFastlane ) {
			await this.payPalUi.provideFastlaneEmail( customer.email );
		}

		if ( isFastlane && payment.fastlaneFlow === 'ryan' ) {
			// For "Ryan's flow" the OTP is required
			await this.payPalUi.provideFastlaneOtp();
			// Checkout form and payment card is already prefilled
			await this.assertShippingAddressIsPopulated( customer.shipping );
		} else {
			// Fill billing details
			await this.fillCheckoutForm( customer );
		}

		// Make payment with tested method
		await this.payPalUi.makePayment( {
			merchant,
			payment,
		} );
	};

	/**
	 * Completes order payed via PayPal on product page
	 *
	 * @param data
	 */
	completeOrderFromProduct = async ( data: WooCommerce.ShopOrder ) => {
		const { payment, coupons, shipping, customer } =
			data;
		await this.assertUrl();
		await expect(
			this.page.getByText(
				`You are currently paying with ${ payment.gatewayName }.`
			)
		).toBeVisible();

		// Add coupons if needed
		await this.applyCouponIfNeeded( coupons );

		// Select shipping or initial shipment (for subscriptions) option:
		await this.shippingMethodRadio( shipping.settings.title ).click();

		// Fill billing details
		await this.fillCheckoutForm( customer );

		// Make payment with tested method
		await this.placeOrder();
	};

	/**
	 * Fills billing details on Classic Checkout page
	 *
	 * @param billing
	 */
	fillFastlaneBillingDetails = async ( billing: WooCommerce.Billing ) => {
		await this.billingFirstNameInput().fill( billing.first_name );
		await this.billingLastNameInput().fill( billing.last_name );
		await this.billingCountryCombobox().click();
		await this.billingCountryOptionByCode( billing.country ).click();
		await this.billingStreetAddressInput().fill( billing.address_1 );
		await this.billingPostcodeInput().fill( billing.postcode );
		await this.billingCityInput().fill( billing.city );
		if ( billing.state ) {
			await this.billingStateCombobox().click();
			await this.billingStateOptionByCode( billing.state ).click();
		}
		await this.billingPhoneInput().fill( billing.phone );
	};

	// Assertions

	assertShippingAddressIsPopulated = async (
		shipping: WooCommerce.Shipping
	) => {
		const shippingAddress = this.fastlaneShippingAddressContainer();
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
		const billingAddress = this.fastlaneBillingAddressContainer();
		await expect( billingAddress ).toContainText( billing.first_name );
		await expect( billingAddress ).toContainText( billing.last_name );
		await expect( billingAddress ).toContainText( billing.address_1 );
		await expect( billingAddress ).toContainText( billing.city );
		// await expect( billingAddress ).toContainText( billing.state ); // TODO: fix for the full state name
		await expect( billingAddress ).toContainText( billing.postcode );
		await expect( billingAddress ).toContainText( billing.countryName );
	};
}
