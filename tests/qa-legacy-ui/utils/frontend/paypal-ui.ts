/**
 * External dependencies
 */
import { Page } from '@playwright/test';
import { expect, getLast4CardDigits } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalAccount, PcpMerchant, PcpPayment } from '../../resources';
import { PayPalPopup } from './paypal-popup';
import { PayPalAPI } from '../paypal-api';

/**
 * Class for common dashboard locators, actions, assertions
 */

export class PayPalUI {
	page: Page;
	ppapi: PayPalAPI;

	constructor( { page, ppapi } ) {
		this.page = page;
		this.ppapi = ppapi;
	}

	// Locators
	placeOrderButton = () =>
		this.page.getByRole( 'button', { name: 'Place order' } );
	payForOrderButton = () =>
		this.page.getByRole( 'button', { name: 'Pay for order' } );
	cartMenu = () => this.page.locator( '#site-header-cart' );

	payPalIframe = () =>
		this.page.frameLocator(
			'[id^="ppc-button-ppcp-gateway"] .component-frame.visible'
		);
	fundingSourceButton = ( name ) =>
		this.payPalIframe().locator( `[data-funding-source="${ name }"]` );

	payPalButton = () => this.fundingSourceButton( 'paypal' );
	payLaterButton = () => this.fundingSourceButton( 'paylater' );
	sepaButton = () => this.fundingSourceButton( 'sepa' );
	giropayButton = () => this.fundingSourceButton( 'giropay' );
	sofortButton = () => this.fundingSourceButton( 'sofort' );
	debitOrCreditCardButton = () => this.fundingSourceButton( 'card' );
	venmoButton = () => this.fundingSourceButton( 'venmo' );

	fundingSourceButtonLabelText = ( name ) =>
		this.fundingSourceButton( name ).locator( '.paypal-button-text' ); // additional text on paypal buttons
	fundingSourceButtonPayLabel = ( name ) =>
		this.fundingSourceButton( name ).locator( '.pay-label' ); // for example Pay Now when vaulting
	fundingSourceButtonLabel = ( name ) =>
		this.fundingSourceButton( name ).locator( '.label' ); // customer's email if Vaulting
	iframePayPalButton = () => this.payPalIframe().locator( '.paypal-button' );
	iframePayPalButtonText = () =>
		this.payPalIframe().locator( '.paypal-button-text.true' );

	payPalGateway = () => this.page.locator( 'li.payment_method_ppcp-gateway' );
	acdcGateway = () =>
		this.page.locator( 'li.payment_method_ppcp-credit-card-gateway' );
	debitCreditCardsGateway = () =>
		this.page.locator( 'li.payment_method_ppcp-credit-card-gateway' );
	standardCardButtonGateway = () =>
		this.page.locator( 'li.payment_method_ppcp-card-button-gateway' );
	oxxoGateway = () =>
		this.page.locator( 'li.payment_method_ppcp-oxxo-gateway' );
	payUponInvoiceGateway = () =>
		this.page.locator( 'li.payment_method_ppcp-pay-upon-invoice-gateway' );

	taglineText = () => this.payPalIframe().locator( '.paypal-button-tagline' );
	payPalGatewayText = () =>
		this.page.locator( '.payment_method_ppcp-gateway>label' );
	payPalGatewayDescription = () =>
		this.page.locator( '.payment_method_ppcp-gateway>p' );
	acdcGatewayText = () =>
		this.page.locator( '.payment_method_ppcp-credit-card-gateway>label' );

	blockSmartButtonIframe = () =>
		this.page.locator( '[id^="express-payment-method-ppcp-gateway-"]' );
	blockPayPalButton = () =>
		this.page
			.frameLocator(
				'#express-payment-method-ppcp-gateway-paypal .component-frame'
			)
			.locator( `[data-funding-source="paypal"]` );
	blockPayLaterButton = () =>
		this.page
			.frameLocator(
				'#express-payment-method-ppcp-gateway-paylater .component-frame'
			)
			.locator( `[data-funding-source="paylater"]` );
	blockVenmoButton = () =>
		this.page
			.frameLocator(
				'#express-payment-method-ppcp-gateway-venmo .component-frame'
			)
			.locator( `[data-funding-source="venmo"]` );

