/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

const testSavePaymentMethod = ( testOrder: ShopOrder ) => {
	const { title, products, payment, merchant, coupons, customer, shipping } =
		testOrder;

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
				classicCart,
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
				await classicCart.visit();
				// Add coupons if needed
				for ( const coupon of coupons ?? [] ) {
					await classicCart.applyCoupon( coupon.code );
				}
				await classicCart.selectShippingMethod(
					shipping.settings.title
				);
				await classicCart.payPalUi.makePayment( { merchant, payment } );
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
				await classicCart.visit();
				if ( payment.saveToAccount === true ) {
					await classicCart.payPalUi.assertVaultedPaymentMethodIsDisplayed(
						payment
					);
				} else {
					await classicCart.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
						payment
					);
				}
			}
		);
	} );
};

const testVaultedPaymentMethod = ( testOrder: ShopOrder ) => {
	const { title, products, payment, merchant, coupons, customer, shipping } =
		testOrder;

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
				classicCart,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
			} ) => {
				const { title: gatewayTitle } = payment.gateway;

				await test.step( `Precondition: save ${ gatewayTitle } payment method`, async () => {
					await customerPaymentMethods.visit();
					await customerPaymentMethods.savePaymentMethod( payment );
				} );


				await test.step( `Add product(s) to the cart`, async () => {
					await utils.fillVisitorsCart( products );
				} );

				await test.step( `Visit Classic Cart, make payment with ${ gatewayTitle }`, async () => {
					await classicCart.visit();
					// Add coupons if needed
					for ( const coupon of coupons ?? [] ) {
						await classicCart.applyCoupon( coupon.code );
					}
					await classicCart.selectShippingMethod(
						shipping.settings.title
					);
					await classicCart.payPalUi.makePayment( { merchant, payment } );
				} );
					
				let orderId: number;
				let payPalPaymentDetails: PayPalPaymentDetails;

				await test.step( `Assert order received`, async () => {
					await orderReceived.assertOrderDetails( testOrder );
					await orderReceived.assertNoErrors();

					orderId = await orderReceived.getOrderNumber();				
					const transactionId =
							( await wooCommerceApi.getOrder( orderId ) ).transaction_id;

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
					await wooCommerceOrderEdit.visit( orderId );
					await wooCommerceOrderEdit.assertOrderDetails( testOrder, payPalPaymentDetails );
				} );
			}
		);
	} );
};

export const testVaultingClassicCart = {
	testSavePaymentMethod,
	testVaultedPaymentMethod,
};
