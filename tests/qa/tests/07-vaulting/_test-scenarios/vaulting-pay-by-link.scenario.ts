/**
 * Internal dependencies
 */
import { cards, payments, PayPalPaymentDetails, ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

const testSavePaymentMethod = ( testOrder: ShopOrder ) => {
	const { title, payment, customer, merchant } = testOrder;

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
					customerPaymentMethods.noSavedMethodsMessage(),
					'Assert no saved payment methods message is visible'
				).toBeVisible();

				// Make tested order (testOrder.payment.saveToAccount = true):
				let order = await wooCommerceUtils.createApiOrder( testOrder );

				await payForOrder.visit( order.id, order.order_key );
				await payForOrder.payPalUi.makePayment( { merchant, payment } );
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
				await payForOrder.payPalUi.expandPaymentGateway( payment );
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
	const { title, payment, customer, merchant } = testOrder;

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
					card: cards.visa,
				} );
				// Assert tested card is not present in My Account
				await customerPaymentMethods.assertUrl();
				await customerPaymentMethods.assertIsNotSavedPaymentMethod(
					payment
				);

				// Make tested order (testOrder.payment.saveToAccount = true):
				let order = await wooCommerceUtils.createApiOrder( testOrder );

				await payForOrder.visit( order.id, order.order_key );
				await payForOrder.payPalUi.makePayment( { merchant, payment } );
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
				await payForOrder.payPalUi.expandPaymentGateway( payment );
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
	const { title, payment, customer, merchant } = testOrder;

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
				let order: WooCommerce.Order;
				const { title: gatewayTitle } = payment.gateway;
				
				await test.step( `Precondition: save ${ gatewayTitle } payment method`, async () => {
					await customerPaymentMethods.visit();
					await customerPaymentMethods.savePaymentMethod( payment );
				} );

				await test.step( `Precondition: create order via API (dashboard)`, async () => {
					order = await wooCommerceUtils.createApiOrder( testOrder );
				} );

				await test.step( `Visit Pay for Order, make payment with ${ gatewayTitle }`, async () => {
					await payForOrder.visit( order.id, order.order_key );
					await payForOrder.payPalUi.makePayment( { merchant, payment } );
				} );

				let payPalPaymentDetails: PayPalPaymentDetails;

				await test.step( `Assert order received`, async () => {
					await orderReceived.assertOrderDetails( testOrder );
					await orderReceived.assertNoErrors();

					const orderNumber = await orderReceived.getOrderNumber();
					await expect(
						order.id,
						`Assert order ID (${ order.id }) matches order number on Order Received page`
					).toEqual( orderNumber );				
					
					const transactionId =
							( await wooCommerceApi.getOrder( order.id ) ).transaction_id;

					payPalPaymentDetails = await payPalApi.getPayPalPaymentDetails(
						transactionId,
						testOrder,
					);

					if( payPalPaymentDetails.amount !== '0' ) { // can be 0 for free trial or free orders
						await orderReceived.assertTotalEqualsPayPalTotal(
							payPalPaymentDetails.amount,
							testOrder.currency
						);
					}
				} );

				await test.step( `Assert details on order edit page`, async () => {
					await wooCommerceOrderEdit.visit( order.id );
					await wooCommerceOrderEdit.assertOrderDetails( testOrder, payPalPaymentDetails );
				} );
			}
		);
	} );
};

export const testVaultingPayByLink = {
	testSavePaymentMethod,
	testAcdcAdditionalCard,
	testVaultedPaymentMethod,
};
