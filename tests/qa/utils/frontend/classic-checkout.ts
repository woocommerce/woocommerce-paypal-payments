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

	completeCheckoutDetails = async ( data: WooCommerce.ShopOrder ) => {
		const { payment, coupons, shipping, customer } = data;
		const isFastlane = payment.gateway.shortcut === 'fastlane';

		// Add coupons if needed
		for ( const coupon of coupons ?? [] ) {
			await this.applyCoupon( coupon.code );
		}

		// Select shipping or initial shipment (for subscriptions) option:
		await this.shippingMethodRadio( shipping.settings.title ).click();

		if ( isFastlane ) {
			await this.fillFastlaneDetails( customer, payment.fastlaneFlow );
		} else {
			// Fill billing details
			await this.fillCheckoutForm( customer );
		}
	};

	fillFastlaneDetails = async (
		customer: WooCommerce.CreateCustomer,
		fastlaneFlow: 'gary' | 'ryan'
	) => {
		await expect( this.payPalUi.fastlaneEmailInput(), 'Assert fastlane email input is visible' ).toBeVisible();
		await expect( this.payPalUi.fastlaneContinueButton(), 'Assert fastlane continue button is visible' ).toBeVisible();
		await this.payPalUi.fastlaneEmailInput().fill( customer.email );
		// on classic checkout fastlane popup is triggered when valid email is filled and input loses focus
		await this.payPalUi.fastlaneEmailInput().press( 'Tab' ); // to trigger make input lose focus

		if ( fastlaneFlow === 'ryan' ) {
			// For "Ryan's flow" the OTP is required
			await this.payPalUi.provideFastlaneOtp();
			// Checkout form and payment card is already prefilled
			await this.assertShippingAddressIsPopulated( customer.shipping );
		} else {
			await this.fillCheckoutForm( customer );
		}
	};

	/**
	 * Completes order payed via PayPal on product page
	 *
	 * @param data
	 */
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
		const billingAddress = this.fastlaneBillingAddressContainer();
		await expect( billingAddress, 'Assert billing address has first name' ).toContainText( billing.first_name );
		await expect( billingAddress, 'Assert billing address has last name' ).toContainText( billing.last_name );
		await expect( billingAddress, 'Assert billing address has address 1' ).toContainText( billing.address_1 );
		await expect( billingAddress, 'Assert billing address has city' ).toContainText( billing.city );
		// await expect( billingAddress ).toContainText( billing.state ); // TODO: fix for the full state name
		await expect( billingAddress, 'Assert billing address has postcode' ).toContainText( billing.postcode );
		await expect( billingAddress, 'Assert billing address has country name' ).toContainText( billing.countryName );
	};
}
