/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

const testSubscriptionOrderGuest = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer: guest, merchant } = testOrder;

	test.describe( () => {
		// Delete guest since he becomes registered customer in subscription tests
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
				classicCheckout,
				orderReceived,
				customerSubscriptions,
			} ) => {
				test.setTimeout( 2 * 60_000 );
				await utils.fillVisitorsCart( products );
				await classicCheckout.visit();
				await classicCheckout.completeCheckoutDetails( testOrder );

				// Add excepton for free trial subscription paid with PayPal
				await classicCheckout.payPalUi.makePayment( {
					merchant,
					payment,
				} );
				await orderReceived.assertOrderDetails( testOrder );

				const subscriptionId =
					await orderReceived.getSubscriptionNumber();
				await customerSubscriptions.visit( subscriptionId );
				await customerSubscriptions.assertUrl( subscriptionId );
				await expect(
					customerSubscriptions.paymentMethod(),
					'Assert payment method on customer subscription details page'
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
				await classicCheckout.visit();
				await classicCheckout.payPalUi.expandPaymentGateway( payment );
				if ( payment.saveToAccount !== false ) {
					await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
						payment
					);
				} else {
					await classicCheckout.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
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
				classicCheckout,
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
				await classicCheckout.visit();
				await classicCheckout.completeCheckoutDetails( testOrder );

				// Add excepton for free trial subscription paid with PayPal
				// if (
				// 	products[ 0 ].slug.includes( 'free-trial' ) &&
				// 	payment.gateway.title === 'PayPal'
				// ) {
				// 	// In case of free product "Proceed to PayPal" black button is displayed instead of PayPal yellow button
				// 	await expect(
				// 		classicCheckout.proceedToPayPalButton(),
				// 		'Assert Proceed to PayPal button is visible'
				// 	).toBeVisible();
				// 	await classicCheckout.proceedToPayPalButton().click();
				// } else {
					await classicCheckout.payPalUi.makePayment( {
						merchant,
						payment,
					} );
				// }
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
				await classicCheckout.visit();
				await classicCheckout.payPalUi.expandPaymentGateway( payment );
				if ( payment.saveToAccount !== false ) {
					await classicCheckout.payPalUi.assertVaultedPaymentMethodIsDisplayed(
						payment
					);
				} else {
					await classicCheckout.payPalUi.assertVaultedPaymentMethodIsNotDisplayed(
						payment
					);
				}
			}
		);
	} );
};

export const testSubscriptionClassicCheckout = {
	testSubscriptionOrderGuest,
	testSubscriptionOrderCustomer,
};
