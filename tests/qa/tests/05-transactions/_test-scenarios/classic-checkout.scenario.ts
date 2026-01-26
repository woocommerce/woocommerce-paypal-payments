/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicCheckout = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			await utils.fillVisitorsCart( testOrder.products );
			await classicCheckout.visit();
			await classicCheckout.completeCheckoutDetails( testOrder );
			await classicCheckout.payPalUi.makePayment( { merchant, payment } );
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
			await wooCommerceOrderEdit.assertOrderDetails( testOrder, pcpData );
		}
	);
};

export const transactionsOnClassicCheckoutOxxo = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

	test.fixme(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			await utils.fillVisitorsCart( testOrder.products );
			await classicCheckout.visit();
			await classicCheckout.completeCheckoutDetails( testOrder );
			await classicCheckout.payPalUi.makePayment( { merchant, payment } );
			await orderReceived.assertOrderDetails( testOrder );

			const orderId = await orderReceived.getOrderNumber();
			const orderJson = await wooCommerceApi.getOrder( orderId );

			const oxxoOrderId = await payPalApi.getOrderIdFromWooCommerce(
				orderJson
			);
			const oxxoOrder = await payPalApi.getOrder(
				oxxoOrderId,
				testOrder.merchant
			);
			const oxxoPaymentId = await payPalApi.getPaymentIdFromOrder(
				oxxoOrder,
				testOrder.payment
			);
		}
	);
};
