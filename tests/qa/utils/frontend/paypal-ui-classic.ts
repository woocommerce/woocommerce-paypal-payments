/**
 * External dependencies
 */
import { expect, getLast4CardDigits } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { Pcp } from '../../resources';
import { PayPalPopup } from './paypal-popup';
import { PayPalUi } from './paypal-ui';

/**
 * Class for common dashboard locators, actions, assertions
 */

export class PayPalUiClassic extends PayPalUi {
	// Locators
	cartMenu = () => this.page.locator( '#site-header-cart' );

	payPalIframe = () =>
		this.page.frameLocator(
			// unified selector for My Account and checkout pages
			'[id^="ppc-button-ppcp-gateway"] iframe[name^="__zoid__paypal_buttons__"]'
		);
	payPalButtonsClassicContainer = () =>
		this.payPalIframe().locator( '#buttons-container' );
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

	acdcCardNumberInput = () =>
		this.page
			.frameLocator( 'iframe[title="paypal_card_number_field"]' )
			.locator( 'input.card-field-number ' );
	acdcCardExpirationInput = () =>
		this.page
			.frameLocator( 'iframe[title="paypal_card_expiry_field"]' )
			.locator( 'input.card-field-expiry' );
	acdcCardCvvInput = () =>
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
	miniCartButtonContainer = () =>
		this.miniCartButtonIframe().locator( '#buttons-container' );
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
	acdcSavedCard = ( card: WooCommerce.CreditCard ) =>
		this.acdcSavedCards().filter( {
			hasText: `${ card.card_type } ending in ${ getLast4CardDigits(
				card.card_number
			) } (expires ${ card.expiration_date })`,
		} );
	acdcUseNewPaymentRadio = () =>
		this.page.locator( '.woocommerce-SavedPaymentMethods-new > input' );