	cardNumberInput = () =>
		this.page
			.frameLocator( 'iframe[title="paypal_card_number_field"]' )
			.locator( 'input.card-field-number ' );
	cardExpirationInput = () =>
		this.page
			.frameLocator( 'iframe[title="paypal_card_expiry_field"]' )
			.locator( 'input.card-field-expiry' );
	cardCVVInput = () =>
		this.page
			.frameLocator( 'iframe[title="paypal_card_cvv_field"]' )
			.locator( 'input.card-field-cvv' );
	addPaymentMethodButton = () => this.page.locator( '#place_order' );

	payPalCardGatewayIframe = () =>
		this.payPalIframe().frameLocator( 'iframe.zoid-visible' );
	payPalCardGatewayCardNumberInput = () =>
		this.payPalCardGatewayIframe().locator( '#credit-card-number' );
	payPalCardGatewayCardExpirationInput = () =>
		this.payPalCardGatewayIframe().locator( '#expiry-date' );
	payPalCardGatewayCSCCode = () =>
		this.payPalCardGatewayIframe().locator( '#credit-card-security' );
	payPalCardGatewayBuyNowButton = () =>
		this.payPalCardGatewayIframe().locator( '#submit-button' );
	payPalCardGatewayPoweredByText = () =>
		this.page
			.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' )
			.locator( '.paypal-powered-by' )
			.getByText( 'Powered by' );
	payPalCardGatewayPoweredByLogo = () =>
		this.page
			.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' )
			.locator( '.paypal-powered-by .paypal-logo' );
	payPalCardGatewayFirsNameField = () =>
		this.payPalCardGatewayIframe().locator(
			'[id="billingAddress.givenName"]'
		);
	payPalCardGatewayLastNameField = () =>
		this.payPalCardGatewayIframe().locator(
			'[id="billingAddress.familyName"]'
		);
	payPalCardGatewayStreetField = () =>
		this.payPalCardGatewayIframe().locator( '[id="billingAddress.line1"]' );
	payPalCardGatewayApartmentBuildingField = () =>
		this.payPalCardGatewayIframe().locator( '[id="billingAddress.line2"]' );
	payPalCardGatewayCityField = () =>
		this.payPalCardGatewayIframe().locator( '[id="billingAddress.city"]' );
	payPalCardGatewayStateField = () =>
		this.payPalCardGatewayIframe().locator( '[id="billingAddress.state"]' );
	payPalCardGatewayZipCodeField = () =>
		this.payPalCardGatewayIframe().locator(
			'[id="billingAddress.postcode"]'
		);
	payPalCardGatewayPhoneField = () =>
		this.payPalCardGatewayIframe().locator( '[id="phone"]' );
	payPalCardGatewayEmailField = () =>
		this.payPalCardGatewayIframe().locator( '[id="email"]' );

	miniCartButtonIframe = () =>
		this.page.frameLocator( '#ppc-button-minicart .component-frame' );
	miniCartFundingSourceButton = ( name ) =>
		this.miniCartButtonIframe().locator(
			`[data-funding-source="${ name }"]`
		);
	miniCartIframePayPalButton = () =>
		this.miniCartButtonIframe().locator( '.paypal-button' );
	miniCartPayPalButton = () => this.miniCartFundingSourceButton( 'paypal' );

