/**
 * External dependencies
 */
import {
	Product as ProductBase,
	expect,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class Product extends ProductBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators

	// Actions

	addToCart = async ( productSlug: string ) => {
		await this.visit( productSlug );
		await this.addToCartButton().click();
	};

	makeOrder = async ( tested ) => {
		await this.visit( tested.products[ 0 ].slug );
		await this.ppui.makeClassicPayment( {
			merchant: tested.merchant,
			payment: tested.payment,
		} );
	};

	// Assertions
}
