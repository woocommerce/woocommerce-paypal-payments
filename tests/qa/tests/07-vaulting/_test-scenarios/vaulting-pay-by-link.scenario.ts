/**
 * Internal dependencies
 */
import { cards, payments, ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

const testSavePaymentMethod = ( testOrder: ShopOrder ) => {
	const { title, payment, customer } = testOrder;

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
				wooCommerceUtils,
				customerPaymentMethods,
				payForOrder,
				orderReceived,
			} ) => {
				// Preconditions
				await customerPaymentMethods.visit();
				await expect(
					customerPaymentMethods.noSavedMethodsMessage()
				).toBeVisible();

				// Make tested order (testOrder.payment.saveToAccount = true):
				let order = await wooCommerceUtils.createApiOrder( testOrder );
				await payForOrder.makeOrder( testOrder, order );
				// Expect Order Received page to be loaded
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

				order = await wooCommerceUtils.createApiOrder( testOrder );
				await payForOrder.visit( order.id, order.order_key );
				if ( payment.saveToAccount === true ) {
					await payForOrder.payPalUi.assertVaultedPaymentMethodIsDisplayed(
						payment
					);
				} else {
					await payForOrder.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
						payment
					);
				}
			}
		);
	} );
};

const testAcdcAdditionalCard = ( testOrder: ShopOrder ) => {
	const { title, payment, customer } = testOrder;

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
				wooCommerceUtils,
				customerPaymentMethods,
				payForOrder,
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
				await customerPaymentMethods.assertUrl();
				await customerPaymentMethods.assertIsNotSavedPaymentMethod(
					payment
				);

				// Make tested order (testOrder.payment.saveToAccount = true):
				let order = await wooCommerceUtils.createApiOrder( testOrder );
				await payForOrder.makeOrder( testOrder, order );
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

				order = await wooCommerceUtils.createApiOrder( testOrder );
				await payForOrder.visit( order.id, order.order_key );
				if ( payment.saveToAccount === true ) {
					await payForOrder.payPalUi.assertVaultedPaymentMethodIsDisplayed(
						payment
					);
				} else {
					await payForOrder.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
						payment
					);
				}
			}
		);
	} );
};

const testVaultedPaymentMethod = ( testOrder: ShopOrder ) => {
	const { title, payment, customer } = testOrder;

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
				wooCommerceUtils,
				customerPaymentMethods,
				payForOrder,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
			} ) => {
				// Preconditions
				await customerPaymentMethods.visit();
				await customerPaymentMethods.savePaymentMethod( payment );

				// Make tested order:
				const order = await wooCommerceUtils.createApiOrder(
					testOrder
				);
				await payForOrder.makeOrder( testOrder, order );
				await orderReceived.assertOrderDetails( testOrder );

				const orderId = await orderReceived.getOrderNumber();
				const orderJson = await wooCommerceApi.getOrder( orderId );

				const pcpData = {
					transactionId: orderJson.transaction_id,
					payPalFee: await payPalApi.getFee(
						orderJson.transaction_id,
						testOrder
					),
					payPalPayout: await payPalApi.getPayout(
						orderJson.transaction_id,
						testOrder
					),
				};

				await payPalApi.assertOrder( orderJson, testOrder );
				await payPalApi.assertPayment(
					orderJson.transaction_id,
					testOrder
				);
				await wooCommerceOrderEdit.assertOrderDetails(
					orderId,
					testOrder,
					pcpData
				);
			}
		);
	} );
};

export const testVaultingPayByLink = {
	testSavePaymentMethod,
	testAcdcAdditionalCard,
	testVaultedPaymentMethod,
};
