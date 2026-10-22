/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import { annotateVisitor, test, waitForTransactionId } from '../../../utils';

export const transactionsOnMiniCart = ( testOrder: ShopOrder ) => {
	const { products, payment, customer, isPayNowEnabled = true } = testOrder;

	test(
		testOrder.title,
		annotateVisitor( customer ),
		async ( {
			shop,
			checkout,
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			const { title: gatewayTitle } = payment.gateway;

			await test.step( `Add product(s) to the cart`, async () => {
				await utils.fillVisitorsCart( products );
			} );

			await test.step( `Open mini cart on Shop page, make payment with ${ gatewayTitle }`, async () => {
				// Shop page has no other PayPal buttons
				await shop.visit();
				// Storefront opens the mini cart on hover
				await shop.minicartContainer().hover();
				await shop.payPalUi.makeMiniCartPayment( {
					payment,
					isPayNowEnabled,
				} );
				if ( ! isPayNowEnabled ) {
					// Review happens on the checkout page set in WooCommerce
					const reviewCheckout = shop.payPalUi.isClassicCheckoutPage()
						? classicCheckout
						: checkout;
					await reviewCheckout.completeOrderFromProduct( testOrder );
				}
			} );

			let orderId: number;
			let payPalPaymentDetails: PayPalPaymentDetails;

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				orderId = await orderReceived.getOrderNumber();
				const transactionId = await waitForTransactionId(
					wooCommerceApi,
					orderId
				);

				payPalPaymentDetails = await payPalApi.getPayPalPaymentDetails(
					transactionId,
					testOrder
				);

				await orderReceived.assertTotalEqualsPayPalTotal(
					payPalPaymentDetails.amount,
					testOrder.currency
				);
			} );

			await test.step( `Assert details on order edit page`, async () => {
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails(
					testOrder,
					payPalPaymentDetails
				);
			} );
		}
	);
};
