/**
 * External dependencies
 */
import { CustomerSubscriptions as CustomerSubscriptionsBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class CustomerSubscriptions extends CustomerSubscriptionsBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators

	// Actions

	// Assertions
}
