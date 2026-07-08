/**
 * External dependencies
 */
import { expect, getLast4CardDigits } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { Pcp, ShopOrder } from '../../resources';
import { PayPalPopup } from './paypal-popup';
import { PayPalUi } from './paypal-ui';
import { GooglePayPopup } from './google-pay-popup';

/**
 * Class for common dashboard locators, actions, assertions
 */

export class PayPalUiClassic extends PayPalUi {
	// Locators
	cartMenu = () => this.page.locator( '#site-header-cart' );

	payPalGatewayContainer = () =>
		this.page.locator( '#ppc-button-ppcp-gateway' );

	googlePayGatewayContainer = () =>
		this.page.locator( '#ppc-button-googlepay-container' );
	payPalIframe = () =>
		this.payPalGatewayContainer()
			.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' );
	payPalButtonsClassicContainer = () =>
		this.payPalIframe().locator( '#buttons-container' );
	fundingSourceButton = ( name ) =>
		this.payPalIframe().locator( `[data-funding-source="${ name }"]` );

	payPalButton = () => this.fundingSourceButton( 'paypal' );
	payLaterButton = () => this.fundingSourceButton( 'paylater' );
	sepaButton = () => this.fundingSourceButton( 'sepa' );
	giropayButton = () => this.fundingSourceButton( 'giropay' );
	sofortButton = () => this.fundingSourceButton( 'sofort' );
	bcdcFundingSourceButton = () => this.fundingSourceButton( 'card' );
	venmoButton = () => this.fundingSourceButton( 'venmo' );
	googlePayButton = () => this.page.locator( '#gpay-button-online-api-id' );

	fundingSourceButtonLabelText = ( name ) =>
		this.fundingSourceButton( name ).locator( '.paypal-button-text' ); // additional text on paypal buttons
	fundingSourceButtonPayLabel = ( name ) =>
		this.fundingSourceButton( name ).locator( '.pay-label' ); // for example Pay Now when vaulting
	fundingSourceButtonLabel = ( name ) =>
		this.fundingSourceButton( name ).locator( '.label' ); // customer's email if Vaulting
	iframePayPalButton = () => this.payPalIframe().locator( '.paypal-button' );
	iframePayPalButtonText = () =>
		this.payPalIframe().locator( '.paypal-button-text.true' );

	fundingSourceGateway = ( name: Pcp.GatewayId ) =>
		this.page.locator( `li.payment_method_${ name }` );
	payPalGateway = () => this.fundingSourceGateway( 'ppcp-gateway' );
	acdcGateway = () =>
		this.fundingSourceGateway( 'ppcp-credit-card-gateway' );
	bcdcGateway = () =>
		this.fundingSourceGateway( 'ppcp-card-button-gateway' );
	oxxoGateway = () =>
		this.fundingSourceGateway( 'ppcp-oxxo-gateway' );
	puiGateway = () =>
		this.fundingSourceGateway( 'ppcp-pay-upon-invoice-gateway' );
	googlePayGateway = () => this.fundingSourceGateway( 'ppcp-googlepay' );

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

	bcdcFundingSourceIframe = () =>
		this.payPalIframe().frameLocator( 'iframe.zoid-visible' );
	bcdcFundingSourceNumberInput = () =>
		this.bcdcFundingSourceIframe().locator( '#credit-card-number' );
	bcdcFundingSourceExpirationInput = () =>
		this.bcdcFundingSourceIframe().locator( '#expiry-date' );
	bcdcFundingSourceCSCInput = () =>
		this.bcdcFundingSourceIframe().locator( '#credit-card-security' );
	bcdcFundingSourceBuyNowButton = () =>
		this.bcdcFundingSourceIframe().locator( '#submit-button' );
	bcdcFundingSourceFirstNameInput = () =>
		this.bcdcFundingSourceIframe().locator(
			'[id="billingAddress.givenName"]'
		);
	bcdcFundingSourceLastNameInput = () =>
		this.bcdcFundingSourceIframe().locator(
			'[id="billingAddress.familyName"]'
		);
	bcdcFundingSourceStreetInput = () =>
		this.bcdcFundingSourceIframe().locator( '[id="billingAddress.line1"]' );
	bcdcFundingSourceApartmentInput = () =>
		this.bcdcFundingSourceIframe().locator( '[id="billingAddress.line2"]' );
	bcdcFundingSourceCityInput = () =>
		this.bcdcFundingSourceIframe().locator( '[id="billingAddress.city"]' );
	bcdcFundingSourceStateInput = () =>
		this.bcdcFundingSourceIframe().locator( '[id="billingAddress.state"]' );
	bcdcFundingSourceZipCodeInput = () =>
		this.bcdcFundingSourceIframe().locator(
			'[id="billingAddress.postcode"]'
		);
	bcdcFundingSourcePhoneInput = () =>
		this.bcdcFundingSourceIframe().locator( '#phone' );
	bcdcFundingSourceEmailInput = () =>
		this.bcdcFundingSourceIframe().locator( '#email' );
	bcdcFundingSourcePayNowButton = () =>
		this.bcdcFundingSourceIframe().locator( '#submit-button' );
	bcdcFundingSourceTagline = () =>
		this.page
			.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' )
			.locator( '.paypal-powered-by' );
	bcdcFundingSourcePoweredByText = () =>
		this.bcdcFundingSourceTagline().getByText( 'Powered by' );
	bcdcFundingSourcePoweredByLogo = () =>
		this.bcdcFundingSourceTagline().locator( '.paypal-logo' );

