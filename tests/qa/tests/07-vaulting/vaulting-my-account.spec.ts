/**
 * Internal dependencies
 */
import { annotateVisitor, expect, test, PayPalPopup } from '../../utils';
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

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		enableClassicPages: true,
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
		// Fail: Deleting Payment Token in WC does not delete it on PayPal bug https://inpsyde.atlassian.net/browse/PCP-4782
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
		'PCP-5380 | Vaulting - My Account - Payment Methods - PayPal - Unable to save additional account',
		annotateVisitor( customer ),
		async ( { customerPaymentMethods } ) => {
			await test.step( 'Save initial PayPal account', async () => {
				await customerPaymentMethods.visit();
				// Save and assert payment method
				await customerPaymentMethods.savePaymentMethod( payPal );
			} );
			
			await test.step( 'Save another PayPal account', async () => {
				const secondPayPalAccount = {
					email: process.env.PAYPAL_PERSONAL_EMAIL_US2,
					password: process.env.PAYPAL_PERSONAL_PASS_US2,
				};
				await customerPaymentMethods.addPaymentMethodButton().click();
				const payPalGatewayButton = customerPaymentMethods.payPalUi.payPalGateway();
				await expect (
					payPalGatewayButton,
					'Assert PayPal gateway is visible',
				).toBeVisible();
				await payPalGatewayButton.click();
				await customerPaymentMethods.page.waitForLoadState();
				await expect(
					customerPaymentMethods.payPalUi.payPalButton(),
					'Assert PayPal button is visible'
				).toBeVisible();
				
				// Assert PayPal dropdown menu button
				const payPalButtonMoreOptions =
					customerPaymentMethods.payPalUi.payPalButtonMoreOptions();
				await expect(
					payPalButtonMoreOptions,
					'Assert PayPal dropdown menu button is visible'
				).toBeVisible();
				await payPalButtonMoreOptions.click();

				// Assert "Pay with different account" button
				const payWithDifferentAccountButton =
					customerPaymentMethods.payPalUi.payWithDifferentAccountButton();
				await expect(
					payWithDifferentAccountButton,
					'Assert Pay with different account button is visible'
				).toBeVisible();
				
				// Call PayPal popup using "Pay with different account" button
				const popupPromise =
					customerPaymentMethods.payPalUi.page.waitForEvent( 'popup', {
						timeout: 20_000,
					} );
				await payWithDifferentAccountButton.click();
		
				const popup = await popupPromise;
				await popup.waitForLoadState();
				const payPalPopup = new PayPalPopup( popup );

				await payPalPopup.tryChangeUser();
				await payPalPopup.completePayPalPayment( secondPayPalAccount );
				await customerPaymentMethods.assertUrl();
				await customerPaymentMethods.assertIsSavedPaymentMethod( {
					gateway: payPal.gateway,
					payPalAccount: secondPayPalAccount
				} );
				await customerPaymentMethods.assertIsNotSavedPaymentMethod( payPal );
			} );
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
