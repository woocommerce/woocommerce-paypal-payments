/**
 * External dependencies
 */
import { Page } from '@playwright/test';
import { expect, getLast4CardDigits } from '@inpsyde/playwright-utils/build';
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
			'ul.wc-block-components-express-payment__event-buttons'
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
		this.page.frameLocator( 'iframe[name^="__zoid__paypal_message__"]' );
	payLaterMessageContainer = () =>
		this.payLaterMessageIframe().locator( '.message__container' );
	payLaterMessageTextPart = () =>
		this.payLaterMessageContainer().getByText(
			'Pay in 4 interest-free payments on'
		);

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
		this.page.frameLocator( '#braintree-hosted-field-cvv' ).locator( '#cvv' );
	fastlaneCardHolderInput = () =>
		this.page
			.frameLocator( '#cardholder-name iframe' )
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

	// Actions

	/**
	 * Clicks PayPal button to open popup
	 */
	openPayPalPupup = async (): Promise< PayPalPopup > => {
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
	openPayLaterPupup = async (): Promise< PayPalPopup > => {
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
	openVenmoPupup = async (): Promise< PayPalPopup > => {
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
				// pay with vaulted account
				if ( payment.isVaulted ) {
					// await this.assertVaultedPaymentMethodIsDisplayed( payment );
					popup = await this.openPayPalPupup();
					await expect( popup.submitPaymentButton() ).toBeVisible();
					await popup.completePayment();
					break;
				}
				// open expected PayPal popup
				popup = await this.openPayPalPupup();
				// pay with given PayPal account
				await popup.completePayPalPayment( payPalAccount );
				break;

			case 'paylater':
				// open expected PayPal popup
				popup = await this.openPayLaterPupup();
				await popup.completePayLaterPayment( payPalAccount );
				break;

			case 'venmo':
				popup = await this.openVenmoPupup();
				await popup.completeVenmoPayment();
				break;

			case 'acdc':
				if ( payment.isVaulted ) {
					await this.assertVaultedPaymentMethodIsDisplayed( payment );
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
		await this.placeOrderButton().click();
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
		await expect( this.acdcGateway() ).toBeVisible();
		await this.acdcGateway().click();

		// On block checkout the Cardholder Name input is present
		// Needed to assert payment via PayPal API
		// await this.acdcCardholderNameInput().fill( card.card_holder );

		await this.acdcCardNumberInput().fill( card.card_number );
		// trick to properly fill expiration date input
		await this.acdcCardExpirationInput().click();
		for ( const char of card.expiration_date ) {
			await this.page.keyboard.type( char );
			await this.page.waitForTimeout( 200 );
		}
		await this.acdcCardCvvInput().fill( card.card_cvv );

		if ( saveToAccount ) {
			await this.acdcSaveToAccountCheckbox().check();
		}

		await this.submitOrder();
		await this.replacePayPalAuthToken( merchant );
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
		await expect( savedCardGateway ).toBeVisible();
		await savedCardGateway.click();
		await this.submitOrder();
		await this.replacePayPalAuthToken( merchant );
	};

	/**
	 * Asserts Fastlane input field and button
	 * Inputs fastlane email and clicks Continue
	 *
	 * @param email
	 */
	provideFastlaneEmail = async ( email: string ) => {
		await expect( this.fastlaneEmailInput() ).toBeVisible();
		await expect( this.fastlaneContinueButton() ).toBeVisible();

		await this.fastlaneEmailInput().fill( email );
		await this.fastlaneContinueButton().click();
		await this.page.waitForLoadState( 'networkidle' );
	};

	/**
	 * Types in Fastlane OPT for Ryan's flow
	 */
	provideFastlaneOtp = async () => {
		await expect( this.fastlaneOtpWindow() ).toBeVisible();
		await this.fastlaneOtp0Input().press( '1' );
		await this.fastlaneOtp1Input().press( '1' );
		await this.fastlaneOtp2Input().press( '1' );
		await this.fastlaneOtp3Input().press( '1' );
		await this.fastlaneOtp4Input().press( '1' );
		await this.fastlaneOtp5Input().press( '1' );
		await expect( this.fastlaneOtpWindow() ).not.toBeVisible();
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
			await expect( this.fastlaneGateway() ).toBeVisible();
			await this.fastlaneGateway().click();
			await this.fastlaneCardNumberInput().fill( card.card_number );
			await this.fastlaneExpirationDateInput().pressSequentially(
				card.expiration_date
			);
			await this.fastlaneCvvInput().fill( card.card_cvv );
			// TODO: clarify Cardholder name presence (bug PCP-4623)
			if ( await this.fastlaneCardHolderInput().isVisible() ) {
				await this.fastlaneCardHolderInput().fill( 'Gary From-USA' );
			}
		}
		await this.page.waitForTimeout( 1000 );
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

	// Assertions

	/**
	 * Asserts the saved payment method is visible
	 *
	 * @param payment
	 */
	assertVaultedPaymentMethodIsDisplayed = async ( payment: Pcp.Payment ) => {
		switch ( payment.gateway.shortcut ) {
			case 'paypal':
				// Uncomment when bug is fixed
				// await expect( this.payPalButton() ).toContainText( 'Pay Now' );
				// await expect( this.payPalButtonMoreOptions() ).toBeVisible();
				break;

			case 'acdc':
				await expect(
					this.acdcSavedCard( payment.card )
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
				await expect( this.payPalButton() ).not.toContainText(
					'Pay Now'
				);
				await expect(
					this.payPalButtonMoreOptions()
				).not.toBeVisible();
				break;

			case 'acdc':
				await expect(
					this.acdcSavedCard( payment.card )
				).not.toBeVisible();
				break;
		}
	};

	/**
	 * - Asserts PayPal buttons block container is visible.
	 * - Compares actual PayPal buttons container screenshot to expected.
	 *
	 * @param snapshotName
	 */
	snapshotBlockPayPalButtons = async ( snapshotName: string ) => {
		await expect.soft( this.payPalButtonsBlockContainer() ).toBeVisible();
		await this.page.waitForTimeout( 500 );
		expect
			.soft(
				await this.payPalButtonsBlockContainer().screenshot( {
					animations: 'disabled',
				} )
			)
			.toMatchSnapshot( `${ snapshotName }.png` );
	};
}
