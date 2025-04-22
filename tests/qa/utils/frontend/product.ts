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
import { PayPalUiClassic } from './paypal-ui-classic';

export class Product extends ProductBase {
	payPalUi: PayPalUiClassic;

	constructor( { page, payPalUi } ) {
		super( { page } );
		this.payPalUi = payPalUi;
	}

	// Locators

	// Actions

	addToCart = async ( productSlug: string ) => {
		await this.visit( productSlug );
		await this.addToCartButton().click();
	};

	makeOrder = async ( data: WooCommerce.ShopOrder ) => {
		await this.visit( data.products[ 0 ].slug );
		await this.payPalUi.makePayment( {
			merchant: data.merchant,
			payment: data.payment,
		} );
	};

	// Assertions
}
