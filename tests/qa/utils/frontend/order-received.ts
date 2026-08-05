/**
 * External dependencies
 */
import {
	OrderReceived as OrderReceivedBase,
	expect,
	formatMoney,
} from '@inpsyde/playwright-utils/build';

export class OrderReceived extends OrderReceivedBase {
	// Locators
	seeOXXOVoucherButton_1 = () =>
		this.page.getByRole( 'link', { name: 'See OXXO voucher' } ).first();
	seeOXXOVoucherButton_2 = () =>
		this.page.getByRole( 'link', { name: 'See OXXO voucher' } ).last();
	
	detailsTotal = () =>
		this.detailsRowWithHeader( 'Total:' )
			.locator( 'td' )
			.locator( 'span.woocommerce-Price-amount' )
			.first(); // the td includes 2 'span.woocommerce-Price-amount' without distinction: for total and for tax

	// Actions

	// Assertions

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
			await expect(
				this.seeOXXOVoucherButton_1(),
				'Assert OXXO voucher button 1 is visible'
			).toBeVisible();
			await expect(
				this.seeOXXOVoucherButton_2(),
				'Assert OXXO voucher button 2 is visible'
			).toBeVisible();
		}
	};

	/**
	 * Asserts that received order total is equal to PayPal total
	 * 
	 * @param payPalTotal 
	 * @param currency 
	 */
	assertTotalEqualsPayPalTotal = async (
		payPalTotal: string,
		currency: string = 'EUR',
	) => {
		await expect(
			this.detailsTotal(),
			'Assert received order total is equal to PayPal payment total'
		).toHaveText(
			await formatMoney(
				Number( payPalTotal ),
				currency
			)
		);
	}
}
