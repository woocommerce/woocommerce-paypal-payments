/**
 * Internal dependencies
 */
import { cards, payments, ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

const testSavePaymentMethod = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

	test.describe( () => {
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			title,
			annotateVisitor( customer ),
			async ( {
				utils,
				customerPaymentMethods,
				checkout,
				orderReceived,
			} ) => {
				// Preconditions
				await customerPaymentMethods.visit();
				await expect(
					customerPaymentMethods.noSavedMethodsMessage(),
					'Assert no saved payment methods message is visible'
				).toBeVisible();

				// Make tested order (testOrder.payment.saveToAccount = true):
				await utils.fillVisitorsCart( products );
				await checkout.visit();
				await checkout.completeCheckoutDetails( testOrder );
				await checkout.payPalUi.makePayment( { merchant, payment } );
				await orderReceived.assertOrderDetails( testOrder );

				await customerPaymentMethods.visit();
				if ( payment.saveToAccount === true ) {
					await customerPaymentMethods.assertIsSavedPaymentMethod(
						payment
					);
				} else {
					await customerPaymentMethods.assertIsNotSavedPaymentMethod(
						payment
					);
				}

				await utils.fillVisitorsCart( products );
				await checkout.visit();
				if ( payment.saveToAccount === true ) {
					await checkout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
						payment
					);
				} else {
					await checkout.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
						payment
					);
				}
			}
		);
	} );
};

const testAcdcAdditionalCard = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

	test.describe( () => {
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			title,
			annotateVisitor( customer ),
			async ( {
				utils,
				customerPaymentMethods,
				checkout,
				orderReceived,
			} ) => {
				// Preconditions
				await customerPaymentMethods.visit();
				// Save initial card (not tested one)
				await customerPaymentMethods.savePaymentMethod( {
					...payments.acdc,
					card: cards.visa2,
				} );
				// Assert tested card is not present in My Account
				await customerPaymentMethods.assertIsNotSavedPaymentMethod(
					payment
				);

				// Make tested order (testOrder.payment.saveToAccount = true):
				await utils.fillVisitorsCart( products );
				await checkout.visit();
				await checkout.completeCheckoutDetails( testOrder );
				await checkout.payPalUi.makePayment( { merchant, payment } );
				await orderReceived.assertOrderDetails( testOrder );

				await customerPaymentMethods.visit();
				if ( payment.saveToAccount === true ) {
					await customerPaymentMethods.assertIsSavedPaymentMethod(
						payment
					);
				} else {
					await customerPaymentMethods.assertIsNotSavedPaymentMethod(
						payment
					);
				}

				await utils.fillVisitorsCart( products );
				await checkout.visit();
				if ( payment.saveToAccount === true ) {
					await checkout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
						payment
					);
				} else {
					await checkout.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
						payment
					);
				}
			}
		);
	} );
};

const testVaultedPaymentMethod = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

	test.describe( () => {
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			title,
			annotateVisitor( customer ),
			async ( {
				utils,
				customerPaymentMethods,
				checkout,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
			} ) => {
				// Preconditions
				await customerPaymentMethods.visit();
				await customerPaymentMethods.savePaymentMethod( payment );

				// Make tested order:
				await utils.fillVisitorsCart( products );
				await checkout.visit();
				await checkout.completeCheckoutDetails( testOrder );
				await checkout.payPalUi.makePayment( { merchant, payment } );
				await orderReceived.assertOrderDetails( testOrder );

				const orderId = await orderReceived.getOrderNumber();
				const { transaction_id: transactionId } =
					await wooCommerceApi.getOrder( orderId );
				const payPalFee = await payPalApi.getFee(
					transactionId,
					testOrder
				);
				const payPalPayout = await payPalApi.getPayout(
					transactionId,
					testOrder
				);
				const pcpData = { transactionId, payPalFee, payPalPayout };

				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails(
					testOrder,
					pcpData
				);
			}
		);
	} );
};

export const testVaultingCheckout = {
	testSavePaymentMethod,
	testAcdcAdditionalCard,
	testVaultedPaymentMethod,
};
