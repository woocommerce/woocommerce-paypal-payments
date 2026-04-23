/**
 * External dependencies
 */
import { CustomerAccount as CustomerAccountBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class CustomerAccount extends CustomerAccountBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators

	// Actions

	// Assertions
}
