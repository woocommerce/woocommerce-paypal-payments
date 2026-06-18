/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
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
					await checkout.completeOrderFromProduct( testOrder );
				}
			} );
				
			let orderId: number;
			let payPalPaymentDetails: PayPalPaymentDetails;

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				orderId = await orderReceived.getOrderNumber();				
				const transactionId =
						( await wooCommerceApi.getOrder( orderId ) ).transaction_id;

				payPalPaymentDetails = await payPalApi.getPayPalPaymentDetails(
					transactionId,
					testOrder,
				);

				if( payPalPaymentDetails.amount !== '0' ) { // can be 0 for free trial or free orders
					await orderReceived.assertTotalEqualsPayPalTotal(
						payPalPaymentDetails.amount,
						testOrder.currency
					);
				}
			} );

			await test.step( `Assert details on order edit page`, async () => {
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails( testOrder, payPalPaymentDetails );
			} );
		}
	);
};

export const getTestedGatewayContainer = ( product: Product, gatewayShortcut: string ) => {
	return gatewayShortcut === 'googlepay'
		? product.payPalUi.googlePayGatewayContainer()
		: product.payPalUi.payPalGatewayContainer();
}

export const getTestedGatewayButton = ( product: Product, gatewayShortcut: string ) => {
	return gatewayShortcut === 'googlepay'
		? product.payPalUi.googlePayButton()
		: product.payPalUi.fundingSourceButton( gatewayShortcut );
}

export const assertPaymentBlockedBeforeVariationSelected = async (
	product: Product,
	gatewayTitle: string,
	gatewayShortcut: string,
) => {
	await test.step( `Assert payment impossible with ${ gatewayTitle } before selecting variations`, async () => {
		await expect(
			getTestedGatewayContainer( product, gatewayShortcut ),
			'Assert PayPal gateway container is disabled before selecting variations',
		).toContainClass( 'ppcp-disabled' );

		product.page.once( 'dialog', async ( dialog ) => {
			expect(
				dialog.message(),
				`Assert dialog on clicking ${ gatewayTitle } button without selected variations`,
			).toBe(
				'Please select some product options before adding this product to your cart.'
			);
			await dialog.accept();
		} );
		await getTestedGatewayButton( product, gatewayShortcut ).click( { force: true } );
	} );
};

export const assertPaymentEnabledAfterVariationSelected = async (
	product: Product,
	gatewayTitle: string,
	gatewayShortcut: string,
	variationToSelect: Record< string, string >,
) => {
	await test.step( `Assert ${ gatewayTitle } button enabled after selecting variations`, async () => {
		await product.selectVariation( variationToSelect );

		await expect(
			getTestedGatewayContainer( product, gatewayShortcut ),
			'Assert PayPal gateway container is enabled after selecting variations',
		).not.toContainClass( 'ppcp-disabled' );

		await expect(
			getTestedGatewayButton( product, gatewayShortcut ),
			`Assert ${ gatewayTitle } button is visible after selecting variations`,
		).toBeVisible();
	} );
};

