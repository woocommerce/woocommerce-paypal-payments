/**
 * Internal dependencies
 */
import { annotateVisitor, expect, test } from '../../utils';
import { customers, payments, cards, products } from '../../resources';

const customer = customers.usa;
const { payPal, acdc } = payments;
const acdc2 = { ...acdc, card: cards.visa };

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
} );

const savePaymentMethodData = [
	{
		testKey: 'PCP-4499',
		testLabel: ' @Smoke',
		payment: payPal,
	},
	{
		testKey: 'PCP-4500',
		testLabel: ' @Smoke',
		payment: acdc,
	},
];

for ( const testData of savePaymentMethodData ) {
	const { testKey, testLabel, payment } = testData;
	test.describe( () => {
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			`${ testKey } | Vaulting - My Account - Payment Methods - ${
				payment.gateway.title
			} - Save payment method${ testLabel ?? '' }`,
			annotateVisitor( customer ),
			async ( { utils, customerPaymentMethods, classicCheckout } ) => {
				await customerPaymentMethods.visit();
				await expect(
					customerPaymentMethods.noSavedMethodsMessage(),
					'Assert no saved payment methods message is visible'
				).toBeVisible();

				await customerPaymentMethods.savePaymentMethod( payment );

				await customerPaymentMethods.assertUrl();
				await customerPaymentMethods.assertIsSavedPaymentMethod(
					payment
				);

				await utils.fillVisitorsCart( [ products.simple100 ] );
				await classicCheckout.visit();
				await classicCheckout.payPalUi.expandPaymentGateway( payment );
				await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
					payment
				);
			}
		);
	} );
}

const deletePaymentMethodData = [
	{
		// Fail: Deleting Payment Token in WC does not delete it on PayPal bug PCP-4782
		testKey: 'PCP-1732',
		payment: payPal,
	},
	{
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
					customerPaymentMethods.noSavedMethodsMessage(),
					'Assert no saved payment methods message is visible'
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
					customerPaymentMethods.paymentMethodDeletedMessage(),
					'Assert payment method deleted message is visible'
				).toBeVisible();
				await expect(
					customerPaymentMethods.noSavedMethodsMessage(),
					'Assert no saved payment methods message is visible after deletion'
				).toBeVisible();
				await customerPaymentMethods.assertIsNotSavedPaymentMethod(
					payment
				);

				await utils.fillVisitorsCart( [ products.simple100 ] );
				await classicCheckout.visit();
				await classicCheckout.payPalUi.expandPaymentGateway( payment );
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
		// Fail:
		'PCP-5381 | Vaulting - My Account - Payment Methods - ACDC - Save additional card',
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

			await utils.fillVisitorsCart( [ products.simple100 ] );
			await classicCheckout.visit();
			await classicCheckout.payPalUi.expandPaymentGateway( acdc );
			await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
				acdc
			);
			await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
				acdc2
			);
		}
	);
} );
