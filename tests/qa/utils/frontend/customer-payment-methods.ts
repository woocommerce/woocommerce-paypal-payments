/**
 * External dependencies
 */
import { CustomerPaymentMethods as CustomerPaymentMethodsBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';
import { PcpPayment } from '../../resources';

export class CustomerPaymentMethods extends CustomerPaymentMethodsBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators
	noSavedMethodsMessage = () =>
		this.page.getByText( 'No saved methods found' );

	// Actions
	isSavedPaymentMethod = async ( payment: PcpPayment ) => {
		await this.visit();

		if ( await this.noSavedMethodsMessage().isVisible() ) {
			return false;
		}

		switch ( payment.dataFundingSource ) {
			case 'paypal':
				return await this.savedPaymentMethodRow(
					`Paypal /`
				).isVisible();

			case 'card':
				return await this.savedPaymentMethodRow(
					payment.card?.card_number
				).isVisible();
		}
	};

	/**
	 * Adds payment method on My Account/Payment Methods page
	 *
	 * @param payment
	 */
	savePaymentMethod = async ( payment: PcpPayment ) => {
		if ( ! ( await this.isSavedPaymentMethod( payment ) ) ) {
			await this.addPaymentMethodButton().click();
			await this.page.waitForLoadState();
			await this.ppui.savePaymentMethod( payment );
		}
	};

	// Assertions
}
