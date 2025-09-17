/**
 * External dependencies
 */
import { PayForOrder as PayForOrderBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class PayForOrder extends PayForOrderBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators

	// Actions

	makeOrder = async ( tested, order ) => {
		await this.visit( order.id, order.order_key );
		await this.ppui.makeClassicPayment( {
			merchant: tested.merchant,
			payment: tested.payment,
		} );
	};

	// Assertions
}
