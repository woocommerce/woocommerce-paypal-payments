/**
 * External dependencies
 */
import { expect, Product as ProductBase } from '@inpsyde/playwright-utils/build';
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
	variationsTable = () => this.page.locator( 'table.variations' );
	variationAttributeSelect = ( attributeName: string ) =>
		this.variationsTable()
			.locator( `select[name="attribute_${ attributeName }"]` );

	// Actions
	selectVariation = async ( variationAttributes: Record< string, string > ) => {
		for ( const [ attribute, value ] of Object.entries( variationAttributes ) ) {
			await expect(
				this.variationAttributeSelect( attribute ),
				`Assert variation attribute ${ attribute } select is visible`,
			).toBeVisible();

			await this.variationAttributeSelect( attribute )
				.selectOption( { label: value } );
		}
	};

	// Assertions
}
