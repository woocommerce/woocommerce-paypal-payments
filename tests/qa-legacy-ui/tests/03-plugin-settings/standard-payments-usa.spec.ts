/**
 * Internal dependencies
 */
import { test, expect, annotateVisitor } from '../../utils';
import {
	customers,
	products,
	pcpConfigUsa,
	storeConfigUsa,
} from '../../resources';

test.describe( 'Standard Payments tab - Google/Venmo payments', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( storeConfigUsa );
		await utils.configurePcp( pcpConfigUsa );
	} );

	//login to google account need to be implemented
	// test.fixme(
	// 	'PCP-2031 | Standard Payments - USA - Google Pay enabled',
	// 	async ( { standardPayments } ) => {
	// 		const buttonsPreviewLocators = [
	// 			standardPayments.classicCheckoutGooglePayButton(),
	// 			standardPayments.singleProductGooglePayButton(),
	// 			standardPayments.classicCartGooglePayButton(),
	// 			standardPayments.miniCartGooglePayButton(),
	// 			standardPayments.expressCartGooglePayButton(),
	// 			standardPayments.expressCheckoutGooglePayButton(),
	// 		];

	// 		await standardPayments.visit();
	// 		await expect(
	// 			standardPayments.googlePayButtonColorCombobox()
	// 		).not.toBeVisible();
	// 		await expect(
	// 			standardPayments.googlePayButtonLabelCombobox()
	// 		).not.toBeVisible();
	// 		await expect(
	// 			standardPayments.googlePayButtonLanguageCombobox()
	// 		).not.toBeVisible();
	// 		await expect(
	// 			standardPayments.googlePayShippingCallbackCheckbox()
	// 		).not.toBeVisible();

	// 		await standardPayments.googlePayButtonCheckbox().check();
	// 		await standardPayments.saveChanges();

	// 		await expect(
	// 			standardPayments.googlePayButtonColorCombobox()
	// 		).toBeVisible();
	// 		await expect(
	// 			standardPayments.googlePayButtonLabelCombobox()
	// 		).toBeVisible();
	// 		await expect(
	// 			standardPayments.googlePayButtonLanguageCombobox()
	// 		).toBeVisible();
	// 		await expect(
	// 			standardPayments.googlePayShippingCallbackCheckbox()
	// 		).toBeVisible();

	// 		for ( const tested of buttonsPreviewLocators ) {
	// 			await expect( tested ).toBeVisible();
	// 		}
	// 	}
	// );

	test(
		'PCP-2379 | Standard Payments - Disabling Venmo - Block cart page',
		annotateVisitor( customers.usa ),
		async ( { utils, standardPayments, cart } ) => {
			await utils.fillVisitorsCart( [ products.simple10 ] );

			await standardPayments.visit();
			await standardPayments
				.customizeSmartButtonsPerLocationCheckbox()
				.check();
			await expect(
				standardPayments.expressCartFundingSourceButton( 'venmo' )
			).toBeVisible();

			await cart.visit();
			await expect( cart.ppui.blockVenmoButton() ).toBeVisible();

			//add Venmo button to 'Disable Alternative Payment Methods' select box and check results
			await standardPayments.addItemsToSelectBox(
				'Disable Alternative Payment Methods',
				'Venmo'
			);
			await standardPayments.saveChanges();
			await expect(
				standardPayments.expressCartFundingSourceButton( 'venmo' )
			).not.toBeVisible();

			await cart.page.reload();
			await expect( cart.ppui.blockVenmoButton() ).not.toBeVisible();
		}
	);
} );
