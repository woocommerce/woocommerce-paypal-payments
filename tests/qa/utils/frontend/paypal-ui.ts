/**
 * External dependencies
 */
import { Page } from '@playwright/test';
import {
	expect,
	getLast4CardDigits,
	assertIframeWithRetry,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { Pcp, ShopOrder } from '../../resources';
import { PayPalPopup } from './paypal-popup';
import { GooglePayPopup } from './google-pay-popup';
import { ApmHostedCheckout } from './apm-hosted-checkout';
// TODO: get resolution about OXXO voucher popup
// import { OxxoVoucherPopup } from './oxxo-voucher-popup';
import { PayPalApi } from '../paypal-api';
import { sdkVersion } from '../helpers/sdk-version.helper';

/**
 * Class for common dashboard locators, actions, assertions
 */

export class PayPalUi {
	page: Page;
	payPalApi: PayPalApi;

	constructor( { page, payPalApi } ) {
		this.page = page;
		this.payPalApi = payPalApi;
	}

	// Locators
	paymentOptionsContainers = () =>
		this.page
			.locator( '#payment-method' )
			.locator( '.wc-block-components-radio-control' );

	// "Place Order" or "Pay for order" or "Sign up now" button
	placeOrderButton = () =>
		this.page
			.getByRole( 'button', { name: 'Place order' } )
			.or( this.page.getByRole( 'button', { name: 'Pay for order' } ) )
			.or( this.page.getByRole( 'button', { name: 'Sign up now' } ) )
			.or( this.page.getByRole( 'button', { name: 'Proceed to PayPal' } ) );

	payPalButtonsBlockContainer = () =>
		this.page.locator(
			'.wc-block-components-express-payment__event-buttons'
		);
	/** v5 (legacy): unified zoid iframe for My Account and checkout pages. */
	payPalIframeV5 = () =>
		this.page.frameLocator(
			'#express-payment-method-ppcp-gateway-paypal .component-frame'
		);
	payPalButton = () =>
		sdkVersion() === 'v5'
			? this.payPalIframeV5().locator( '[data-funding-source="paypal"]' )
			: this.page.locator(
					'#express-payment-method-ppcp-gateway-paypal paypal-button, #ppc-button-ppcp-gateway-v6 paypal-button, #ppc-button-ppcp-gateway-save-payment-method paypal-button'
			  );
	payLaterButton = () =>
		sdkVersion() === 'v5'
			? this.page
					.frameLocator(
						'#express-payment-method-ppcp-gateway-paylater .component-frame'
					)
					.locator( '[data-funding-source="paylater"]' )
			: this.page.locator(
					'#express-payment-method-ppcp-gateway-paylater paypal-pay-later-button, #ppc-button-ppcp-gateway-v6 paypal-pay-later-button'
			  );
	venmoButton = () =>
		sdkVersion() === 'v5'
			? this.page
					.frameLocator(
						'#express-payment-method-ppcp-gateway-venmo .component-frame'
					)
					.locator( '[data-funding-source="venmo"]' )
			: this.page.locator(
					'#express-payment-method-ppcp-gateway-venmo venmo-button, #ppc-button-ppcp-gateway-v6 venmo-button'
			  );

	googlePayButton = () =>
		this.page
			.locator( '#express-payment-method-ppcp-googlepay .gpay-button' )
			.or(
				this.page.locator(
					'#express-payment-method-ppcp-googlepay button'
				)
			)
			.first();

	payPalGateway = () =>
		this.page.locator(
			'#radio-control-wc-payment-method-options-ppcp-gateway__label'
		);
	payPalVaultedGateway = () =>
		this.paymentOptionsContainers().filter( {
			hasText: 'Saved token for ppcp-gateway',
		} );
	payPalVaultComponent = () =>
		this.page.locator( '#ppcp-vault-component' );

	payLaterMessageContainer = () =>
		this.page.locator( 'iframe[title^="PayPal Message"]' ).first();

	fastlaneContinueButton = () =>
		this.page
			.locator( '.wc-block-axo-email-submit-button-container' )
			.getByRole( 'button', { name: 'Continue' } );
	fastlaneContactContainer = () =>
		this.page.locator( '.wc-block-components-address-form__email', {
			has: this.fastlaneContinueButton(),
		} );
	fastlaneEmailInput = () =>
		this.fastlaneContactContainer().getByLabel( 'Email address' );
	fastlaneGateway = () =>
		this.page.locator(
			'#radio-control-wc-payment-method-options-ppcp-axo-gateway'
		);
	fastlaneCardNumberInput = () =>
		this.page
			.frameLocator( '#braintree-hosted-field-number' )
			.locator( '#credit-card-number' );
	fastlaneExpirationDateInput = () =>
		this.page
			.frameLocator( '#braintree-hosted-field-expirationDate' )
			.locator( '#expiration' );
	fastlaneCvvInput = () =>
		this.page
			.frameLocator( '#braintree-hosted-field-cvv' )
			.locator( '#cvv' );
	fastlaneCardHolderInput = () =>
		this.page
			.frameLocator( '#braintree-hosted-field-cardholderName' )
			.locator( '#cardholder-name' );
	fastlaneOtpWindow = () =>
		this.page.getByTestId( 'modal-sheet-inner-sheet' );
	fastlaneOtp0Input = () => this.page.locator( 'input[name="otp0"]' );
	fastlaneOtp1Input = () => this.page.locator( 'input[name="otp1"]' );
	fastlaneOtp2Input = () => this.page.locator( 'input[name="otp2"]' );
	fastlaneOtp3Input = () => this.page.locator( 'input[name="otp3"]' );
	fastlaneOtp4Input = () => this.page.locator( 'input[name="otp4"]' );
	fastlaneOtp5Input = () => this.page.locator( 'input[name="otp5"]' );

	acdcGateway = () =>
		this.page.locator(
			'#radio-control-wc-payment-method-options-ppcp-credit-card-gateway__label'
		);
	oxxoGateway = () =>
		this.page.locator(
			'#radio-control-wc-payment-method-options-ppcp-oxxo-gateway__label'
		);
	acdcContainer = () =>
		this.paymentOptionsContainers().filter( {
			has: this.acdcGateway(),
		} );
	acdcCardholderNameInput = () =>
		sdkVersion() === 'v5'
			? this.acdcContainer()
					.frameLocator(
						'[id^="zoid-paypal-card-name-field"] iframe[name^="__zoid__paypal_card_name_field__"]'
					)
					.locator( 'input.card-field-name' )
			: this.acdcContainer().locator(
					'.ppcp-sdk-v6-card-field--name input'
			  );
	acdcCardNumberInput = () =>
		sdkVersion() === 'v5'
			? this.acdcContainer()
					.frameLocator(
						'[id^="zoid-paypal-card-number-field"] iframe[name^="__zoid__paypal_card_number_field__"]'
					)
					.locator( 'input.card-field-number' )
			: this.acdcContainer()
					.locator( '.ppcp-sdk-v6-card-field--number' )
					.frameLocator( 'iframe[title="Number PayPal Card Field"]' )
					.locator( 'input' );
	acdcCardExpirationInput = () =>
		sdkVersion() === 'v5'
			? this.acdcContainer()
					.frameLocator(
						'[id^="zoid-paypal-card-expiry-field"] iframe[name^="__zoid__paypal_card_expiry_field__"]'
					)
					.locator( 'input.card-field-expiry' )
			: this.acdcContainer()
					.locator( '.ppcp-sdk-v6-card-field--expiry' )
					.frameLocator( 'iframe[title="Expiry PayPal Card Field"]' )
					.locator( 'input' );
	acdcCardCvvInput = () =>
		sdkVersion() === 'v5'
			? this.acdcContainer()
					.frameLocator(
						'[id^="zoid-paypal-card-cvv-field"] iframe[name^="__zoid__paypal_card_cvv_field__"]'
					)
					.locator( 'input.card-field-cvv' )
			: this.acdcContainer()
					.locator( '.ppcp-sdk-v6-card-field--cvv' )
					.frameLocator( 'iframe[title="Cvv PayPal Card Field"]' )
					.locator( 'input' );
	acdcSaveToAccountCheckbox = () =>
		this.acdcContainer().locator( 'input[type="checkbox"]' );
	acdcSavedCard = ( card: WooCommerce.CreditCard ) =>
		this.paymentOptionsContainers().filter( {
			hasText: `${ card.card_type } ending in ${ getLast4CardDigits(
				card.card_number
			) } (expires ${ card.expiration_date })`,
		} );

	threeDSFrame1 = () =>
		this.page
			.frameLocator( '.paypal-checkout-sandbox-iframe' )
			.frameLocator( '[name^="__zoid__three_domain_secure__"]' );
	threeDSFrame2 = () =>
		this.threeDSFrame1()
			.frameLocator( '#threedsIframeV2' )
			.frameLocator( '[id^="cardinal-stepUpIframe-"]' );
	threeDSAcceptCookiesButton = () =>
		this.threeDSFrame1().locator( '#acceptAllButton' );
	threeDSOtpInput = () =>
		this.threeDSFrame2().locator( 'input[name="challengeDataEntry"]' );
	threeDSSubmitButton = () =>
		this.threeDSFrame2().locator( 'input.primary[type="submit"]' );

	/** Host element with paypal-buttons-label-* and paypal-buttons-layout-* classes (block cart/checkout). */
	payPalButtonsHostElement = () =>
		this.page.locator(
			'#express-payment-method-ppcp-gateway-paypal .paypal-buttons, #express-payment-method-ppcp-gateway-paypal paypal-button'
		);
	
	puiGateway = () =>
		this.page.locator(
			'#radio-control-wc-payment-method-options-ppcp-pay-upon-invoice-gateway__label'
		);
	puiBirthDateInput = () =>
		this.page.locator( '#ppcp-pui-birth-date' );
	puiPhoneInput = () =>
		this.page.locator( '#ppcp-pui-phone' );

	// Page checks — based on the current URL. Pay for order nests under both
	// /checkout/ and /classic-checkout/ (e.g. /checkout/order-pay/123/), so
	// isCheckoutPage/isClassicCheckoutPage explicitly exclude it to stay
	// mutually exclusive with isPayForOrderPage.
	isProductPage = () => this.page.url().includes( '/product/' );
	isCartPage = () => this.page.url().includes( '/cart/' );
	isClassicCartPage = () => this.page.url().includes( '/classic-cart/' );
	isPayForOrderPage = () => this.page.url().includes( '/order-pay/' );
	isCheckoutPage = () =>
		this.page.url().includes( '/checkout/' ) && ! this.isPayForOrderPage();
	isClassicCheckoutPage = () =>
		this.page.url().includes( '/classic-checkout/' ) &&
		! this.isPayForOrderPage();

	// Actions

	/**
	 * Clicks PayPal button to open popup
	 */
	async openPayPalPopup(): Promise< PayPalPopup > {
		const popupPromise = this.page.waitForEvent( 'popup', {
			timeout: 20 * 1000,
		} );
		await expect(
			this.payPalButton(),
			'Assert PayPal button is visible'
		).toBeVisible();
		await this.payPalButton().click();
		// Popup opens directly or PayPal shows "Click to Continue" overlay
		await Promise.race( [
			popupPromise,
			( async () => {
				try {
					const clickToContinue = this.page.getByRole( 'link', {
						name: 'Click to Continue',
					} );
					await clickToContinue.waitFor( {
						state: 'visible',
					} );
					await clickToContinue.click();
				} catch {
					// popup opened directly (normal case)
				}
			} )(),
		] );

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	}

	/**
	 * Clicks Pay Later button to open popup
	 */
	async openPayLaterPopup(): Promise< PayPalPopup > {
		const popupPromise = this.page.waitForEvent( 'popup', {
			timeout: 20_000,
		} );
		await expect(
			this.payLaterButton(),
			'Assert Pay Later button is visible'
		).toBeVisible();
		await this.payLaterButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	}

	/**
	 * Clicks Venmo button to open popup
	 */
	openVenmoPupup = async (): Promise< PayPalPopup > => {
		const popupPromise = this.page.waitForEvent( 'popup', {
			timeout: 20_000,
		} );
		await expect(
			this.venmoButton(),
			'Assert Venmo button is visible'
		).toBeVisible();
		await this.venmoButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalPopup( popup );
	};

	/**
	 * Clicks Google Pay button to open the TEST environment popup
	 */
	openGooglePayPopup = async (): Promise< GooglePayPopup > => {
		const popupPromise = this.page.waitForEvent( 'popup', {
			timeout: 20_000,
		} );
		await expect(
			this.googlePayButton(),
			'Assert Google Pay button is visible'
		).toBeVisible();
		await this.googlePayButton().click();

		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new GooglePayPopup( popup );
	};

	/**
	 * Completes payment on Classic pages with given payment method
	 *
	 * @param data
	 * @param data.payment
	 * @param data.merchant
	 * @param data.customer
	 */
	makePayment = async ( data: {
		payment: Pcp.Payment;
		merchant?: Pcp.Merchant;
		customer?: ShopOrder[ 'customer' ];
	} ) => {
		const { payment, merchant, customer } = data;
		const { gateway, payPalAccount } = payment;
		const { shortcut } = gateway;
		let popup: PayPalPopup;
		// Map to the tested method
		switch ( shortcut ) {
			case 'paypal':
				if ( payment.isVaulted ) {
					// pay with vaulted account
					await this.completePayPalVaultedPayment( payment );
					break;
				}

				popup = await this.openPayPalPopup();
				// pay with given PayPal account
				await popup.completePayPalPayment( payPalAccount );
				// PayPal popap occasionally shows "Try again" error need to assert the hang
				await expect(
					this.page,
					'Assert redirected to order received page after PayPal payment'
				).toHaveURL( /order-received/, { timeout: 30_000 } );
				break;

			case 'paylater':
				// open expected PayPal popup
				popup = await this.openPayLaterPopup();
				await popup.completePayLaterPayment( payPalAccount );
				break;

			case 'venmo':
				popup = await this.openVenmoPupup();
				await popup.completeVenmoPayment();
				break;

			case 'googlepay': {
				const googlePayPopup = await this.openGooglePayPopup();
				await googlePayPopup.completePayment();
				break;
			}

			case 'acdc':
				if ( payment.isVaulted ) {
					await this.completeAcdcVaultedPayment( payment, merchant );
					break;
				}
				if ( gateway.threeDSecure === 'always-3d-secure' ) {
					await this.completeAcdc3dsPayment( payment, merchant );
					break;
				}
				await this.completeAcdcPayment( payment, merchant );
				break;

			case 'oxxo':
				await this.completeOxxoPayment();
				break;

			case 'card':
				await this.completeBcdcPayment( payment.card, customer );
				break;

			case 'pay_upon_invoice':
				await this.completePuiPayment( payment );
				break;

			case 'fastlane':
				await this.completeFastlanePayment( payment );
				break;
		}
	};

	/**
	 * Submits order and waits for page load
	 */
	submitOrder = async () => {
		const button = this.placeOrderButton();
		await button.focus();
		await button.click();
		await this.page.waitForLoadState();
	};

	/**
	 * Corrects Authorization header for PayPal which is messed up with Basic Auth.
	 * In the following request Playwright replaces Auth header with Basic Auth from .env,
	 * But the header should be from PayPal. Here it's replaced explicitly:
	 *
	 * @param merchant
	 */
	replacePayPalAuthToken = async ( merchant: Pcp.Merchant ) => {
		await this.page.route(
			'https://www.sandbox.paypal.com/v2/checkout/orders/**/*',
			async ( route ) => {
				const token = await this.payPalApi.getAuthToken( merchant );
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
	 * Completes payment with vaulted PayPal account
	 */
	async completePayPalVaultedPayment( payment: Pcp.Payment ) {
		// On block checkout
		if ( this.isCheckoutPage() ) {
			await this.assertVaultedPaymentMethodIsDisplayed( payment );
			if ( payment.isFreeTrialSubscription ) {
				// Free trial cart: no vault component is rendered, just the plain saved-token radio.
				await this.payPalVaultedGateway().click();
				await this.submitOrder();
				return;
			}
			await this.payPalVaultComponent().click();
			await this.submitOrder();
			const sandboxPage = new PayPalPopup( this.page );
			const { payPalAccount } = payment;
			await Promise.race( [
				sandboxPage.login( payPalAccount.email, payPalAccount.password ),
				sandboxPage.submitPaymentButton().click()
			] );
			await this.page.waitForLoadState();
			return;
		}
		// On block cart
		const popup = await this.openPayPalPopup();
		await expect(
			popup.submitPaymentButton(),
			'Assert submit payment button is visible'
		).toBeVisible();
		await popup.completePayment();
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
		const { card, saveToAccount } = payment;
		await expect(
			this.acdcGateway(),
			'Assert ACDC gateway is visible'
		).toBeVisible();
		await this.acdcGateway().click();

		// On block checkout the Cardholder Name input is present
		// Needed to assert payment via PayPal API
		// await this.acdcCardholderNameInput().fill( card.card_holder );

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
		// trick to properly fill expiration date input
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
	 * Completes payment with ACDC 3D-Secure (vaulting disabled)
	 *
	 * @param payment
	 * @param merchant
	 */
	completeAcdc3dsPayment = async (
		payment: Pcp.Payment,
		merchant: Pcp.Merchant
	) => {
		await this.completeAcdcPayment( payment, merchant );
		// TODO: report misbehavior
		// PayPal change: Manual 3DS input is not required any more
		// await this.threeDSAcceptCookiesButton().click();
		// await this.threeDSOtpInput().fill( payment.card.code_3ds );
		// await this.threeDSSubmitButton().click();
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
		const savedCardGateway = this.acdcSavedCard( payment.card );
		await expect(
			savedCardGateway,
			'Assert saved ACDC card gateway is visible'
		).toBeVisible();
		await savedCardGateway.click();
		await this.replacePayPalAuthToken( merchant );
		await this.submitOrder();
	};

	/**
	 * Types in Fastlane OPT for Ryan's flow
	 */
	provideFastlaneOtp = async () => {
		await expect(
			this.fastlaneOtpWindow(),
			'Assert Fastlane OTP window is visible'
		).toBeVisible();
		await this.fastlaneOtp0Input().press( '1' );
		await this.fastlaneOtp1Input().press( '1' );
		await this.fastlaneOtp2Input().press( '1' );
		await this.fastlaneOtp3Input().press( '1' );
		await this.fastlaneOtp4Input().press( '1' );
		await this.fastlaneOtp5Input().press( '1' );
		await expect(
			this.fastlaneOtpWindow(),
			'Assert Fastlane OTP window is not visible'
		).not.toBeVisible();
	};

	/**
	 * Completes payment with Fastlane
	 * Guest without saved details
	 *
	 * @param payment
	 */
	completeFastlanePayment = async ( payment: Pcp.Payment ) => {
		// For Ryan the payment details are already populated
		// For Gary's flow it is required to provide address and card details
		if ( payment.fastlaneFlow === 'gary' ) {
			const { card } = payment;
			await expect(
				this.fastlaneGateway(),
				'Assert Fastlane gateway is visible'
			).toBeVisible();
			await this.fastlaneGateway().click();

			// Wait for Braintree hosted field iframes to load
			await expect(
				this.fastlaneCardNumberInput(),
				'Wait for Braintree card form'
			).toBeVisible();
			await this.fastlaneCardNumberInput().fill( card.card_number );

			await expect(
				this.fastlaneExpirationDateInput(),
				'Assert Fastlane expiration date input is visible'
			).toBeVisible();
			await this.fastlaneExpirationDateInput().pressSequentially(
				card.expiration_date
			);

			await expect(
				this.fastlaneCvvInput(),
				'Assert Fastlane CVV input is visible'
			).toBeVisible();
			await this.fastlaneCvvInput().fill( card.card_cvv );

			// TODO: clarify Cardholder name presence (bug PCP-4623)
			if ( await this.fastlaneCardHolderInput().isVisible() ) {
				await this.fastlaneCardHolderInput().fill( 'Gary From-USA' );
			}

			await expect( this.placeOrderButton() ).toBeEnabled();
		}
		await this.submitOrder();
	};

	/**
	 * Completes payment with OXXO (vaulting disabled)
	 */
	completeOxxoPayment = async () => {
		await expect(
			this.oxxoGateway(),
			'Assert OXXO gateway is visible'
		).toBeVisible();
		await this.oxxoGateway().click();

		await this.submitOrder();

		const apmHostedCheckout = new ApmHostedCheckout( this.page );
		await apmHostedCheckout.assertUrl();
		await expect(
			apmHostedCheckout.testSuccessfulPaymentButton(),
			'Assert OXXO hosted checkout loaded with payment simulation options'
		).toBeVisible();
		await apmHostedCheckout.testSuccessfulPaymentButton().click();

		await this.page.waitForURL( /order-received/, { timeout: 30_000 } );
		// TODO: get resolution about OXXO voucher popup
		// // Generating the voucher only creates the order (status stays "pending"/"on-hold").
		// // The actual cash payment — and the webhook that flips the order to "processing" —
		// // is simulated separately via the voucher popup on the thank-you page.
		// const seeOxxoVoucherButton = this.page
		// 	.getByRole( 'link', { name: 'See OXXO voucher' } )
		// 	.first();
		// await expect(
		// 	seeOxxoVoucherButton,
		// 	'Assert See OXXO voucher button is visible'
		// ).toBeVisible();
		// const popupPromise = this.page.waitForEvent( 'popup' );
		// await seeOxxoVoucherButton.click();
		// const voucherPopup = new OxxoVoucherPopup( await popupPromise );
		// await voucherPopup.simulate();
	};

	completeBcdcPayment = async ( ...args ) =>
		console.log(
			`TODO: completeBcdcPayment for block pages ${ args.length }`
		);

	/**
	 * Completes payment with Pay upon Invoice (vaulting disabled)
	 *
	 * @param birthDate
	 */
	completePuiPayment = async ( payment: Pcp.Payment ) => {
		const { birthDate, phone } = payment;
		await expect(
			this.puiGateway(),
			'Assert pay upon invoice gateway is visible'
		).toBeVisible();
		await this.puiGateway().click();

		await expect(
			this.puiBirthDateInput(),
			'Assert pay upon invoice birth date input is visible'
		).toBeVisible();
		await this.puiBirthDateInput().click();
		await this.page.keyboard.type( birthDate ); // Trick to properly fill date

		await expect(
			this.puiPhoneInput(),
			'Assert pay upon invoice phone input is visible'
		).toBeVisible();
		await this.puiPhoneInput().fill( phone ); // Trick to properly fill date

		await this.submitOrder();
	};

	/**
	 * Clicks payment gateway to make visible payment form or buttons
	 *
	 * @param payment
	 */
	expandPaymentGateway = async ( payment: Pcp.Payment ) => {
		switch ( payment.gateway.shortcut ) {
			case 'paypal':
				await expect(
					this.payPalGateway(),
					'Assert PayPal gateway is visible'
				).toBeVisible();
				await this.payPalGateway().click( { position: { x: 10, y: 10 } } );
				break;

			case 'acdc':
				await expect(
					this.acdcGateway(),
					'Assert ACDC gateway is visible'
				).toBeVisible();
				await this.acdcGateway().click();
				break;
		}
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
				// On block checkout
				if ( this.isCheckoutPage() ) {
					if ( payment.isFreeTrialSubscription ) {
						// Free trial cart: PayPal doesn't render the vault component
						// (see FreeTrialSubscriptionHelper::is_free_trial_cart()), only
						// the plain saved-token radio.
						await expect(
							this.payPalVaultedGateway(),
							'Assert PayPal vaulted gateway is visible'
						).toBeVisible();
						break;
					}
					await expect(
						this.payPalVaultComponent(),
						'Assert PayPal vault component is visible'
					).toBeVisible();
					break;
				}
				// On block cart
				await expect(
					this.payPalButton(),
					'Assert PayPal button is visible'
				).toBeVisible();
				break;

			case 'acdc':
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
					this.payPalVaultedGateway(),
					'Assert PayPal vaulted gateway is visible'
				).not.toBeVisible();

				await expect(
					this.payPalVaultComponent(),
					'Assert PayPal vault component is visible'
				).not.toBeVisible();
				break;

			case 'acdc':
				await expect(
					this.acdcSavedCard( payment.card ),
					'Assert ACDC saved card is not visible'
				).not.toBeVisible();
				break;
		}
	};

	/**
	 * Asserts Pay Later Messaging iframe is visible. Uses retry-with-reload for SDK-loaded content.
	 * Returns false if not found after retry (caller should test.skip()).
	 */
	assertPayLaterMessageVisibleWithContent = async (): Promise< boolean > =>
		assertIframeWithRetry( this.page, 'iframe[title^="PayPal Message"]' );

	/**
	 * Asserts PayPal buttons block container is visible and contains PayPal payment button.
	 */
	assertPayPalButtonsBlockVisibleWithContent = async () => {
		const container = this.payPalButtonsBlockContainer();
		await expect(
			container,
			'Assert PayPal buttons block container is visible'
		).toBeVisible();
		await expect(
			container.locator( '#express-payment-method-ppcp-gateway-paypal' ),
			'Assert PayPal express payment button is visible'
		).toBeVisible();
	};
}
