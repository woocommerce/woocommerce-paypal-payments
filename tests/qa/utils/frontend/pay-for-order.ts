/**
 * External dependencies
 */
import { PayForOrder as PayForOrderBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUiClassic } from './paypal-ui-classic';

export class PayForOrder extends PayForOrderBase {
	// UI is the same as when classic pages are enabled
	// Only difference is in page URL: contains /checkout/ instead of /classic-checkout/
	payPalUi: PayPalUiClassic;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	// Assertions
}
