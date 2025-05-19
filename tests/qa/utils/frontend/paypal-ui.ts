/**
 * External dependencies
 */
import { Page } from '@playwright/test';
import { expect } from '@inpsyde/playwright-utils/build';
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
			.locator( '.wc-block-components-radio-control-accordion-option' );

	placeOrderButton = () =>
		this.page.getByRole( 'button', { name: 'Place order' } );
	payForOrderButton = () =>
		this.page.getByRole( 'button', { name: 'Pay for order' } );

	payPalButtonsBlockContainer = () =>
		this.page.locator(
			'ul.wc-block-components-express-payment__event-buttons'
		);
	blockSmartButtonListItem = () =>
		this.payPalButtonsBlockContainer().locator(
			'li[id^="express-payment-method-"]'
		);
	payPalButton = () =>
		this.page
			.frameLocator(
				'#express-payment-method-ppcp-gateway-paypal .component-frame'
			)
			.locator( `[data-funding-source="paypal"]` );
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

	payPalButtonMoreOptions = () => this.page.locator( '.todo' );
	payWithDifferentAccountButton = () => this.page.locator( '.todo' );

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
			.frameLocator( '#card-number iframe' )
			.locator( '#credit-card-number' );
	fastlaneExpirationDateInput = () =>
		this.page
			.frameLocator( '#expiration-date iframe' )
			.locator( '#expiration' );
	fastlaneCvvInput = () =>
		this.page.frameLocator( '#cvv iframe' ).locator( '#cvv' );
	fastlaneCardHolderInput = () =>
		this.page
			.frameLocator( '#cardholder-name iframe' )
			.locator( '#cardholder-name' );
	fastlaneOtpWindow = () =>
		this.page.getByTestId( 'modal-sheet-inner-sheet' );
	fastlaneOtp0Input = () => this.page.locator( '#otp0-input' );
	fastlaneOtp1Input = () => this.page.locator( '#otp1-input' );
	fastlaneOtp2Input = () => this.page.locator( '#otp2-input' );
	fastlaneOtp3Input = () => this.page.locator( '#otp3-input' );
	fastlaneOtp4Input = () => this.page.locator( '#otp4-input' );
	fastlaneOtp5Input = () => this.page.locator( '#otp5-input' );
	
	acdcGateway = () => this.page.locator(
		'#radio-control-wc-payment-method-options-ppcp-credit-card-gateway'
	);
	acdcContainer = () => this.paymentOptionsContainers().filter( {
		has: this.acdcGateway()
	} );
	acdcCardholderNameInput = () =>
		this.acdcContainer()
			.frameLocator( '[id^="zoid-paypal-card-name-field"] iframe[name^="__zoid__paypal_card_name_field__"]' )
			.locator( 'input.card-field-name' );
	acdcCardNumberInput = () =>
		this.acdcContainer()
			.frameLocator( '[id^="zoid-paypal-card-number-field"] iframe[name^="__zoid__paypal_card_number_field__"]' )
			.locator( 'input.card-field-number' );
	acdcCardExpirationInput = () =>
		this.acdcContainer()
			.frameLocator( '[id^="zoid-paypal-card-expiry-field"] iframe[name^="__zoid__paypal_card_expiry_field__"]' )
			.locator( 'input.card-field-expiry' );
	acdcCardCvvInput = () =>
		this.acdcContainer()
			.frameLocator( '[id^="zoid-paypal-card-cvv-field"] iframe[name^="__zoid__paypal_card_cvv_field__"]' )
			.locator( 'input.card-field-cvv' );

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
	 * Opens PayPal popup per given funding source options
	 *
	 * @param payment
	 */
	openPayPalGatewayPupup = async ( payment: Pcp.Payment ) => {
		const { gateway } = payment;
		const { shortcut } = gateway;

		const popupPromise = this.page.waitForEvent( 'popup' );

		switch ( shortcut ) {
			case 'paypal':
				// pay with vaulted account (vaulting enabled)
				if ( payment.isVaulted ) {
					await expect( this.payPalButton() ).toBeVisible();
					await this.payPalButton().click();
					break;
				}
				// pay with account other than vaulted (vaulting enabled)
				if ( payment.useNotVaultedAccount ) {
					await expect(
						this.payPalButtonMoreOptions()
					).toBeVisible();
					await this.payPalButtonMoreOptions().click();

					await expect(
						this.payWithDifferentAccountButton()
					).toBeVisible();
					await this.payWithDifferentAccountButton().click();
					break;
				}
				// pay with PayPal button (vaulting disabled)
				await expect( this.payPalButton() ).toBeVisible();
				await this.payPalButton().click();
				break;

			case 'paylater':
				await expect( this.payLaterButton() ).toBeVisible();
				await this.payLaterButton().click();
				break;

			case 'venmo':
				await expect( this.venmoButton() ).toBeVisible();
				await this.venmoButton().click();
				break;
		}

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
				// open expected PayPal popup
				popup = await this.openPayPalGatewayPupup( payment );
				// pay with vaulted account
				if ( payment.isVaulted ) {
					await popup.completePayPalVaultedPayment();
					break;
				}
				// pay with PayPal (vaulting disabled)
				// or account other than vaulted (vaulting enabled)
				await popup.completePayPalPayment( payPalAccount );
				break;

			case 'paylater':
				// open expected PayPal popup
				popup = await this.openPayPalGatewayPupup( payment );
				await popup.completePayLaterPayment( payPalAccount );
				break;

			case 'venmo':
				popup = await this.openPayPalGatewayPupup( payment );
				await popup.completeVenmoPayment();
				break;

			case 'acdc':
				if ( gateway.threeDSecure === 'always-3d-secure' ) {
					await this.completeAcdc3dsPayment( payment, merchant );
					break;
				}
				if ( payment.isVaulted ) {
					await this.completeAcdcVaultedPayment( payment, merchant );
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
	 * Adds payment method on My Account/Payment Methods page
	 *
	 * @param payment
	 */
	savePaymentMethod = async ( payment: Pcp.Payment ) => {
		const { gateway, payPalAccount } = payment;
		const { shortcut } = gateway;
		switch ( shortcut ) {
			case 'paypal':
				const popup = await this.openPayPalGatewayPupup( payment );
				await popup.savePayPalPaymentMethod( payPalAccount );
				break;

			case 'acdc':
				await this.addCardPaymentMethod( payment );
				break;
		}
	};

	/**
	 * Clicks "Place Order" or "Pay for order" button depending on the page URL
	 */
	submitOrder = async () => {
		// on Pay for Order page the button name is Pay for order
		if ( this.page.url().includes( 'pay_for_order' ) ) {
			await this.payForOrderButton().click();
		} else {
			await this.placeOrderButton().click();
		}
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
	 * Completes payment with ACDC (vaulting disabled)
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

		// TODO: implement tests
		// //if some cards are already stored then "Use a new payment method" radio should be checked
		// if ( await this.acdcUseNewPaymentRadio().isVisible() ) {
		// 	await this.acdcUseNewPaymentRadio().check();
		// }

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

		// TODO: implement tests
		// if ( saveToAccount ) {
		// 	await this.acdcSaveToAccountCheckbox().check();
		// }

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

	completeAcdcVaultedPayment = async ( ...args ) =>
		console.log( `TODO: completeAcdc3dsPayment for block pages` );

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

	addCardPaymentMethod = async ( ...args ) =>
		console.log( `TODO: addCardPaymentMethod for block pages` );

	// Assertions

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