	debitOrCreditCardIframe = () =>
		this.payPalIframe().frameLocator( 'iframe.zoid-visible' );
	debitOrCreditCardNumberInput = () =>
		this.debitOrCreditCardIframe().locator( '#credit-card-number' );
	debitOrCreditCardExpirationInput = () =>
		this.debitOrCreditCardIframe().locator( '#expiry-date' );
	debitOrCreditCardCSCInput = () =>
		this.debitOrCreditCardIframe().locator( '#credit-card-security' );
	debitOrCreditCardBuyNowButton = () =>
		this.debitOrCreditCardIframe().locator( '#submit-button' );
	debitOrCreditCardBillingAddress = () =>
		this.debitOrCreditCardIframe().getByText( 'Billing address' );
	debitOrCreditCardFirstNameInput = () =>
		this.debitOrCreditCardIframe().locator(
			'[id="billingAddress.givenName"]'
		);
	debitOrCreditCardLastNameInput = () =>
		this.debitOrCreditCardIframe().locator(
			'[id="billingAddress.familyName"]'
		);
	debitOrCreditCardStreetInput = () =>
		this.debitOrCreditCardIframe().locator( '[id="billingAddress.line1"]' );
	debitOrCreditCardApartmentInput = () =>
		this.debitOrCreditCardIframe().locator( '[id="billingAddress.line2"]' );
	debitOrCreditCardCityInput = () =>
		this.debitOrCreditCardIframe().locator( '[id="billingAddress.city"]' );
	debitOrCreditCardStateInput = () =>
		this.debitOrCreditCardIframe().locator( '[id="billingAddress.state"]' );
	debitOrCreditCardZipCodeInput = () =>
		this.debitOrCreditCardIframe().locator(
			'[id="billingAddress.postcode"]'
		);
	debitOrCreditCardPhoneInput = () =>
		this.debitOrCreditCardIframe().locator( '#phone' );
	debitOrCreditCardEmailInput = () =>
		this.debitOrCreditCardIframe().locator( '#email' );
	debitOrCreditCardPayNowButton = () =>
		this.debitOrCreditCardIframe().locator( '#submit-button' );
	debitOrCreditCardPayPalRadio = () =>
		this.debitOrCreditCardIframe().locator(
			'label[for="paypal-currency-conversion-option"]'
		);
	debitOrCreditCardVendorRadio = () =>
		this.debitOrCreditCardIframe().locator(
			'label[for="vendor-currency-conversion-option"]'
		);

	debitOrCreditCardTagline = () =>
		this.page
			.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' )
			.locator( '.paypal-powered-by' );
	debitOrCreditCardPoweredByText = () =>
		this.debitOrCreditCardTagline().getByText( 'Powered by' );
	debitOrCreditCardPoweredByLogo = () =>
		this.debitOrCreditCardTagline().locator( '.paypal-logo' );

