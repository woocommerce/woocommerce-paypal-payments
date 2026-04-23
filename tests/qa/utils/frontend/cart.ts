/**
 * External dependencies
 */
import { Cart as CartBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUi } from './paypal-ui';

export class Cart extends CartBase {
	payPalUi: PayPalUi;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	// Assertions
}
