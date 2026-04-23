/**
 * External dependencies
 */
import {
	capitalizeFirst,
	formatMoney,
	countTotals,
	getHalfPrice,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test, expect, annotateVisitor } from '../../../utils';
import { pcpConfigDefault } from '../../../resources';

export const testRefund = ( tests ) => {
	for ( const tested of tests ) {
		test(
			tested.title,
			annotateVisitor( tested.customer ),
			async ( {
				wooCommerceUtils,
				utils,
				wooCommerceOrderEdit,
				wooCommerceApi,
				ppapi,
			} ) => {
				let order;
				const total = await countTotals( tested );
				const refundAvailable = total.order;
				const refundAmount = tested.isFullRefund
					? refundAvailable.toString()
					: getHalfPrice( refundAvailable );

				// Preconditions
				if ( tested.isApiOrder ) {
					order = await wooCommerceUtils.createApiOrder( tested );
					order = await utils.payForApiOrder(
						order.id,
						order.order_key,
						tested
					);
				} else {
					order = await utils.completeOrderOnCheckout( tested );
				}

				// Test
				await wooCommerceOrderEdit.visit( order.id );
				await wooCommerceOrderEdit.refundButton().click();
				await expect(
					wooCommerceOrderEdit.restockRefundedItemsCheckbox()
				).toBeVisible();
				await expect(
					wooCommerceOrderEdit.totalAmountAlreadyRefunded()
				).toHaveText( `-${ formatMoney( 0, tested.currency ) }` );
				await expect(
					wooCommerceOrderEdit.totalAvailableToRefund()
				).toHaveText(
					formatMoney( Number( refundAvailable ), tested.currency )
				);

				await wooCommerceOrderEdit.makePayPalRefund( refundAmount );
				await wooCommerceOrderEdit.assertUrl( order.id );
				await expect(
					wooCommerceOrderEdit.refundNumber()
				).toContainText( `Refund #` );
				await expect( wooCommerceOrderEdit.refundAmount() ).toHaveText(
					`-${ formatMoney(
						Number( refundAmount ),
						tested.currency
					) }`
				);

				order = await wooCommerceApi.getOrder( order.id );
				await expect( order.status ).toEqual(
					tested.refundOrderStatus
				);
				await expect( order.refunds ).not.toHaveLength( 0 );

				const payPalPayment = await ppapi.getCapturedPayment(
					order.transaction_id,
					pcpConfigDefault.merchant
				);
				await expect( payPalPayment.status ).toEqual(
					tested.refundPaymentStatus
				);

				const orderRefund = order.refunds[ 0 ];
				await expect( orderRefund.total ).toEqual(
					`-${ Number( refundAmount ).toFixed( 2 ) }`
				);

				const payPalRefunds = order.meta_data.filter(
					( el ) => el.key === '_ppcp_refunds'
				)[ 0 ].value;
				const payPalRefundId = payPalRefunds[ 0 ];
				const payPalRefund = await ppapi.getRefund(
					payPalRefundId,
					pcpConfigDefault.merchant
				);
				await expect( payPalRefund.status ).toEqual( 'COMPLETED' );

				await wooCommerceOrderEdit.assertRefundData( {
					orderStatus: capitalizeFirst( tested.refundOrderStatus ),
					refund_id: orderRefund.id,
					refunded: refundAmount,
					totalRefunded:
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
					currency: tested.currency,
				} );
			}
		);
	}
};
