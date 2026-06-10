/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { test, expect, annotateVisitor } from '../../../utils';

export const transactionsOnPayByLink = ( testOrder: ShopOrder ) => {
	const { payment, merchant } = testOrder;

	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			wooCommerceUtils,
			payForOrder,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
		} ) => {
			const order = await wooCommerceUtils.createApiOrder( testOrder );

			await payForOrder.visit( order.id, order.order_key );
			await payForOrder.payPalUi.makePayment( { merchant, payment } );
			await orderReceived.assertOrderDetails( testOrder );

			await expect( order.id ).toEqual(
				await orderReceived.getOrderNumber(),
				`Assert order number on order received page matches ${ order.id }`
			);
			const { transaction_id: transactionId } =
				await wooCommerceApi.getOrder( order.id );
			const payPalFee = await payPalApi.getFee(
				transactionId,
				testOrder
			);
			const payPalPayout = await payPalApi.getPayout(
				transactionId,
				testOrder
			);
			const pcpData = { transactionId, payPalFee, payPalPayout };

			await wooCommerceOrderEdit.visit( order.id );
			await wooCommerceOrderEdit.assertOrderDetails( testOrder, pcpData );
		}
	);
};