	payUponInvoiceBirthDateInput = () =>
		this.page.locator( '#billing_birth_date' );
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
			'.paypal-button-wallet-menu .menu-button, [aria-label="More options"]'
		);
	payPalMenuIframe = () =>
		this.page.frameLocator( 'iframe[name^="__zoid__paypal_menu__"]' );
	payWithDifferentAccountButton = () =>
		this.payPalMenuIframe().getByText( 'Pay with a different account' );

	fastlaneContinueButton = () =>
		this.page.locator( '#ppcp-axo-billing-email-submit-button' );
	fastlaneContactContainer = () =>
		this.page.locator( '#ppcp-axo-billing-email-field-wrapper' );
	fastlaneEmailInput = () =>
		this.fastlaneContactContainer().locator(
			'input[name="billing_email"]'
		);
	fastlaneGateway = () =>
		this.page.locator(
			'li.payment_method_ppcp-axo-gateway label[for="payment_method_ppcp-axo-gateway"]'
		);

	usingVaultedPayPalAccountText = ( payPalEmail: string ) =>
		this.page.getByText( `Using ${ payPalEmail } PayPal.` );

	/** Host element with paypal-buttons-label-* and paypal-buttons-layout-* classes (product, cart, checkout). */
	payPalButtonsHostElement = () =>
		this.page.locator( '#ppc-button-ppcp-gateway .paypal-buttons' );

	/** Host element for mini cart PayPal buttons. */
	minicartPayPalButtonsHostElement = () =>
		this.page.locator( '#ppc-button-minicart .paypal-buttons' );

	// Actions

	/**
	 * Clicks PayPal gateway and PayPal button to open popup
	 * Actual on Pay for Order and classic checkout pages
	 */
	async openPayPalPopup(): Promise< PayPalPopup > {
		// Select gateway if not on classic-cart page
		if ( ! this.page.url().includes( 'classic-cart' ) ) {
			await expect (
				this.payPalGateway(),
				'Assert PayPal gateway is visible',
			).toBeVisible();
			await this.payPalGateway().click();
		}
		return await super.openPayPalPopup();
	}

	/**
	 * Clicks PayPal gateway and Pay Later button to open popup
	 * Actual on Pay for Order and classic checkout pages
	 */
	async openPayLaterPopup(): Promise< PayPalPopup > {
		// Select gateway if not on classic-cart page
		if ( ! this.page.url().includes( 'classic-cart' ) ) {
			await this.payPalGateway().click();
		}
		return await super.openPayLaterPopup();
	}

	/**
	 * Completes payment with ACDC
	 *
	 * @param payment
	 * @param merchant
	 */
	completeAcdcPayment = async (
		payment: Pcp.Payment,
		merchant: Pcp.Merchant
	) => {
		const { card, saveToAccount, isVaulted } = payment;
		await expect(
			this.acdcGateway(),
			'Assert ACDC gateway is visible'
		).toBeVisible();
		await this.acdcGateway().click();

		//if some cards are already stored then "Use a new payment method" radio should be checked
		if (
			! isVaulted &&
			( await this.acdcUseNewPaymentRadio().isVisible() )
		) {
			await this.acdcUseNewPaymentRadio().check();
		}

		await expect(
			this.acdcCardNumberInput(),
			'Assert ACDC card number input is visible'
		).toBeVisible();
		await this.acdcCardNumberInput().fill( card.card_number );

		await expect(
			this.acdcCardExpirationInput(),
			'Assert ACDC card expiration input is visible'
		).toBeVisible();
		// trick to properly fill expiration date input
		await this.acdcCardExpirationInput().click();
		for ( const char of card.expiration_date ) {
			await this.page.keyboard.type( char );
			await this.page.waitForTimeout( 200 );
		}

		await expect(
			this.acdcCardCvvInput(),
			'Assert ACDC card CVV input is visible'
		).toBeVisible();
		await this.acdcCardCvvInput().fill( card.card_cvv );

		if ( saveToAccount ) {
			await expect(
				this.acdcSaveToAccountCheckbox(),
				'Assert ACDC save to account checkbox is visible'
			).toBeVisible();
			await this.acdcSaveToAccountCheckbox().check();
		}

		await this.replacePayPalAuthToken( merchant );
		await this.submitOrder();
	};

	/**
	 * Completes payment with ACDC (vaulting enabled)
	 *
	 * @param payment
	 * @param merchant
	 */
	completeAcdcVaultedPayment = async (
		payment: Pcp.Payment,
		merchant: Pcp.Merchant
	) => {
		const acdcGateway = this.acdcGateway();
		await expect(
			acdcGateway,
			'Assert ACDC gateway is visible'
		).toBeVisible();
		await acdcGateway.click();

		const savedCard = this.acdcSavedCard( payment.card );
		await expect(
			savedCard,
			'Assert saved ACDC card is visible'
		).toBeVisible();
		await savedCard.click();

		await this.replacePayPalAuthToken( merchant );
		await this.submitOrder();
	};

	/**
	 * Completes payment with OXXO (vaulting disabled)
	 */
	completeOXXOPayment = async () => {
		await expect(
			this.oxxoGateway(),
			'Assert OXXO gateway is visible'
		).toBeVisible();
		await this.oxxoGateway().click();
		await expect(
			this.page.getByText(
				'OXXO allows you to pay bills and online purchases in-store with cash.'
			),
			'Assert OXXO description is visible'
		).toBeVisible();

		const popupPromise = this.page.waitForEvent( 'popup', {
			timeout: 20 * 1000,
		} );
		await this.submitOrder();
		const popup = await popupPromise;
		const paypal = new PayPalPopup( popup );

		await expect(
			paypal.page.getByText( 'Successful Payment', { exact: true } ),
			'Assert OXXO successful payment message is visible in PayPal popup'
		).toBeVisible();

		await popup.close();
	};

	/**
	 * Completes payment with Debit Or Credit Card (vaulting disabled)
	 *
	 * @param card
	 */
	completeDebitOrCreditCardPayment = async (
		card: WooCommerce.CreditCard
	) => {
		await expect(
			this.debitOrCreditCardButton(),
			'Assert debit or credit card button is visible'
		).toBeVisible();
		await this.debitOrCreditCardButton().click();

		await expect(
			this.debitOrCreditCardNumberInput(),
			'Assert debit or credit card number input is visible'
		).toBeVisible();
		await this.debitOrCreditCardNumberInput().fill( card.card_number );

		await expect(
			this.debitOrCreditCardExpirationInput(),
			'Assert debit or credit card expiration input is visible'
		).toBeVisible();
		await this.debitOrCreditCardExpirationInput().fill(
			card.expiration_date
		);

		await expect(
			this.debitOrCreditCardCSCInput(),
			'Assert debit or credit card CSC input is visible'
		).toBeVisible();
		await this.debitOrCreditCardCSCInput().fill( card.card_cvv );

		await expect(
			this.debitOrCreditCardPayNowButton(),
			'Assert debit or credit card pay now button is visible'
		).toBeVisible();
		await this.debitOrCreditCardPayNowButton().click();
	};

	/**
	 * Completes payment with Standard Card Button (vaulting disabled)
	 *
	 * @param card
	 */
	completeStandardCardButtonPayment = async (
		card: WooCommerce.CreditCard
	) => {
		await expect(
			this.standardCardButtonGateway(),
			'Assert standard card button gateway is visible'
		).toBeVisible();
		await this.standardCardButtonGateway().click();

		await expect(
			this.standardCardButton(),
			'Assert standard card button is visible'
		).toBeVisible();
		await this.standardCardButton().click();

		await expect(
			this.standardCardButtonNumberInput(),
			'Assert standard card button number input is visible'
		).toBeVisible();
		await this.standardCardButtonNumberInput().fill( card.card_number );

		await expect(
			this.standardCardButtonExpirationInput(),
			'Assert standard card button expiration input is visible'
		).toBeVisible();
		await this.standardCardButtonExpirationInput().fill(
			card.expiration_date
		);

		await expect(
			this.standardCardButtonCSCInput(),
			'Assert standard card button CSC input is visible'
		).toBeVisible();
		await this.standardCardButtonCSCInput().fill( card.card_cvv );

		await expect(
			this.standardCardButtonPayNowButton(),
			'Assert standard card button pay now button is visible'
		).toBeVisible();
		await this.standardCardButtonPayNowButton().click();
	};

	/**
	 * Completes payment with Pay upon Invoice (vaulting disabled)
	 *
	 * @param birthDate
	 */
	completePayUponInvoicePayment = async ( birthDate: string ) => {
		await expect(
			this.payUponInvoiceGateway(),
			'Assert pay upon invoice gateway is visible'
		).toBeVisible();
		await this.payUponInvoiceGateway().click();

		await expect(
			this.payUponInvoiceBirthDateInput(),
			'Assert pay upon invoice birth date input is visible'
		).toBeVisible();
		await this.payUponInvoiceBirthDateInput().click();
		await this.page.keyboard.type( birthDate ); // Trick to properly fill date

		await this.submitOrder();
	};

	addCardPaymentMethod = async ( payment: Pcp.Payment ) => {
		const { card } = payment;
		await expect(
			this.debitCreditCardsGateway(),
			'Assert debit credit cards gateway is visible'
		).toBeVisible();
		await this.debitCreditCardsGateway().click();

		await expect(
			this.acdcCardNumberInput(),
			'Assert ACDC card number input is visible'
		).toBeVisible();
		await this.acdcCardNumberInput().fill( card.card_number );

		await expect(
			this.acdcCardExpirationInput(),
			'Assert ACDC card expiration input is visible'
		).toBeVisible();
		await this.acdcCardExpirationInput().click();
		await this.page.keyboard.type( card.expiration_date ); // Trick to properly fill date

		await expect(
			this.acdcCardCvvInput(),
			'Assert ACDC card CVV input is visible'
		).toBeVisible();
		await this.acdcCardCvvInput().fill( card.card_cvv );

		await expect(
			this.addPaymentMethodButton(),
			'Assert add payment method button is visible'
		).toBeVisible();
		await this.addPaymentMethodButton().click();
	};

	// Assertions

	/**
	 * Asserts PayPal buttons gateway container is visible and contains PayPal button (product, classic cart, classic checkout).
	 */
	assertPayPalButtonsGatewayVisibleWithContent = async () => {
		await expect(
			this.payPalButtonsHostElement(),
			'Assert PayPal buttons gateway host is visible'
		).toBeVisible();
		await expect(
			this.payPalButton(),
			'Assert PayPal button is visible'
		).toBeVisible();
	};

	/**
	 * Asserts PayPal buttons have the given label (pay, checkout, buynow, paypal).
	 *
	 * @param label   - Expected label value
	 * @param context - 'gateway' for product/cart/checkout, 'minicart' for mini cart
	 */
	assertPayPalButtonsHaveLabel = async (
		label: 'pay' | 'checkout' | 'buynow' | 'paypal',
		context: 'gateway' | 'minicart' = 'gateway'
	) => {
		const host =
			context === 'minicart'
				? this.minicartPayPalButtonsHostElement()
				: this.payPalButtonsHostElement();
		await expect(
			host,
			`Assert PayPal buttons have label ${ label }`
		).toHaveClass( new RegExp( `paypal-buttons-label-${ label }` ) );
	};

	/**
	 * Asserts PayPal buttons have the given layout (vertical, horizontal).
	 *
	 * @param layout  - Expected layout value
	 * @param context - 'gateway' for product/cart/checkout, 'minicart' for mini cart
	 */
	assertPayPalButtonsHaveLayout = async (
		layout: 'vertical' | 'horizontal',
		context: 'gateway' | 'minicart' = 'gateway'
	) => {
		const host =
			context === 'minicart'
				? this.minicartPayPalButtonsHostElement()
				: this.payPalButtonsHostElement();
		await expect(
			host,
			`Assert PayPal buttons have layout ${ layout }`
		).toHaveClass( new RegExp( `paypal-buttons-layout-${ layout }` ) );
	};

	/**
	 * Asserts PayPal buttons are not visible.
	 *
	 * @param context - 'gateway' for product/cart/checkout, 'minicart' for mini cart
	 */
	assertPayPalButtonsNotVisible = async (
		context: 'gateway' | 'minicart' = 'gateway'
	) => {
		const selector =
			context === 'minicart'
				? '#ppc-button-minicart .paypal-buttons'
				: '#ppc-button-ppcp-gateway .paypal-buttons';
		await expect(
			this.page.locator( selector ),
			`Assert PayPal buttons (${ context }) are not visible`
		).toBeHidden();
	};
}
