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
import { PayPalAccount, Pcp } from '../../resources';
import { PayPalPopup } from './paypal-popup';
import { PayPalApi } from '../paypal-api';

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
			.or( this.page.getByRole( 'button', { name: 'Sign up now' } ) );

	payPalButtonsBlockContainer = () =>
		this.page.locator(
			'.wc-block-components-express-payment__event-buttons'
		);
	blockSmartButtonListItem = () =>
		this.payPalButtonsBlockContainer().locator(
			'li[id^="express-payment-method-"]'
		);
	payPalIframe = () =>
		this.page.frameLocator(
			// unified selector for My Account and checkout pages
			'#express-payment-method-ppcp-gateway-paypal .component-frame'
		);
	payPalButton = () =>
		this.payPalIframe().locator( `[data-funding-source="paypal"]` );
	payLaterButton = () =>
		this.page
			.frameLocator(
				'#express-payment-method-ppcp-gateway-paylater .component-frame'
			)
			.locator( `[data-funding-source="paylater"]` );
	venmoButton = () =>
		this.page
			.frameLocator(
				'#express-payment-method-ppcp-gateway-venmo .component-frame'
			)
			.locator( `[data-funding-source="venmo"]` );

	payPalGateway = () =>
		this.page.locator(
			'#radio-control-wc-payment-method-options-ppcp-gateway__label'
		);
	payPalButtonMoreOptions = () =>
		this.payPalIframe().locator( '[aria-label="More options"]' );
	payPalVaultedGateway = () =>
		this.paymentOptionsContainers().filter( {
			hasText: 'Saved token for ppcp-gateway',
		} );

	payLaterMessageIframe = () =>
		this.page.frameLocator( 'iframe[title^="PayPal Message"]' );
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
	acdcContainer = () =>
		this.paymentOptionsContainers().filter( {
			has: this.acdcGateway(),
		} );
	acdcCardholderNameInput = () =>
		this.acdcContainer()
			.frameLocator(
				'[id^="zoid-paypal-card-name-field"] iframe[name^="__zoid__paypal_card_name_field__"]'
			)
			.locator( 'input.card-field-name' );
	acdcCardNumberInput = () =>
		this.acdcContainer()
			.frameLocator(
				'[id^="zoid-paypal-card-number-field"] iframe[name^="__zoid__paypal_card_number_field__"]'
			)
			.locator( 'input.card-field-number' );
	acdcCardExpirationInput = () =>
		this.acdcContainer()
			.frameLocator(
				'[id^="zoid-paypal-card-expiry-field"] iframe[name^="__zoid__paypal_card_expiry_field__"]'
			)
			.locator( 'input.card-field-expiry' );
	acdcCardCvvInput = () =>
		this.acdcContainer()
			.frameLocator(
				'[id^="zoid-paypal-card-cvv-field"] iframe[name^="__zoid__paypal_card_cvv_field__"]'
			)
			.locator( 'input.card-field-cvv' );
	acdcSaveToAccountCheckbox = () => this.page.locator( '#save' );
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
			'#express-payment-method-ppcp-gateway-paypal .paypal-buttons'
		);

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
			timeout: 20 * 1000,
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
			timeout: 20 * 1000,
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
	 * Completes payment on Classic pages with given payment method
	 *
	 * @param data
	 * @param data.payment
	 * @param data.merchant
	 */
	makePayment = async ( data: {
		payment: Pcp.Payment;
		merchant?: Pcp.Merchant;
	} ) => {
		const { payment, merchant } = data;
		const { gateway, payPalAccount } = payment;
		const { shortcut } = gateway;
		let popup: PayPalPopup;
		// Map to the tested method
		switch ( shortcut ) {
			case 'paypal':
				popup = await this.openPayPalPopup();

				if ( payment.isVaulted ) {
					// pay with vaulted account
					await expect(
						popup.submitPaymentButton(),
						'Assert submit payment button is visible'
					).toBeVisible();
					await popup.completePayment();
					break;
				}

				// pay with given PayPal account
				await popup.completePayPalPayment( payPalAccount );
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
				await this.completeOXXOPayment();
				break;

			case 'card':
				// Standard Card Button
				if ( gateway.id === 'ppcp-card-button-gateway' ) {
					await this.completeStandardCardButtonPayment(
						payment.card
					);
					break;
				}
				// Debit Or Credit Card
				await this.completeDebitOrCreditCardPayment( payment.card );
				break;

			case 'pay_upon_invoice':
				await this.completePayUponInvoicePayment( payment.birthDate );
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
				const token = await this.payPalApi.getToken( merchant );
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

	completeOXXOPayment = async ( ...args ) =>
		console.log( `TODO: completeOXXOPayment for block pages` );

	completeStandardCardButtonPayment = async ( ...args ) =>
		console.log(
			`TODO: completeStandardCardButtonPayment for block pages`
		);

	completeDebitOrCreditCardPayment = async ( ...args ) =>
		console.log( `TODO: completeDebitOrCreditCardPayment for block pages` );

	completePayUponInvoicePayment = async ( ...args ) =>
		console.log( `TODO: completePayUponInvoicePayment for block pages` );

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
				await this.payPalGateway().click();
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
				// Only check the wallet dropdown trigger — it's visible iff wallet mode is active.
				// Checking a class on payPalButton() is fragile when the SDK renders in a
				// different structure (e.g. after a prior PayPal popup in the same browser context).
				await expect
					.soft( this.payPalButtonMoreOptions() )
					.not.toBeVisible();
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
	assertPayLaterMessageVisibleWithContent = async (): Promise<boolean> =>
		assertIframeWithRetry(
			this.page,
			'iframe[title^="PayPal Message"]',			
		);

	/**
	 * Asserts Pay Later Messaging iframe is not visible.
	 */
	assertPayLaterMessageNotVisible = async () => {
		await expect(
			this.payLaterMessageContainer(),
			'Assert PLM iframe is not visible'
		).toBeHidden();
	};

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

	/**
	 * Asserts PayPal buttons have the given label (pay, checkout, buynow, paypal).
	 */
	assertPayPalButtonsHaveLabel = async (
		label: 'pay' | 'checkout' | 'buynow' | 'paypal'
	) => {
		await expect(
			this.payPalButtonsHostElement(),
			`Assert PayPal buttons have label ${ label }`
		).toHaveClass( new RegExp( `paypal-buttons-label-${ label }` ) );
	};

	/**
	 * Asserts PayPal buttons have the given layout (vertical, horizontal).
	 */
	assertPayPalButtonsHaveLayout = async (
		layout: 'vertical' | 'horizontal'
	) => {
		await expect(
			this.payPalButtonsHostElement(),
			`Assert PayPal buttons have layout ${ layout }`
		).toHaveClass( new RegExp( `paypal-buttons-layout-${ layout }` ) );
	};

	/**
	 * Asserts PayPal buttons are not visible (block cart/checkout).
	 */
	assertPayPalButtonsNotVisible = async () => {
		await expect(
			this.page.locator( '#express-payment-method-ppcp-gateway-paypal' ),
			'Assert PayPal buttons block container is not visible'
		).toBeHidden();
	};
}
