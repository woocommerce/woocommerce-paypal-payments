/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import {
	annotateVisitor,
	test,
	expect,
	waitForOrderStatus,
	waitForTransactionId,
} from '../../../utils';

/**
 * Captures an authorization from the WooCommerce order screen.
 *
 * The Intent Authorized tests stop once the order reaches on-hold, which is the
 * correct state for an authorization: funds are reserved, nothing is captured,
 * so the order is not paid. Nothing then exercises the other half of the flow -
 * the merchant capturing the authorization - even though that is the ordinary
 * way an authorized order gets completed.
 *
 * The capture is initiated from WooCommerce and calls PayPal directly, so this
 * needs no webhook.
 *
 * @param testOrder The order test data; expects payment.isAuthorized.
 */
export const captureAuthorizedPayment = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

	test(
		title,
		annotateVisitor( customer ),
		async ( {
			utils,
			classicCheckout,
			orderReceived,
			wooCommerceOrderEdit,
			wooCommerceApi,
			payPalApi,
		} ) => {
			let orderId: number;
			let authorizationId: string;

			await test.step( 'Precondition: place an authorized order', async () => {
				await utils.fillVisitorsCart( products );
				await classicCheckout.visit();
				await classicCheckout.completeCheckoutDetails( testOrder );
				await classicCheckout.payPalUi.makePayment( {
					merchant,
					payment,
				} );

				await orderReceived.page.waitForLoadState();
				orderId = await orderReceived.getOrderNumber();

				await waitForOrderStatus( wooCommerceApi, orderId, {
					expectedStatus: 'on-hold',
					timeout: 30_000,
				} );
				authorizationId = await waitForTransactionId(
					wooCommerceApi,
					orderId
				);
			} );

			await test.step( 'Assert the order is authorized but not captured', async () => {
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertIntentAuthorizedState();

				const authorization = await payPalApi.getAuthorizedPayment(
					authorizationId,
					merchant
				);
				expect(
					authorization.status,
					'Assert PayPal authorization is created'
				).toEqual( 'CREATED' );
			} );

			await test.step( 'Capture the authorized payment', async () => {
				await wooCommerceOrderEdit.captureAuthorizedPayment();
			} );

			await test.step( 'Assert the order is captured and paid', async () => {
				await waitForOrderStatus( wooCommerceApi, orderId, {
					expectedStatus: 'processing',
					timeout: 60_000,
				} );

				await wooCommerceOrderEdit.visit( orderId );
				await expect(
					wooCommerceOrderEdit.notCapturedIndicator(),
					'Assert the "Not captured" indicator is gone'
				).toBeHidden();

				const authorization = await payPalApi.getAuthorizedPayment(
					authorizationId,
					merchant
				);
				expect(
					authorization.status,
					'Assert PayPal authorization is captured'
				).toEqual( 'CAPTURED' );
			} );
		}
	);
};
