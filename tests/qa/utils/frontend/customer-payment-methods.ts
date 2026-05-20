/**
 * External dependencies
 */
import {
	CustomerPaymentMethods as CustomerPaymentMethodsBase,
	expect,
	getLast4CardDigits,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUiClassic } from './paypal-ui-classic';
import { Pcp } from '../../resources';

export class CustomerPaymentMethods extends CustomerPaymentMethodsBase {
	payPalUi: PayPalUiClassic;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators
	paymentMethodDeletedMessage = () =>
		// TODO: remove with playwright-utils version > 2.6.1
		this.page.getByText( 'Payment method deleted.' );
	noSavedMethodsMessage = () =>
		// TODO: remove with playwright-utils version > 2.6.1
		this.page.getByText( 'No saved methods found' );
	addPaymentMethodButton = () =>
		// TODO: remove with playwright-utils version > 2.6.1
		this.page
			.getByRole( 'link', { name: 'Add payment method' } )
			.or(
				this.page.getByRole( 'button', { name: 'Add payment method' } )
			);

	// Actions
	getSavedPaymentMethodText = async ( payment: Pcp.Payment ) => {
		switch ( payment.gateway.shortcut ) {
			case 'paypal':
				const payPalEmail = payment.payPalAccount.email;
				const match = payPalEmail.replace( /.{2}-.{1}/, '' );
				return new RegExp( `Paypal /.*${ match }`, 'i' );

			case 'acdc':
				const { card } = payment;
				const last4Digits = getLast4CardDigits( card.card_number );
				return `${ card.card_type } ending in ${ last4Digits }`;
		}
	};

	isSavedPaymentMethod = async ( payment: Pcp.Payment ) => {
		if ( await this.noSavedMethodsMessage().isVisible() ) {
			return false;
		}
		const paymentMethodText = await this.getSavedPaymentMethodText(
			payment
		);
		return await this.savedPaymentMethodRow(
			paymentMethodText
		).isVisible();
	};

	/**
	 * Adds payment method on My Account/Payment Methods page
	 *
	 * @param payment
	 */
	savePaymentMethod = async ( payment: Pcp.Payment ) => {
		const { gateway, card, payPalAccount } = payment;

		const addPaymentMethodButton = this.addPaymentMethodButton();
		await expect( addPaymentMethodButton, 'Assert add payment method button is visible' ).toBeVisible();
		await addPaymentMethodButton.click();
		await this.page.waitForLoadState();

		switch ( gateway.shortcut ) {
			case 'paypal':
				const popup = await this.payPalUi.openPayPalPopup();
				await popup.completePayPalPayment( payPalAccount );
				break;

			case 'acdc':
				await this.payPalUi.acdcGateway().click();
				await this.payPalUi
					.acdcCardNumberInput()
					.fill( card.card_number );
				// trick to properly fill expiration date input
				await this.payPalUi.acdcCardExpirationInput().click();
				for ( const char of card.expiration_date ) {
					await this.page.keyboard.type( char );
					await this.page.waitForTimeout( 200 );
				}
				await this.payPalUi.acdcCardCvvInput().fill( card.card_cvv );
				await this.page.waitForTimeout( 200 );
				await this.addPaymentMethodButton().click();
				break;
		}
		await this.page.waitForURL( this.url );
		await this.assertIsSavedPaymentMethod( payment );
	};

	// Assertions

	/**
	 * Asserts payment method row is visible
	 *
	 * @param payment
	 */
	assertIsSavedPaymentMethod = async ( payment: Pcp.Payment ) => {
		const paymentMethodText = await this.getSavedPaymentMethodText(
			payment
		);
		await expect(
			this.savedPaymentMethodRow( paymentMethodText ),
			`Assert payment method with text ${ paymentMethodText } is visible`
		).toBeVisible();
	};

	/**
	 * Asserts payment method row is not visible
	 *
	 * @param payment
	 */
	assertIsNotSavedPaymentMethod = async ( payment: Pcp.Payment ) => {
		const paymentMethodText = await this.getSavedPaymentMethodText(
			payment
		);
		await expect(
			this.savedPaymentMethodRow( paymentMethodText ),
			`Assert payment method with text ${ paymentMethodText } is not visible`
		).not.toBeVisible();
	};
}