	bcdcIframe = () =>
		this.page.frameLocator(
			'#ppc-button-ppcp-card-button-gateway .component-frame'
		);
	bcdcButton = () =>
		this.bcdcIframe().locator( `[data-funding-source="card"]` );
	bcdcDetailsIframe = () =>
		this.bcdcIframe().frameLocator( 'iframe.zoid-visible' );
	bcdcNumberInput = () =>
		this.bcdcDetailsIframe().locator( '#credit-card-number' );
	bcdcExpirationInput = () =>
		this.bcdcDetailsIframe().locator( '#expiry-date' );
	bcdcCSCInput = () =>
		this.bcdcDetailsIframe().locator( '#credit-card-security' );
	bcdcBuyNowButton = () =>
		this.bcdcDetailsIframe().locator( '#submit-button' );
	bcdcFirstNameInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.givenName"]' );
	bcdcLastNameInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.familyName"]' );
	bcdcStreetInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.line1"]' );
	bcdcApartmentInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.line2"]' );
	bcdcCityInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.city"]' );
	bcdcCountryInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.country"]' );
	bcdcStateInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.state"]' );
	bcdcZipCodeInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.postcode"]' );
	bcdcPhoneInput = () => this.bcdcDetailsIframe().locator( '#phone' );
	bcdcEmailInput = () => this.bcdcDetailsIframe().locator( '#email' );
	bcdcPayNowButton = () =>
		this.bcdcDetailsIframe().locator( '#submit-button' );
	bcdcTagline = () =>
		this.page
			.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' )
			.locator( '.paypal-powered-by' );
	bcdcPoweredByText = () => this.bcdcTagline().getByText( 'Powered by' );
	bcdcPoweredByLogo = () => this.bcdcTagline().locator( '.paypal-logo' );

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

