/**
 * External dependencies
 */
import {
	OrderReceived as OrderReceivedBase,
	expect,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalUI } from './paypal-ui';

export class OrderReceived extends OrderReceivedBase {
	ppui: PayPalUI;

	constructor( { page, ppui } ) {
		super( { page } );
		this.ppui = ppui;
	}

	// Locators
	seeOXXOVoucherButton_1 = () =>
		this.page.getByRole( 'link', { name: 'See OXXO voucher' } ).first();
	seeOXXOVoucherButton_2 = () =>
		this.page.getByRole( 'link', { name: 'See OXXO voucher' } ).last();

	// Actions

	/**
	 * Asserts that
	 * - Order Received heading is visible
	 * - Expected payment method is displayed
	 * - Other optional payment details
	 *
	 * @param order
	 */
	assertOrderDetails = async ( order: WooCommerce.ShopOrder ) => {
		await super.assertOrderDetails( order );

		if ( order.payment.dataFundingSource === 'oxxo' ) {
			await expect( this.seeOXXOVoucherButton_1() ).toBeVisible();
			await expect( this.seeOXXOVoucherButton_2() ).toBeVisible();
		}
	};

	// Assertions
}