	standardCardButtonIframe = () =>
		this.page.frameLocator(
			'#ppc-button-ppcp-card-button-gateway .component-frame'
		);
	standardCardButton = () =>
		this.standardCardButtonIframe().locator(
			`[data-funding-source="card"]`
		);
	standardCardButtonDetailsIframe = () =>
		this.standardCardButtonIframe().frameLocator( 'iframe.zoid-visible' );
	standardCardButtonNumberInput = () =>
		this.standardCardButtonDetailsIframe().locator( '#credit-card-number' );
	standardCardButtonExpirationInput = () =>
		this.standardCardButtonDetailsIframe().locator( '#expiry-date' );
	standardCardButtonCSCInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'#credit-card-security'
		);
	standardCardButtonBuyNowButton = () =>
		this.standardCardButtonDetailsIframe().locator( '#submit-button' );
	standardCardButtonFirstNameInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'[id="billingAddress.givenName"]'
		);
	standardCardButtonLastNameInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'[id="billingAddress.familyName"]'
		);
	standardCardButtonStreetInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'[id="billingAddress.line1"]'
		);
	standardCardButtonApartmentInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'[id="billingAddress.line2"]'
		);
	standardCardButtonCityInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'[id="billingAddress.city"]'
		);
	standardCardButtonStateInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'[id="billingAddress.state"]'
		);
	standardCardButtonZipCodeInput = () =>
		this.standardCardButtonDetailsIframe().locator(
			'[id="billingAddress.postcode"]'
		);
	standardCardButtonPhoneInput = () =>
		this.standardCardButtonDetailsIframe().locator( '#phone' );
	standardCardButtonEmailInput = () =>
		this.standardCardButtonDetailsIframe().locator( '#email' );
	standardCardButtonPayNowButton = () =>
		this.standardCardButtonDetailsIframe().locator( '#submit-button' );
	standardCardButtonTagline = () =>
		this.page
			.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' )
			.locator( '.paypal-powered-by' );
	standardCardButtonPoweredByText = () =>
		this.standardCardButtonTagline().getByText( 'Powered by' );
	standardCardButtonPoweredByLogo = () =>
		this.standardCardButtonTagline().locator( '.paypal-logo' );

	acdcCardsIcons = () =>
		this.page.locator(
			'.payment_method_ppcp-credit-card-gateway > label > img'
		);
	acdcStoredCredentialsText = () =>
		this.page.locator(
			'#wc-ppcp-credit-card-gateway-payment-token-3 > label'
		);
	acdcSaveToAccountCheckbox = () =>
		this.page.locator( '#wc-ppcp-credit-card-gateway-new-payment-method' );
	acdcSavedCards = () =>
		this.page.locator( '.woocommerce-SavedPaymentMethods-token' );
	acdcSavedCardByNumber = ( cardNumber ) =>
		this.acdcSavedCards()
			.filter( {
				hasText: `ending in ${ getLast4CardDigits( cardNumber ) }`,
			} )
			.first();
	acdcUseNewPaymentRadio = () =>
		this.page.locator( '.woocommerce-SavedPaymentMethods-new > input' );

	threeDSFrame1 = () =>
		this.page
			.frameLocator(
				'[name^="__paypal_checkout_sandbox_paypal-overlay"]'
			)
			.frameLocator( '[name^="__zoid__three_domain_secure__"]' );
	threeDSAcceptCookiesButton = () =>
		this.threeDSFrame1().locator( '#acceptAllButton' );
	threeDSFrame2 = () =>
		this.threeDSFrame1()
			.frameLocator( '#threedsIframeV2' )
			.frameLocator( '#threedsIframe' );
	threeDSOtpInput = () => this.threeDSFrame2().locator( '#otp' );
	threeDSSubmitButton = () =>
		this.threeDSFrame2().locator( '#submit-button' );

	payUponInvoiceBirthDateInput = () =>
		this.page.locator( '#billing_birth_date' );
	payUponInvoicePhoneInput = () =>
		this.payUponInvoiceGateway().locator( 'input[name="billing_phone"]' );
	payUponInvoiceGatewayTitle = () =>
		this.page.locator(
			'label[for="payment_method_ppcp-pay-upon-invoice-gateway"]'
		);
	payUponInvoiceGatewayDescription = () =>
		this.payUponInvoiceGateway().locator(
			'div.payment_method_ppcp-pay-upon-invoice-gateway>p'
		);

	payPalSpinner = () =>
		this.page.locator( '.ppc-button-wrapper .blockUI.blockOverlay' );

	venmoOverlayIframe = () =>
		this.page.frameLocator( '.venmo-checkout-sandbox-iframe' );
	venmoOverlayContinueButton = () =>
		this.venmoOverlayIframe().locator( '.venmo-checkout-continue' );

	payPalButtonMoreOptions = () =>
		this.payPalIframe().locator(
			'.paypal-button-wallet-menu .menu-button'
		);
	payPalMenuIframe = () =>
		this.page.frameLocator( 'iframe[name^="__zoid__paypal_menu__"]' );
	payWithDifferentAccountButton = () =>
		this.payPalMenuIframe().getByText( 'pay with a different account' );

	submitOrder = async () => {
		// on Pay for Order page the button name is Pay for order
		if ( this.page.url().includes( 'pay_for_order' ) ) {
			await this.payForOrderButton().click();
		} else {
			await this.placeOrderButton().click();
		}
	};

	// Actions

	/**
	 * Clicks PayPal button to open popup
	 */
	openPayPalPopup = async (): Promise< PayPalPopup > => {
		const popupPromise = this.page.waitForEvent( 'popup' );
		await expect( this.payPalButton() ).toBeVisible();
		await this.payPalButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	/**
	 * Clicks Pay Later button to open popup
	 */
	openPayLaterPopup = async (): Promise< PayPalPopup > => {
		const popupPromise = this.page.waitForEvent( 'popup' );
		await expect( this.payLaterButton() ).toBeVisible();
		await this.payLaterButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	/**
	 * Clicks Venmo button to open popup
	 */
	openVenmoPopup = async (): Promise< PayPalPopup > => {
		const popupPromise = this.page.waitForEvent( 'popup' );
		await expect( this.venmoButton() ).toBeVisible();
		await this.venmoButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	/**
	 * Completes payment on Classic pages with given payment method
	 *
	 * @param data
	 * @param data.payment
	 * @param data.merchant
	 */
	makeClassicPayment = async ( data: WooCommerce.ShopOrder ) => {
		// Map to the tested method
		let popup: PayPalPopup;
		switch ( data.payment.method ) {
			case 'PayPal':
				if ( await this.payPalGateway().isVisible() ) {
					await this.payPalGateway().click();
				}
				// pay with vaulted account
				if ( data.payment.isVaulted ) {
					await this.assertVaultedPaymentMethodIsDisplayed(
						data.payment
					);
					await this.payPalButton().click();
					break;
				}
				// pay with account other than vaulted
				if ( data.payment.useNotVaultedAccount ) {
					await this.completePayPalPayment(
						await this.openPayPalPupupDifferentAccount(),
						data.payment.useNotVaultedAccount
					);
					break;
				}

				popup = await this.openPayPalPupup();
				await popup.completePayPalPayment( data.payment.payPalAccount );
				break;

			case 'PayLater':
				if ( await this.payPalGateway().isVisible() ) {
					await this.payPalGateway().click();
				}
				popup = await this.openPayLaterPopup();
				await popup.completePayLaterPayment(
					data.payment.payPalAccount
				);
				break;

			case 'ACDC':
				if ( data.payment.isVaulted ) {
					await this.completeAcdcVaultedPayment(
						data.payment,
						data.merchant
					);
					break;
				}
				await this.completeAcdcPayment( data.payment, data.merchant );
				break;

			case 'ACDC3DS':
				await this.completeAcdc3dsPayment(
					data.payment,
					data.merchant
				);
				break;

			case 'OXXO':
				await this.completeOXXOPayment();
				break;

			case 'Venmo':
				popup = await this.openVenmoPopup();
				await popup.completeVenmoPayment();
				break;

			case 'DebitOrCreditCard':
				await this.completeDebitOrCreditCardPayment( data );
				break;

			case 'StandardCardButton':
				await this.completeStandardCardButtonPayment(
					data.payment.card
				);
				break;

			case 'PayUponInvoice':
				await this.completePayUponInvoicePayment(
					data.payment.birthDate,
					data.payment.phone
				);
				break;
		}
	};

	/**
	 * Completes payment on Block pages with given payment method
	 *
	 * @param data
	 */
	makePayment = async ( data ) => {
		let popup: PayPalPopup;
		// Make payment with tested method
		switch ( data.payment.method ) {
			case 'PayPal':
				popup = await this.openBlockPayPalPopup();
				await popup.completePayPalPayment( data.payment.payPalAccount );
				break;

			case 'PayLater':
				popup = await this.openBlockPayLaterPopup();
				await popup.completePayLaterPayment(
					data.payment.payPalAccount
				);
				break;
		}
	};

	/**
	 * Opens PayPal popup
	 *
	 * @param fundingSource
	 * @return PayPalPopup
	 */
	openPayPalPupup = async ( fundingSource = 'paypal' ) => {
		const popupPromise = this.page.waitForEvent( 'popup' );

		await expect( this.fundingSourceButton( fundingSource ) ).toBeVisible();
		await this.fundingSourceButton( fundingSource ).click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	openPayPalPupupDifferentAccount = async () => {
		const popupPromise = this.page.waitForEvent( 'popup' );

		await expect( this.payPalButtonMoreOptions() ).toBeVisible();
		await this.payPalButtonMoreOptions().click();

		await expect( this.payWithDifferentAccountButton() ).toBeVisible();
		await this.payWithDifferentAccountButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	openBlockPayPalPopup = async () => {
		const popupPromise = this.page.waitForEvent( 'popup' );

		await expect( this.blockPayPalButton() ).toBeVisible();
		await this.blockPayPalButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	openBlockPayLaterPopup = async () => {
		const popupPromise = this.page.waitForEvent( 'popup' );

		await expect( this.blockPayLaterButton() ).toBeVisible();
		await this.blockPayLaterButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	/**
	 * Completes payment with PayPal
	 *
	 * @param payPalPopup
	 * @param payPalAccount
	 */
	completePayPalPayment = async (
		payPalPopup: PayPalPopup,
		payPalAccount: PayPalAccount
	) => {
		await payPalPopup.login( payPalAccount.email, payPalAccount.password );
		await payPalPopup.completePayment();
	};

	/**
	 * Completes payment with PayPal
	 *
	 * @param payPalAccount
	 */
	completePayPalVaultedPayment = async ( payPalAccount?: PayPalAccount ) => {
		const popupPromise = this.page.waitForEvent( 'popup' );

		await expect( this.payPalButton() ).toBeVisible();
		await this.payPalButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		const payPalPopup = new PayPalPopup( popup );

		await payPalPopup.completePayment();

		await this.page.waitForLoadState();
	};

	// In the following request Playwright replaces Auth header with Basic Auth from .env,
	// But the header should be from PayPal. Here it's replaced manually:
	replacePayPalAuthToken = async ( merchant: PcpMerchant ) => {
		await this.page.route(
			'https://www.sandbox.paypal.com/v2/checkout/orders/**/*',
			async ( route ) => {
				const token = await this.ppapi.getToken( merchant );
				const originalHeaders = route.request().headers();
				const updatedHeaders = {
					...originalHeaders,
					Authorization: `Bearer ${ token }`,
				};
				await route.continue( { headers: updatedHeaders } );
			}
		);
	};

	/**
	 * Completes payment with ACDC
	 *
	 * @param payment
	 * @param merchant
	 */
	completeAcdcPayment = async (
		payment: PcpPayment,
		merchant: PcpMerchant
	) => {
		await expect( this.acdcGateway() ).toBeVisible();
		await this.acdcGateway().click();

		//if some cards are already stored then "Use a new payment method" radio should be checked
		if ( await this.acdcUseNewPaymentRadio().isVisible() ) {
			await this.acdcUseNewPaymentRadio().check();
		}

		await this.cardNumberInput().fill( payment.card.card_number );
		// trick to properly fill expiration date input
		await this.cardExpirationInput().click();
		for ( const char of payment.card.expiration_date ) {
			await this.page.keyboard.type( char );
			await this.page.waitForTimeout( 250 );
		}
		await this.cardCVVInput().fill( payment.card.card_cvv );

		if ( payment.saveToAccount ) {
			await this.acdcSaveToAccountCheckbox().check();
		}

		await this.submitOrder();
		await this.replacePayPalAuthToken( merchant );
	};

	completeAcdcVaultedPayment = async (
		payment: PcpPayment,
		merchant: PcpMerchant
	) => {
		await expect( this.acdcGateway() ).toBeVisible();
		await this.acdcGateway().click();
		await this.acdcSavedCardByNumber( payment.card.card_number ).click();
		await this.submitOrder();
		await this.replacePayPalAuthToken( merchant );
	};

	completeAcdc3dsPayment = async (
		payment: PcpPayment,
		merchant: PcpMerchant
	) => {
		await this.completeAcdcPayment( payment, merchant );
		// PayPal change: Manual 3DS input is not required any more
		// await this.threeDSAcceptCookiesButton().click();
		// await this.threeDSOtpInput().fill( payment.card.code_3ds );
		// await this.threeDSSubmitButton().click();
	};

	/**
	 * Completes payment with OXXO
	 */
	completeOXXOPayment = async () => {
		await expect( this.oxxoGateway() ).toBeVisible();
		await this.oxxoGateway().click();
		await expect(
			this.page.getByText(
				'OXXO allows you to pay bills and online purchases in-store with cash.'
			)
		).toBeVisible();

		// From 09/12/2025 OXXO popup doesn't appear
		await this.submitOrder();
	};

	/**
	 * Completes payment with Debit Or Credit Card
	 *
	 * @param data
	 */
	completeDebitOrCreditCardPayment = async (
		data: WooCommerce.ShopOrder
	) => {
		const { payment, customer } = data;
		const { card } = payment;
		await expect( this.debitOrCreditCardButton() ).toBeVisible();
		await this.debitOrCreditCardButton().click();
		await this.debitOrCreditCardNumberInput().fill( card.card_number );
		await this.debitOrCreditCardExpirationInput().fill(
			card.expiration_date
		);
		await this.debitOrCreditCardCSCInput().fill( card.card_cvv );
		await this.debitOrCreditCardPayNowButton().click();
		await this.page.waitForTimeout( 2500 );

		// Commented by MUtkin on 30-09-2025 due to changed UI behavior by PayPal
		// const firstName = this.debitOrCreditCardFirstNameInput();
		// if ( await firstName.isVisible() ) {
		// 	await firstName.fill( customer.first_name );
		// }
		// const lastName = this.debitOrCreditCardLastNameInput();
		// if ( await lastName.isVisible() ) {
		// 	await lastName.fill( customer.last_name );
		// }
		// const street = this.debitOrCreditCardStreetInput();
		// if ( await street.isVisible() ) {
		// 	await street.fill( customer.billing.address_1 );
		// }
		// const apartment = this.debitOrCreditCardApartmentInput();
		// if ( await apartment.isVisible() ) {
		// 	await apartment.fill( customer.billing.address_2 );
		// }
		// const city = this.debitOrCreditCardCityInput();
		// if ( await city.isVisible() ) {
		// 	await city.fill( customer.billing.city );
		// }
		// const state = this.debitOrCreditCardStateInput();
		// if ( await state.isVisible() ) {
		// 	await state.fill( customer.billing.state );
		// }
		// const postcode = this.debitOrCreditCardZipCodeInput();
		// if ( await postcode.isVisible() ) {
		// 	await postcode.fill( customer.billing.postcode );
		// }
		// const phone = this.debitOrCreditCardPhoneInput();
		// if ( await phone.isVisible() ) {
		// 	await phone.fill( customer.billing.phone );
		// }
		// const email = this.debitOrCreditCardEmailInput();
		// if ( await email.isVisible() ) {
		// 	await email.fill( customer.billing.email );
		// }
		// const payNow = this.debitOrCreditCardPayNowButton();
		// if ( await payNow.isVisible() ) {
		// 	await payNow.click();
		// }
	};

	/**
	 * Completes payment with Standard Card Button
	 *
	 * @param card
	 */
	completeStandardCardButtonPayment = async (
		card: WooCommerce.CreditCard
	) => {
		await expect( this.standardCardButtonGateway() ).toBeVisible();
		await this.standardCardButtonGateway().click();
		await this.standardCardButton().click();
		await this.standardCardButtonNumberInput().fill( card.card_number );
		await this.standardCardButtonExpirationInput().fill(
			card.expiration_date
		);
		await this.standardCardButtonCSCInput().fill( card.card_cvv );
		await this.standardCardButtonPayNowButton().click();
	};

	/**
	 * Completes payment with Pay upon Invoice
	 *
	 * @param birthDate
	 * @param phone
	 */
	completePayUponInvoicePayment = async (
		birthDate: string,
		phone: string
	) => {
		await expect( this.payUponInvoiceGateway() ).toBeVisible();
		await this.payUponInvoiceGateway().click();
		await this.payUponInvoiceBirthDateInput().click();
		await this.page.keyboard.type( birthDate );
		await this.payUponInvoicePhoneInput().fill( phone );
		await this.submitOrder();
	};

	addCardPaymentMethod = async ( payment: PcpPayment ) => {
		await expect( this.debitCreditCardsGateway() ).toBeVisible();
		await this.debitCreditCardsGateway().click();
		await this.cardNumberInput().fill( payment.card.card_number );
		await this.cardExpirationInput().click();
		await this.page.keyboard.type( payment.card.expiration_date );
		await this.cardCVVInput().fill( payment.card.card_cvv );
		await this.addPaymentMethodButton().click();
	};

	// Assertions

	/**
	 * Asserts the saved payment method is visible
	 *
	 * @param payment
	 */
	assertVaultedPaymentMethodIsDisplayed = async ( payment ) => {
		switch ( payment.gateway.shortcut ) {
			case 'paypal':
				await expect( this.payPalButton() ).toBeVisible();
				break;

			// case 'acdc':
			// 	await expect(
			// 		this.acdcSavedCard( payment.card )
			// 	).toBeVisible();
			// 	break;
		}
	};

	assertPayPalButtonVisibility = async ( isVisible: boolean ) => {
		await expect( this.payPalButton() ).toBeVisible( {
			visible: isVisible,
		} );
	};

	assertPayLaterButtonVisibility = async ( isVisible: boolean ) => {
		await expect( this.payLaterButton() ).toBeVisible( {
			visible: isVisible,
		} );
	};

	assertDebitOrCreditCardButtonVisibility = async ( isVisible: boolean ) => {
		await expect( this.debitOrCreditCardButton() ).toBeVisible( {
			visible: isVisible,
		} );
	};

	assertPoweredByPayPalTextVisibility = async ( isVisible: boolean ) => {
		await expect( this.debitOrCreditCardPoweredByText() ).toBeVisible( {
			visible: isVisible,
		} );
		await expect( this.debitOrCreditCardPoweredByLogo() ).toBeVisible( {
			visible: isVisible,
		} );
	};

	assertMiniCartPayPalButtonVisibility = async ( isVisible: boolean ) => {
		await this.cartMenu().hover();
		await expect( this.miniCartPayPalButton() ).toBeVisible( {
			visible: isVisible,
		} );
	};

	assertPayPalButtonsHaveClass = async ( payPalButtonsFrontEnd, regex ) => {
		const listLengthFrontEnd: any = await payPalButtonsFrontEnd.length;
		for ( let el = 0; el < listLengthFrontEnd; el++ ) {
			await expect( payPalButtonsFrontEnd[ el ] ).toHaveClass( regex );
		}
	};

	assertPayPalButtonsMiniCartHaveClass = async ( regex ) => {
		const payPalButtonsFrontEnd =
			await this.miniCartIframePayPalButton().all();
		const listLengthFrontEnd: any = await payPalButtonsFrontEnd.length;
		for ( let el = 0; el < listLengthFrontEnd; el++ ) {
			await expect( payPalButtonsFrontEnd[ el ] ).toHaveClass( regex );
		}
	};

	collectBlockSmartButtons = async () => {
		const blockSmartButtons: any = [];
		const listIframes = await this.blockSmartButtonIframe().all();
		for ( const iframe of listIframes ) {
			const smartButton = iframe
				.frameLocator( '.component-frame' )
				.locator( '.paypal-button' );
			await blockSmartButtons.push( smartButton );
		}
		return blockSmartButtons;
	};
}
