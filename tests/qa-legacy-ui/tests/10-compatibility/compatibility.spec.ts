/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	storeConfigClassic,
	payPal,
	orders,
	merchants,
	pcpConfigDefault,
} from '../../resources';

test.describe( 'Compatibility', () => {
	test.describe( 'Theme 2023', () => {
		test.beforeAll( async ( { utils } ) => {
			await utils.configureStore( storeConfigClassic );
			await utils.configurePcp( pcpConfigDefault );
			await utils.requestUtils.activateTheme( 'twentytwentythree' );
		} );

		test( 'PCP-2050 | Theme 2023 - Transaction - PayPal - Product - Default order @theme2023', async ( {
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			ppapi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			const tested = {
				...orders.default,
				payment: payPal,
				transactionId: undefined,
				payPalFee: undefined,
				payPalPayout: undefined,
			};

			const summaryHeadingKey = ( heading ) =>
				orderReceived.page
					.locator(
						'.wc-block-order-confirmation-summary-list-item__key'
					)
					.getByText( heading );
			const summaryHeadingItem = ( heading ) =>
				orderReceived.page.locator( 'li', {
					has: summaryHeadingKey( heading ),
				} );
			const summaryHeadingValue = ( heading ) =>
				summaryHeadingItem( heading ).locator(
					'.wc-block-order-confirmation-summary-list-item__value'
				);

			// Preconditions
			await utils.fillVisitorsCart( tested.products );

			// Test
			await classicCheckout.visit();

			await expect( classicCheckout.ppui.payPalButton() ).toBeVisible();
			await classicCheckout.selectShippingMethod(
				tested.shipping.settings.title
			);
			await classicCheckout.fillCheckoutForm( tested.customer );
			await classicCheckout.ppui.makeClassicPayment( {
				merchant: tested.merchant,
				payment: tested.payment,
			} );

			await expect(
				orderReceived.page.getByText(
					'Thank you. Your order has been received.'
				)
			).toBeVisible();
			await expect( summaryHeadingValue( 'Payment:' ) ).toHaveText(
				tested.payment.gatewayName
			);

			const orderId = await summaryHeadingValue( 'Order #:' ).innerText();
			const orderJson = await wooCommerceApi.getOrder(
				Number( orderId )
			);

			const pcpData = {
				transactionId: orderJson.transaction_id,
				payPalFee: await ppapi.getFee(
					orderJson.transaction_id,
					tested
				),
				payPalPayout: await ppapi.getPayout(
					orderJson.transaction_id,
					tested
				),
			};

			await ppapi.assertOrder( orderJson, tested );
			await ppapi.assertPayment( orderJson.transaction_id, tested );
			await wooCommerceOrderEdit.visit( Number( orderId ) );
			await wooCommerceOrderEdit.assertOrderDetails(
				tested,
				pcpData
			);
		} );

		test.afterAll( async ( { utils } ) => {
			await utils.requestUtils.activateTheme( 'storefront' );
		} );
	} );
} );
