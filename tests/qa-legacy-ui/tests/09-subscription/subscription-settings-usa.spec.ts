/**
 * Internal dependencies
 */
import { test, expect, annotateVisitor } from '../../utils';
import {
	pcpConfigUsa,
	storeConfigSubscriptionUsa,
	products,
} from '../../resources';

test.describe( 'Subscription > Standard Payments', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( storeConfigSubscriptionUsa );
		await utils.configurePcp( pcpConfigUsa );
	} );

	test(
		'PCP-1774 | Subscription - Standard Payments - PayPal Vaulting in Subscription Mode @Critical',
		annotateVisitor( storeConfigSubscriptionUsa.customer ),
		async ( {
			utils,
			standardPayments,
			product,
			classicCart,
			classicCheckout,
		} ) => {
			const testProduct = products.subscription10;
			await utils.fillVisitorsCart( [ testProduct ] );

			await standardPayments.visit();
			await expect(
				standardPayments.subscriptionModeTextBox()
			).toBeVisible();
			await standardPayments.subscriptionModeTextBox().click();
			await standardPayments
				.dropdownOption( 'PayPal Subscriptions' )
				.click();
			await standardPayments.subscriptionModeTextBox().click();
			await standardPayments.dropdownOption( 'PayPal Vaulting' ).click();
			await standardPayments.saveChanges();

			await expect(
				standardPayments.subscriptionModeTextBox()
			).toHaveText( 'PayPal Vaulting' );
			await expect( standardPayments.vaultingCheckbox() ).toBeChecked();

			await product.visit( testProduct.slug );
			await product.ppui.assertPayPalButtonVisibility( true );

			await classicCart.visit();
			await classicCart.ppui.assertPayPalButtonVisibility( true );

			await classicCheckout.visit();
			await classicCheckout.ppui.assertPayPalButtonVisibility( true );
		}
	);
} );
