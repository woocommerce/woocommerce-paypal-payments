/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

const testSubscriptionOrderGuest = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer } = testOrder;

	test.describe( () => {
		// Delete guest since he becomes registered customer in subscription tests
		test.beforeAll( async ( { requestUtils, wooCommerceUtils } ) => {
			await wooCommerceUtils.deleteCustomer( customer );
			const userName = `${ customer.first_name } ${ customer.last_name }`;
			const user = await requestUtils.getUserByName( userName );
			if( user.length ) {
				await requestUtils.deleteUser( user[ 0 ].id );
			}
		} );
		
		test(
			title,
			annotateVisitor( customer ),
			async ( {
				utils,
				customerPaymentMethods,
				checkout,
				orderReceived,
				customerSubscriptions,
			} ) => {
				test.setTimeout( 2 * 60 * 1000 );
				await utils.fillVisitorsCart( products );
				await checkout.makeOrder( testOrder );
				await orderReceived.assertOrderDetails( testOrder );

				const subscriptionId = await orderReceived.getSubscriptionNumber();
				await customerSubscriptions.visit( subscriptionId );
				await customerSubscriptions.assertUrl( subscriptionId );
				await expect(
					customerSubscriptions.paymentMethod()
				).toHaveText( new RegExp( payment.gateway.title ) );
				// TODO: additional assertions?

				await customerPaymentMethods.visit();
				if ( payment.saveToAccount !== false ) {
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
				if ( payment.saveToAccount !== false ) {
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

const testSubscriptionOrderCustomer = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer } = testOrder;

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
				customerSubscriptions,
			} ) => {
				test.setTimeout( 2 * 60 * 1000 );
				// Preconditions
				await customerPaymentMethods.visit();
				await customerPaymentMethods.assertIsNotSavedPaymentMethod(
					payment
				);

				// Make tested order (testOrder.payment.saveToAccount = true):
				await utils.fillVisitorsCart( products );
				await checkout.makeOrder( testOrder );
				await orderReceived.assertOrderDetails( testOrder );

				const subscriptionId = await orderReceived.getSubscriptionNumber();
				await customerSubscriptions.visit( subscriptionId );
				await customerSubscriptions.assertUrl( subscriptionId );
				await expect(
					customerSubscriptions.paymentMethod()
				).toHaveText( new RegExp( payment.gateway.title ) );
				// TODO: additional assertions?

				await customerPaymentMethods.visit();
				if ( payment.saveToAccount !== false ) {
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
				if ( payment.saveToAccount !== false ) {
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

export const testSubscriptionCheckout = {
	testSubscriptionOrderGuest,
	testSubscriptionOrderCustomer,
};
