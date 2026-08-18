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
	payPalButtonsContainer = () =>
		this.page.locator(
			'#ppc-button-ppcp-gateway, #ppc-button-ppcp-gateway-v6'
		);

	googlePayGatewayContainer = () =>
		this.page.locator( '#ppc-button-googlepay-container' );
	fundingSourceButton = ( name: string ) => {
		switch ( name ) {
			case 'paypal':
				return this.payPalButton();
			case 'paylater':
				return this.payLaterButton();
			case 'venmo':
				return this.venmoButton();
			default:
				throw new Error( `Unsupported funding source: ${ name }` );
		}
	};

	googlePayButton = () => this.page.locator( '#gpay-button-online-api-id' );

	fundingSourceGateway = ( name: Pcp.GatewayId ) =>
		this.page.locator( `li.payment_method_${ name }` );
	payPalGateway = () => this.fundingSourceGateway( 'ppcp-gateway' );
	payPalVaultedPaymentMethodRadio = () =>
		this.page.locator(
			'input[id^="wc-ppcp-gateway-payment-token-"]:not([id="wc-ppcp-gateway-payment-token-new"])'
		);
	payPalNewPaymentMethodRadio = () =>
		this.page.locator( 'input#wc-ppcp-gateway-payment-token-new' );

	acdcGateway = () =>
		this.fundingSourceGateway( 'ppcp-credit-card-gateway' );
	bcdcGateway = () =>
		this.fundingSourceGateway( 'ppcp-card-button-gateway' );
	oxxoGateway = () =>
		this.fundingSourceGateway( 'ppcp-oxxo-gateway' );
	puiGateway = () =>
		this.fundingSourceGateway( 'ppcp-pay-upon-invoice-gateway' );
	googlePayGateway = () => this.fundingSourceGateway( 'ppcp-googlepay' );

	acdcCardNumberInput = () =>
		this.page.locator( '#ppcp-credit-card-gateway-card-number' );
	acdcCardExpirationInput = () =>
		this.page.locator( '#ppcp-credit-card-gateway-card-expiry' );
	acdcCardCvvInput = () =>
		this.page.locator( '#ppcp-credit-card-gateway-card-cvc' );
	addPaymentMethodButton = () => this.page.locator( '#place_order' );

	miniCartButtonContainer = () =>
		this.page
			.locator( '#ppc-button-minicart-v6' )
			.locator( 'paypal-button, venmo-button, paypal-pay-later-button' );

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
	bcdcFirstNameInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.givenName"]' );
	bcdcLastNameInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.familyName"]' );
	bcdcStreetInput = () =>
		this.bcdcDetailsIframe().locator( '[id="billingAddress.line1"]' );
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

	/** Host element with paypal-buttons-label-* and paypal-buttons-layout-* classes (product, cart, checkout). */
	payPalButtonsHostElement = () =>
		this.payPalButtonsContainer().locator( '.paypal-buttons, paypal-button' );

	// Actions

	/**
	 * Clicks PayPal gateway and PayPal button to open popup
	 * Actual on Pay for Order and classic checkout pages
	 */
	async openPayPalPopup(): Promise< PayPalPopup > {
		// Select gateway if not on classic-cart page
		if ( this.isClassicCheckoutPage() || this.isPayForOrderPage() ) {
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
		if ( this.isClassicCheckoutPage() || this.isPayForOrderPage() ) {
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
		if ( this.isClassicCheckoutPage() || this.isPayForOrderPage() ) {
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
	 * Completes payment with vaulted PayPal account
	 */
	async completePayPalVaultedPayment( payment: Pcp.Payment ) {
		// On classic checkout, pay for order pages
		if ( this.isClassicCheckoutPage() || this.isPayForOrderPage() ) {
			await this.assertVaultedPaymentMethodIsDisplayed( payment );
			await this.payPalVaultedPaymentMethodRadio().click();
			await this.submitOrder();
			return;
		}
		// On classic cart, product pages
		return super.completePayPalVaultedPayment( payment );
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
	 * Asserts the saved payment method is visible
	 *
	 * @param payment
	 */
	assertVaultedPaymentMethodIsDisplayed = async ( payment: Pcp.Payment ) => {
		const { gateway, card } = payment;
		switch ( gateway.shortcut ) {
			case 'paypal':
				if ( this.isClassicCheckoutPage() || this.isPayForOrderPage() ) {
					// On Classic checkout, Pay for order pages
					await expect(
						this.payPalGateway(),
						'Assert PayPal gateway is visible',
					).toBeVisible();
					await this.payPalGateway().click( { position: { x: 10, y: 10 } } );

					await expect(
						this.payPalButtonsContainer(),
						'Assert PayPal buttons are NOT visible',
					).not.toBeVisible();

					if ( ! payment.isFreeTrialSubscription ) {
						// Free trial cart: PayPal doesn't render the vault component
						// (see FreeTrialSubscriptionHelper::is_free_trial_cart()), only
						// the plain saved-token radio.
						await expect(
							this.payPalVaultComponent(),
							'Assert PayPal vault component is visible',
						).toBeVisible();
					}

					await expect(
						this.payPalVaultedPaymentMethodRadio(),
						'Assert vaulted PayPal radio button is visible',
					).toBeVisible();

					await expect(
						this.payPalNewPaymentMethodRadio(),
						'Assert new PayPal payment method radio button is visible',
					).toBeVisible();
					
					await expect(
						this.placeOrderButton(),
						'Assert Place Order button is visible',
					).toBeVisible();
					break;
				}
				// On classic cart, product pages:
				await expect(
					this.payPalButton(),
					'Assert PayPal button is visible'
				).toBeVisible();
				break;

			case 'acdc':
				await expect(
					this.acdcGateway(),
					'Assert ACDC gateway is visible',
				).toBeVisible();
				await this.acdcGateway().click();

				await expect(
					this.acdcSavedCard( card ),
					'Assert ACDC saved card is visible'
				).toBeVisible();
				break;
		}
	};

	/**
	 * Asserts the saved payment method is not visible
	 *
	 * @param payment
	 */
	assertVaultedPaymentMethodIsNotDisplayed = async (
		payment: Pcp.Payment
	) => {
		switch ( payment.gateway.shortcut ) {
			case 'paypal':
				await expect(
					this.payPalGateway(),
					'Assert PayPal gateway is visible',
				).toBeVisible();
				await this.payPalGateway().click();

				await expect(
					this.payPalVaultComponent(),
					'Assert PayPal vault component is NOT visible',
				).not.toBeVisible();

				await expect(
					this.payPalVaultedPaymentMethodRadio(),
					'Assert vaulted PayPal radio button is NOT visible',
				).not.toBeVisible();

				await expect(
					this.payPalNewPaymentMethodRadio(),
					'Assert new PayPal payment method radio button is NOT visible',
				).not.toBeVisible();

				await expect(
					this.payPalButtonsContainer(),
					'Assert PayPal buttons are visible',
				).toBeVisible();
				
				await expect(
					this.placeOrderButton(),
					'Assert Place Order button is NOT visible',
				).not.toBeVisible();
				break;

			case 'acdc':
				await expect(
					this.acdcGateway(),
					'Assert ACDC gateway is visible',
				).toBeVisible();
				await this.acdcGateway().click();

				await expect(
					this.acdcSavedCard( payment.card ),
					'Assert ACDC saved card is NOT visible'
				).not.toBeVisible();
				break;
		}
	};

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
}