	puiBirthDateInput = () =>
		this.puiGateway().locator( '#billing_birth_date' );
	puiPhoneInput = () =>
		this.puiGateway().locator( '#billing_phone' );
	puiGatewayTitle = () =>
		this.puiGateway().locator(
			'label[for="payment_method_ppcp-pay-upon-invoice-gateway"]'
		);
	puiGatewayDescription = () =>
		this.puiGateway().locator(
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
		if (
			this.page.url().includes( 'classic-checkout' ) ||
			this.page.url().includes( 'pay_for_order' )
		) {
			await expect(
				this.payPalGateway(),
				'Assert PayPal gateway is visible'
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
		if (
			this.page.url().includes( 'classic-checkout' ) ||
			this.page.url().includes( 'pay_for_order' )
		) {
			await expect(
				this.payPalGateway(),
				'Assert PayPal gateway is visible'
			).toBeVisible();
			await this.payPalGateway().click();
		}
		return await super.openPayLaterPopup();
	}
	
	/**
	 * Clicks Google Pay button to open the TEST environment popup
	 */
	openGooglePayPopup = async (): Promise< GooglePayPopup > => {
		if(
			this.page.url().includes( 'classic-checkout' ) ||
			this.page.url().includes( 'pay_for_order' )
		) {
			await expect(
				this.googlePayGateway(),
				'Assert Google Pay gateway is visible'
			).toBeVisible();
			await this.googlePayGateway().click();
		}
		await expect(
			this.googlePayButton(),
			'Assert Google Pay button is visible'
		).toBeVisible();
		const popupPromise = this.page.waitForEvent( 'popup', {
			timeout: 20 * 1000,
		} );
		await this.googlePayButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new GooglePayPopup( popup );
	};

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
		// Close without clicking — payment simulation happens from the thank-you page voucher button
		await popup.waitForLoadState().catch( () => {} );
		await popup.close();
	};

	/**
	 * Completes payment with BCDC funding source (vaulting disabled)
	 *
	 * @param card
	 */
	completeBcdcFundingSourcePayment = async (
		card: WooCommerce.CreditCard
	) => {
		await expect(
			this.bcdcFundingSourceButton(),
			'Assert BCDC funding source button is visible'
		).toBeVisible();
		await this.bcdcFundingSourceButton().click();

		await expect(
			this.bcdcFundingSourceNumberInput(),
			'Assert BCDC funding source number input is visible'
		).toBeVisible();
		await this.bcdcFundingSourceNumberInput().fill( card.card_number );

		await expect(
			this.bcdcFundingSourceExpirationInput(),
			'Assert BCDC funding source expiration input is visible'
		).toBeVisible();
		await this.bcdcFundingSourceExpirationInput().fill(
			card.expiration_date
		);

		await expect(
			this.bcdcFundingSourceCSCInput(),
			'Assert BCDC funding source CSC input is visible'
		).toBeVisible();
		await this.bcdcFundingSourceCSCInput().fill( card.card_cvv );

		await expect(
			this.bcdcFundingSourcePayNowButton(),
			'Assert BCDC funding source pay now button is visible'
		).toBeVisible();
		await this.bcdcFundingSourcePayNowButton().click();
	};

	/**
	 * Completes payment with BCDC (vaulting disabled)
	 *
	 * @param card
	 * @param customer
	 */
	completeBcdcPayment = async (
		card: WooCommerce.CreditCard,
		customer?: ShopOrder[ 'customer' ]
	) => {
		await expect(
			this.bcdcGateway(),
			'Assert BCDC gateway is visible'
		).toBeVisible();
		await this.bcdcGateway().click();

		await expect(
			this.bcdcButton(),
			'Assert BCDC button is visible'
		).toBeVisible();
		await this.bcdcButton().click();

		await expect(
			this.bcdcNumberInput(),
			'Assert BCDC number input is visible'
		).toBeVisible();
		await this.bcdcNumberInput().fill( card.card_number );

		await expect(
			this.bcdcExpirationInput(),
			'Assert BCDC expiration input is visible'
		).toBeVisible();
		await this.bcdcExpirationInput().fill( card.expiration_date );

		await expect(
			this.bcdcCSCInput(),
			'Assert BCDC CSC input is visible'
		).toBeVisible();
		await this.bcdcCSCInput().fill( card.card_cvv );

		// Latin America (Mexico) uses minimal mode: PayPal shows its own billing form inside the modal.
		const firstNameInput = this.bcdcFirstNameInput();
		if ( ( await firstNameInput.isVisible() ) && customer?.billing ) {
			const { billing } = customer;
			const countryInput = this.bcdcCountryInput();
			const stateInput = this.bcdcStateInput();
			await stateInput.waitFor( { state: 'visible', timeout: 30000 } );

			if ( billing.country ) {
				await countryInput.selectOption(
					{ value: billing.country },
					{ force: true }
				);
			}

			await firstNameInput.fill( billing.first_name );
			await this.bcdcLastNameInput().fill( billing.last_name );
			await this.bcdcStreetInput().fill( billing.address_1 );
			await this.bcdcCityInput().fill( billing.city );
			await this.bcdcZipCodeInput().fill( billing.postcode );
			if ( billing.email ) {
				const emailInput = this.bcdcEmailInput();
				if ( await emailInput.isVisible() ) {
					await emailInput.fill( billing.email );
				}
			}
			if ( billing.phone ) {
				const phoneInput = this.bcdcPhoneInput();
				if ( await phoneInput.isVisible() ) {
					await phoneInput.fill( billing.phone );
				}
			}

			const stateOptions = await stateInput.evaluate(
				( el: HTMLSelectElement ) =>
					el.tagName.toLowerCase() === 'select'
						? Array.from( el.options )
								.filter( ( o ) => o.value )
								.map( ( o ) => ( { v: o.value, t: o.text } ) )
						: []
			);
			const stateCode = billing.state?.trim() ?? '';
			if ( stateOptions.length > 0 ) {
				const match =
					stateOptions.find( ( o ) => o.v === stateCode ) ||
					stateOptions.find( ( o ) =>
						o.t.toLowerCase().includes( stateCode.toLowerCase() )
					) ||
					stateOptions[ 0 ];
				await stateInput.selectOption(
					{ value: match.v },
					{ force: true }
				);
			} else if ( stateCode ) {
				await stateInput.fill( stateCode );
			}
		}

		await expect(
			this.bcdcPayNowButton(),
			'Assert BCDC pay now button is visible'
		).toBeVisible();
		await this.bcdcPayNowButton().click();
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
