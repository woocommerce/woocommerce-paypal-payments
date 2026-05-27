/**
 * External dependencies
 */
import {
	capitalizeFirst,
	formatMoney,
	countTotals,
	getAmountPercentage,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test, expect, annotateVisitor } from '../../../utils';
import { ShopRefund } from '../../../resources';

export const testRefund = ( testData: ShopRefund ) => {
	const {
		title,
		customer,
		refundPercentage,
		refundOrderStatus,
		refundPaymentStatus,
		payment,
		currency,
		isApiOrder,
		merchant,
		products,
	} = testData;

	test(
		title,
		annotateVisitor( customer ),
		async ( {
			wooCommerceUtils,
			utils,
			classicCheckout,
			payForOrder,
			orderReceived,
			wooCommerceOrderEdit,
			wooCommerceApi,
			payPalApi,
		} ) => {
			test.setTimeout( 2 * 60_000 );
			let order: WooCommerce.Order; // TODO: fix type in playwright-utils
			let orderId: number;
			const total = await countTotals( testData );
			const refundAvailable = total.order;
			const refundAmount = getAmountPercentage(
				refundAvailable,
				refundPercentage
			);

			// Preconditions
			await test.step( 'Precondition: create WooCommerce order', async () => {
				if ( isApiOrder ) {
					order = await wooCommerceUtils.createApiOrder( testData );
					await payForOrder.visit( order.id, order.order_key );
					await payForOrder.payPalUi.makePayment( {
						merchant,
						payment,
					} );
				} else {
					await utils.fillVisitorsCart( products );
					await classicCheckout.visit();
					await classicCheckout.completeCheckoutDetails( testData );
					await classicCheckout.payPalUi.makePayment( {
						merchant,
						payment,
					} );
				}

				await orderReceived.page.waitForLoadState();
				orderId = await orderReceived.getOrderNumber();
				// Assert order status is processing (sometimes takes time to update after payment)
				await expect
					.poll(
						async () => {
							order = await wooCommerceApi.getOrder( orderId );
							return order.status;
						},
						{
							message: 'Assert order status is processing',
							timeout: 30_000,
							intervals: [ 1_000, 2_000, 3_000 ],
						}
					)
					.toEqual( 'processing' );
			} );

			// Test
			await test.step( `Make refund from WooCommerce via ${ payment.gateway.title }`, async () => {
				await wooCommerceOrderEdit.visit( order.id );
				await wooCommerceOrderEdit.refundButton().click();

				// Assertions before refund
				await expect(
					wooCommerceOrderEdit.restockRefundedItemsCheckbox(),
					'Assert "Restock refunded items" checkbox is visible'
				).toBeVisible();
				await expect(
					wooCommerceOrderEdit.totalAmountAlreadyRefunded(),
					'Assert total amount already refunded is visible'
				).toHaveText( `-${ formatMoney( 0, currency ) }` );
				await expect(
					wooCommerceOrderEdit.totalAvailableToRefund(),
					'Assert total amount available to refund is visible'
				).toHaveText(
					formatMoney( Number( refundAvailable ), currency )
				);

				// Make refund
				await wooCommerceOrderEdit.makeRefundVia(
					payment.gateway.title,
					refundAmount
				);
				// Assert URL after page is reloaded
				await wooCommerceOrderEdit.assertUrl( order.id );
			} );

			await test.step( 'Assert refund number and amount', async () => {
				// Assert refund ID and expected refund amount are displayed
				await expect(
					wooCommerceOrderEdit.refundNumber(),
					'Assert refund number is visible'
				).toContainText( `Refund #` );
				await expect(
					wooCommerceOrderEdit.refundAmount(),
					'Assert refund amount is visible'
				).toHaveText(
					`-${ formatMoney( Number( refundAmount ), currency ) }`
				);
			} );

			let orderRefund;
			let payPalRefund;
			let payPalPayment;
			await test.step( 'Assert via API WooCommerce Order refund status and presence of refunds', async () => {
				order = await wooCommerceApi.getOrder( order.id );
				await expect(
					order.status,
					`Assert order status is ${ refundOrderStatus }`
				).toEqual( refundOrderStatus );
				await expect(
					order.refunds,
					'Assert order has refunds'
				).not.toHaveLength( 0 );

				// Assert via API the refund status of PayPal payment
				payPalPayment = await payPalApi.getCapturedPayment(
					order.transaction_id,
					merchant
				);
				await expect(
					payPalPayment.status,
					`Assert PayPal payment status is ${ refundPaymentStatus }`
				).toEqual( refundPaymentStatus );

				orderRefund = order.refunds[ 0 ];
				await expect(
					orderRefund.total,
					'Assert refund total is the expected'
				).toEqual( `-${ Number( refundAmount ).toFixed( 2 ) }` );

				const payPalRefunds = order.meta_data.filter(
					( el ) => el.key === '_ppcp_refunds'
				)[ 0 ].value;
				const payPalRefundId = payPalRefunds[ 0 ];
				payPalRefund = await payPalApi.getRefund(
					payPalRefundId,
					merchant
				);
				await expect(
					payPalRefund.status,
					'Assert PayPal payment status is COMPLETED'
				).toEqual( 'COMPLETED' );
			} );

			await test.step( 'Assert on OrderEdit page that WooCommerce and PayPal refund fields are displayed and have expected values', async () => {
				await wooCommerceOrderEdit.assertRefundData( {
					currency,
					orderStatus: capitalizeFirst( refundOrderStatus ),
					refundId: orderRefund.id,
					refundAmount: Number( refundAmount ),
					refundTotal:
						payPalRefund.seller_payable_breakdown
							.total_refunded_amount.value,
					netPayment:
						parseFloat( order.total ) - parseFloat( refundAmount ),
					payPalFee:
						payPalPayment.seller_receivable_breakdown.paypal_fee
							.value,
					payPalRefundFee:
						payPalRefund.seller_payable_breakdown.paypal_fee.value,
					payPalRefunded:
						payPalRefund.seller_payable_breakdown.net_amount.value,
					payPalPayout:
						payPalPayment.seller_receivable_breakdown.net_amount
							.value,
					payPalNetTotal:
						parseFloat( order.total ) -
						parseFloat( refundAmount ) -
						parseFloat(
							payPalPayment.seller_receivable_breakdown.paypal_fee
								.value
						),
				} );
			} );
		}
	);
};
