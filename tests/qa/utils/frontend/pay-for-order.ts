/**
 * External dependencies
 */
import { PayForOrder as PayForOrderBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUiClassic } from './paypal-ui-classic';

export class PayForOrder extends PayForOrderBase {
	payPalUi: PayPalUiClassic;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	makeOrder = async ( tested, order ) => {
		await this.visit( order.id, order.order_key );
		await this.payPalUi.makePayment( {
			merchant: tested.merchant,
			payment: tested.payment,
		} );
	};

	// Assertions
}
