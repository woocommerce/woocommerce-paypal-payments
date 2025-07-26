/**
 * Internal dependencies
 */
import { annotateVisitor, expect, test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	customers,
	payments,
	cards,
	products,
} from '../../resources';

const customer = customers.usa;
const { payPal, acdc } = payments;
const acdc2 = { ...acdc, card: cards.visa2 };

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret,
		{
			isCasualSeller: false,
			areOptionalPaymentMethodsEnabled: true,
		}
	);
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
} );

test.afterAll( async ( { wooCommerceApi } ) => {
	await wooCommerceApi.deleteAllOrders();
} );

const savePaymentMethodData = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-4499
		testKey: 'PCP-4499',
		payment: payPal,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-4500
		testKey: 'PCP-4500',
		payment: acdc,
	},
];

for ( const testData of savePaymentMethodData ) {
	const { testKey, payment } = testData;
	test.describe( () => {
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			`${ testKey } | Vaulting - My Account - Payment Methods - ${ payment.gateway.title } - Save payment method`,
			annotateVisitor( customer ),
			async ( { utils, customerPaymentMethods, classicCheckout } ) => {
				await customerPaymentMethods.visit();
				await expect(
					customerPaymentMethods.noSavedMethodsMessage()
				).toBeVisible();

				await customerPaymentMethods.savePaymentMethod( payment );

				await customerPaymentMethods.assertUrl();
				await customerPaymentMethods.assertIsSavedPaymentMethod(
					payment
				);

				await utils.fillVisitorsCart( [ products.simple10 ] );
				await classicCheckout.visit();
				await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
					payment
				);
			}
		);
	} );
}

const deletePaymentMethodData = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1732
		testKey: 'PCP-1732',
		payment: payPal,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1371
		testKey: 'PCP-1371',
		payment: acdc,
	},
];

for ( const testData of deletePaymentMethodData ) {
	const { testKey, payment } = testData;
	test.describe( () => {
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			`${ testKey } | Vaulting - My Account - Payment Methods - ${ payment.gateway.title } - Delete saved payment method`,
			annotateVisitor( customer ),
			async ( { utils, customerPaymentMethods, classicCheckout } ) => {
				await customerPaymentMethods.visit();
				await expect(
					customerPaymentMethods.noSavedMethodsMessage()
				).toBeVisible();

				await customerPaymentMethods.savePaymentMethod( payment );

				const savedPaymentMethodText =
					await customerPaymentMethods.getSavedPaymentMethodText(
						payment
					);

				await customerPaymentMethods
					.deletePaymentMethodButton( savedPaymentMethodText )
					.click();
				await customerPaymentMethods.page.waitForLoadState();

				await customerPaymentMethods.assertUrl();
				await expect(
					customerPaymentMethods.paymentMethodDeletedMessage()
				).toBeVisible();
				await expect(
					customerPaymentMethods.noSavedMethodsMessage()
				).toBeVisible();
				await customerPaymentMethods.assertIsNotSavedPaymentMethod(
					payment
				);

				await utils.fillVisitorsCart( [ products.simple10 ] );
				await classicCheckout.visit();
				await classicCheckout.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
					payment
				);
			}
		);
	} );
}

test.describe( () => {
	// Restore customer and his storage state to remove vaulted payment methods.
	// Placed in beforeAll for each test to be able to use storate state in a test.
	test.beforeAll( async ( { utils } ) => {
		await utils.restoreCustomer( customer );
	} );

	test(
		'PCP-0000 | Vaulting - My Account - Payment Methods - PayPal - Unable to save additional account',
		annotateVisitor( customer ),
		async ( { customerPaymentMethods } ) => {
			// Preconditions
			await customerPaymentMethods.visit();
			// Save initial card (not tested)
			await customerPaymentMethods.savePaymentMethod( payPal );
			// Assert tested card is not present in My Account
			await customerPaymentMethods.assertIsSavedPaymentMethod( payPal );
			await customerPaymentMethods.addPaymentMethodButton().click();
			await customerPaymentMethods.page.waitForLoadState();
			await expect(
				customerPaymentMethods.payPalUi.payPalGateway()
			).not.toBeVisible();
			await expect(
				customerPaymentMethods.payPalUi.payPalButton()
			).not.toBeVisible();
		}
	);
} );

test.describe( () => {
	// Restore customer and his storage state to remove vaulted payment methods.
	// Placed in beforeAll for each test to be able to use storate state in a test.
	test.beforeAll( async ( { utils } ) => {
		await utils.restoreCustomer( customer );
	} );

	test(
		'PCP-0000 | Vaulting - My Account - Payment Methods - ACDC - Save additional card',
		annotateVisitor( customer ),
		async ( { utils, customerPaymentMethods, classicCheckout } ) => {
			// Preconditions
			await customerPaymentMethods.visit();
			// Save initial card (not tested one)
			await customerPaymentMethods.savePaymentMethod( acdc );
			// Assert tested card is not present in My Account
			await customerPaymentMethods.assertIsSavedPaymentMethod( acdc );
			await customerPaymentMethods.assertIsNotSavedPaymentMethod( acdc2 );

			await customerPaymentMethods.savePaymentMethod( acdc2 );

			await customerPaymentMethods.assertUrl();
			await customerPaymentMethods.assertIsSavedPaymentMethod( acdc );
			await customerPaymentMethods.assertIsSavedPaymentMethod( acdc2 );

			await utils.fillVisitorsCart( [ products.simple10 ] );
			await classicCheckout.visit();
			await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
				acdc
			);
			await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
				acdc2
			);
		}
	);
} );
