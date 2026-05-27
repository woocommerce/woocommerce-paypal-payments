/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

const testSubscriptionOrderGuest = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer: guest, merchant } = testOrder;

	test.describe( () => {
		test.beforeAll( async ( { wooCommerceUtils } ) => {
			const previousEmails = [
				guest.email,
				payment.payPalAccount?.email,
			];
			for ( const email of previousEmails ) {
				await wooCommerceUtils.deleteCustomer( { email } );
			}
		} );

		test(
			title,
			annotateVisitor( guest ),
			async ( {
				utils,
				customerPaymentMethods,
				checkout,
				orderReceived,
				customerSubscriptions,
			} ) => {
				test.setTimeout( 2 * 60_000 );
				await utils.fillVisitorsCart( products );
				await checkout.visit();
				await checkout.completeCheckoutDetails( testOrder );
				await checkout.payPalUi.makePayment( { merchant, payment } );
				await orderReceived.assertOrderDetails( testOrder );

				const subscriptionId =
					await orderReceived.getSubscriptionNumber();
				await customerSubscriptions.visit( subscriptionId );
				await customerSubscriptions.assertUrl( subscriptionId );
				await expect(
					customerSubscriptions.paymentMethod(),
					`Assert payment method is ${ payment.gateway.title }`
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
				customerSubscriptions,
			} ) => {
				test.setTimeout( 2 * 60_000 );
				// Preconditions
				await customerPaymentMethods.visit();
				await customerPaymentMethods.assertIsNotSavedPaymentMethod(
					payment
				);

				// Make tested order (testOrder.payment.saveToAccount = true):
				await utils.fillVisitorsCart( products );
				await checkout.visit();
				await checkout.completeCheckoutDetails( testOrder );
				await checkout.payPalUi.makePayment( { merchant, payment } );
				await orderReceived.assertOrderDetails( testOrder );

				const subscriptionId =
					await orderReceived.getSubscriptionNumber();
				await customerSubscriptions.visit( subscriptionId );
				await customerSubscriptions.assertUrl( subscriptionId );
				await expect(
					customerSubscriptions.paymentMethod(),
					`Assert payment method is ${ payment.gateway.title }`
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
