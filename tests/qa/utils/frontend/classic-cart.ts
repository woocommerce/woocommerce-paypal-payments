/**
 * External dependencies
 */
import { ClassicCart as ClassicCartBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUiClassic } from './paypal-ui-classic';

export class ClassicCart extends ClassicCartBase {
	payPalUi: PayPalUiClassic;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	// Assertions
}
