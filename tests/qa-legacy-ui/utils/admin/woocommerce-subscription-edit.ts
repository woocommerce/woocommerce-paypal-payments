/**
 * External dependencies
 */
import {
	WooCommerceSubscriptionEdit as WooCommerceSubscriptionEditBase,
	expect,
} from '@inpsyde/playwright-utils/build';

export class WooCommerceSubscriptionEdit extends WooCommerceSubscriptionEditBase {
	// Locators
	transactionIdKey = () =>
		this.page.locator(
			'input[value="ppcp_previous_transaction_reference"]'
		);
	transactionIdRow = () =>
		this.page.locator( '#the-list tr', { has: this.transactionIdKey() } );
	transactionIdTextarea = () => this.transactionIdRow().getByLabel( 'Value' );

	// Actions

	// Assertions

	/**
	 * Asserts data on subscription page
	 *
	 * @param subscriptionId
	 * @param data
	 */
	assertSubscriptionDetails = async ( subscriptionId, data ) => {
		const statusLabels = {
			active: 'Active',
		};
		const status = statusLabels[ data.subscription.status ];

		await this.visit( subscriptionId );
		await expect( this.customerCombobox() ).toContainText(
			data.customer.email
		);
		await expect( this.statusCombobox() ).toHaveText( status );
	};
}
