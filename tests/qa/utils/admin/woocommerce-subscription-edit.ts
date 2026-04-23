/**
 * External dependencies
 */
import { WooCommerceSubscriptionEdit as WooCommerceSubscriptionEditBase } from '@inpsyde/playwright-utils/build';

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
}
