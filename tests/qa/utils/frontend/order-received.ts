/**
 * External dependencies
 */
import {
	OrderReceived as OrderReceivedBase,
	expect,
} from '@inpsyde/playwright-utils/build';

export class OrderReceived extends OrderReceivedBase {
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

		if ( order.payment.gateway.shortcut === 'oxxo' ) {
			await expect( this.seeOXXOVoucherButton_1(), 'Assert OXXO voucher button 1 is visible' ).toBeVisible();
			await expect( this.seeOXXOVoucherButton_2(), 'Assert OXXO voucher button 2 is visible' ).toBeVisible();
		}
	};

	// Assertions
}
