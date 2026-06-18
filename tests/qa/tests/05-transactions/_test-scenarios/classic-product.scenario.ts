/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test, expect } from '../../../utils';
import {
	getTestedGatewayButton,
	assertPaymentBlockedBeforeVariationSelected,
	assertPaymentEnabledAfterVariationSelected,
} from './product.scenario'

export const transactionsOnClassicProduct = ( testOrder: ShopOrder ) => {
	const { products, payment, merchant, isPayNowEnabled } = testOrder;

	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			product,
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
		} ) => {
			const { type: productType, variationToSelect } = products[ 0 ];
			const { title: gatewayTitle, shortcut: gatewayShortcut } = payment.gateway;
			await test.step( `Visit product page and assert ${ gatewayTitle } button visibility`, async () => {
				await product.visit( products[ 0 ].slug );
				await expect(
					getTestedGatewayButton( product, gatewayShortcut ),
					`Assert ${ gatewayTitle } button is visible on product page`,
				).toBeVisible();
			} );

			if ( productType === 'variable' && variationToSelect ) {
				await assertPaymentBlockedBeforeVariationSelected(
					product,
					gatewayTitle,
					gatewayShortcut,
				);
				await assertPaymentEnabledAfterVariationSelected(
					product,
					gatewayTitle,
					gatewayShortcut,
					variationToSelect
				);
			}
			
			await test.step( `Complete payment with ${ gatewayTitle }`, async () => {
				await product.payPalUi.makePayment( { merchant, payment } );
				if( isPayNowEnabled === false ) {
					await classicCheckout.completeOrderFromProduct( testOrder );
				}
			} );
				
			let orderId: number;
			let pcpData: {
				transactionId: string;
				payPalTotal: string;
				payPalFee: string;
				payPalPayout: string;
			};

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				const orderId = await orderReceived.getOrderNumber();				
				const transactionId =
						( await wooCommerceApi.getOrder( orderId ) ).transaction_id;

				const payPalSellerReceivableBreakdown =
					await payPalApi.getSellerReceivableBreakdown(
						transactionId,
						testOrder
					);
					
				const payPalTotal = payPalSellerReceivableBreakdown?.gross_amount?.value;
				const payPalFee = payPalSellerReceivableBreakdown?.paypal_fee?.value;
				const payPalPayout = payPalSellerReceivableBreakdown?.net_amount?.value;

				pcpData = {
					transactionId,
					payPalTotal,
					payPalFee,
					payPalPayout,
				};

				
				if( payPalTotal ) { // can be 0 for free trial or free orders
					await orderReceived.assertTotalEqualsPayPalTotal( payPalTotal, testOrder.currency );
				}
			} );

			await test.step( `Assert details on order edit page`, async () => {
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails( testOrder, pcpData );
			} );
		}
	);
};
