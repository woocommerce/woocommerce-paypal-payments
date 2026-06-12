/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test, Product } from '../../../utils';

export const transactionsOnProduct = ( testOrder: ShopOrder ) => {
	const { products, payment, merchant, isPayNowEnabled } = testOrder;

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
			const { type: productType, variationToSelect } = products[ 0 ];
			const { title: gatewayTitle, shortcut: gatewayShortcut } = payment.gateway;
			await test.step( `Visit product page and assert ${ gatewayTitle } button visibility`, async () => {
				await product.visit( products[ 0 ].slug );
				await expect(
					product.payPalUi.fundingSourceButton( gatewayShortcut ),
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
			
			await test.step( `Make payment with ${ gatewayTitle } assert order completed`, async () => {
				await product.payPalUi.makePayment( { merchant, payment } );
				if( isPayNowEnabled === false ) {
					await checkout.completeOrderFromProduct( testOrder );
				}
				await orderReceived.assertOrderDetails( testOrder );
			} );
			
			
			await test.step( `Assert details on order edit page`, async () => {
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
			} );
		}
	);
};
const assertPaymentBlockedBeforeVariationSelected = async (
	product: Product,
	gatewayTitle: string,
	gatewayShortcut: string,
) => {
	await test.step( `Assert payment impossible with ${ gatewayTitle } before selecting variations`, async () => {
		await expect(
			product.payPalUi.payPalGatewayContainer(),
			'Assert PayPal gateway container is disabled before selecting variations',
		).toHaveClass( 'ppcp-disabled' );

		product.page.once( 'dialog', async ( dialog ) => {
			expect(
				dialog.message(),
				`Assert dialog on clicking ${ gatewayTitle } button without selected variations`,
			).toBe(
				'Please select some product options before adding this product to your cart.'
			);
			await dialog.accept();
		} );
		await product.payPalUi.fundingSourceButton( gatewayShortcut ).click( { force: true } );
	} );
};

const assertPaymentEnabledAfterVariationSelected = async (
	product: Product,
	gatewayTitle: string,
	gatewayShortcut: string,
	variationToSelect: Record< string, string >,
) => {
	await test.step( `Assert ${ gatewayTitle } button enabled after selecting variations`, async () => {
		await product.selectVariation( variationToSelect );

		await expect(
			product.payPalUi.payPalGatewayContainer(),
			'Assert PayPal gateway container is enabled after selecting variations',
		).not.toHaveClass( 'ppcp-disabled' );

		await expect(
			product.payPalUi.fundingSourceButton( gatewayShortcut ),
			`Assert ${ gatewayTitle } button is visible after selecting variations`,
		).toBeVisible();
	} );
};

