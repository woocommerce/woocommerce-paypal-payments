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

export const testRefund = ( testsData: ShopRefund[] ) => {
	for ( const testData of testsData ) {
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				wooCommerceUtils,
				utils,
				wooCommerceOrderEdit,
				wooCommerceApi,
				payPalApi,
			} ) => {
				test.setTimeout( 2 * 60 * 1000 );
				let order: WooCommerce.Order; // TODO: fix type in playwright-utils
				const total = await countTotals( testData );
				const refundAvailable = total.order;
				const refundAmount = getAmountPercentage(
					refundAvailable,
					testData.refundPercentage
				);

				// Precondition
				if ( testData.isApiOrder ) {
					order = await wooCommerceUtils.createApiOrder( testData );
					order = await utils.payForApiOrder(
						order.id,
						order.order_key,
						testData
					);
				} else {
					order = await utils.completeOrderOnCheckout( testData );
				}

				// Test
				await wooCommerceOrderEdit.visit( order.id );
				await wooCommerceOrderEdit.refundButton().click();

				// Assertions before refund
				await expect(
					wooCommerceOrderEdit.restockRefundedItemsCheckbox()
				).toBeVisible();
				await expect(
					wooCommerceOrderEdit.totalAmountAlreadyRefunded()
				).toHaveText( `-${ formatMoney( 0, testData.currency ) }` );
				await expect(
					wooCommerceOrderEdit.totalAvailableToRefund()
				).toHaveText(
					formatMoney( Number( refundAvailable ), testData.currency )
				);

				// Make refund
				await wooCommerceOrderEdit.makePayPalRefund( refundAmount );
				// Assert URL after page is reloaded
				await wooCommerceOrderEdit.assertUrl( order.id );
				// Assert refund ID and expected refund amount are displayed
				await expect(
					wooCommerceOrderEdit.refundNumber()
				).toContainText( `Refund #` );
				await expect( wooCommerceOrderEdit.refundAmount() ).toHaveText(
					`-${ formatMoney(
						Number( refundAmount ),
						testData.currency
					) }`
				);

				// Assert via API WooCommerce Order refund status and presence of refunds
				order = await wooCommerceApi.getOrder( order.id );
				await expect( order.status ).toEqual(
					testData.refundOrderStatus
				);
				await expect( order.refunds ).not.toHaveLength( 0 );

				// Assert via API the refund status of PayPal payment
				const payPalPayment = await payPalApi.getCapturedPayment(
					order.transaction_id,
					testData.merchant
				);
				await expect( payPalPayment.status ).toEqual(
					testData.refundPaymentStatus
				);

				const orderRefund = order.refunds[ 0 ];
				await expect( orderRefund.total ).toEqual(
					`-${ Number( refundAmount ).toFixed( 2 ) }`
				);

				const payPalRefunds = order.meta_data.filter(
					( el ) => el.key === '_ppcp_refunds'
				)[ 0 ].value;
				const payPalRefundId = payPalRefunds[ 0 ];
				const payPalRefund = await payPalApi.getRefund(
					payPalRefundId,
					testData.merchant
				);
				await expect( payPalRefund.status ).toEqual( 'COMPLETED' );

				// Assert on OrderEdit page that WooCommerce and PayPal refund fields are displayed and have expected values
				await wooCommerceOrderEdit.assertRefundData( {
					currency: testData.currency,
					orderStatus: capitalizeFirst( testData.refundOrderStatus ),
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
			}
		);
	}
};
