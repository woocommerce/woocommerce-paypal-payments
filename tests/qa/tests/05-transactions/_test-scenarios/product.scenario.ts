/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnProduct = ( testOrder: ShopOrder ) => {
	const { products, payment, merchant } = testOrder;

	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			product,
			checkout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
		} ) => {
			await product.visit( products[ 0 ].slug );
			await product.payPalUi.makePayment( { merchant, payment } );
			await checkout.completeOrderFromProduct( testOrder );
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
