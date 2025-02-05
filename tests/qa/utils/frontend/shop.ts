/**
 * External dependencies
 */
import { Shop as ShopBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class Shop extends ShopBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators

	// Actions

	// Assertions
}
