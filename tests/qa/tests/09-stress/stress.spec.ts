/**
 * Internal dependencies
 */
import { annotateVisitor, expect, test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	payments,
	products as productsBase,
	flatRate,
	taxSettings,
	guests,
} from '../../resources';

const { payPal } = payments;
const testProduct = productsBase.simpleWithStock;
const testOrder: WooCommerce.ShopOrder = {
	shipping: flatRate,
	taxes: taxSettings.including,
	customer: guests.usa,
	products: [ testProduct ],
	payment: payPal,
	currency: 'USD',
	merchant: merchants.usa,
};

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		enableClassicPages: true,
		products: [ testProduct ],
	} );
	// Restore stock_quantity before each test
	const { id: productId } = await wooCommerceApi.getProductBySlug(
		testProduct.slug
	);
	await wooCommerceApi.updateProduct( productId, { stock_quantity: 1000 } );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.gateway.id ]: { id: payPal.gateway.id, enabled: true },
	} );
} );

for ( let i = 1; i <= 500; i++ ) {
	const { products, payment, merchant, customer } = testOrder;
	const productSlug = products[ 0 ].slug;
	test(
		`Transaction - Classic checkout - PayPal - Stress test #${ i }`,
		annotateVisitor( customer ),
		async ( {
			product,
			classicCart,
			classicCheckout,
			wooCommerceApi,
			orderReceived,
		} ) => {
			let orderProduct = await wooCommerceApi.getProductBySlug(
				productSlug
			);
			const expectedProductStock = orderProduct.stock_quantity - 1;

			await product.visit( productSlug );
			await product.addToCartButton().click();
			await product.viewCartLink().click();
			await classicCart.proceedToCheckoutButton().click();

			await classicCheckout.completeCheckoutDetails( testOrder );
			await classicCheckout.payPalUi.makePayment( { merchant, payment } );
			await orderReceived.assertOrderDetails( testOrder );

			// Verify order via WooCommerce API
			const orderId = await orderReceived.getOrderNumber();
			const { transaction_id: transactionId, status: orderStatus } =
				await wooCommerceApi.getOrder( orderId );

			await expect(
				transactionId,
				`Assert transaction ID ${ transactionId } is defined`
			).toBeDefined();
			await expect( orderStatus, `Assert order #${ orderId } status is processing` ).toBe(
				'processing'
			);

			// Verify product stock quantity
			orderProduct = await wooCommerceApi.getProductBySlug( productSlug );
			await expect(
				orderProduct.stock_quantity,
				`Assert product stock quantity for ${ productSlug } is ${ expectedProductStock }`
			).toBe( expectedProductStock );

			// Verify order notes
			const orderNotes = await wooCommerceApi.getOrderNotes( orderId );
			const paymentViaNote = orderNotes.filter( ( orderNote ) =>
				orderNote.note.includes(
					`Payment via PayPal (${ transactionId }).`
				)
			);
			const transactionNote = orderNotes.filter( ( orderNote ) =>
				orderNote.note.includes(
					`PayPal transaction ID: ${ transactionId }`
				)
			);
			await expect(
				paymentViaNote.length,
				'Assert payment via order notes quantity is 1'
			).toBe( 1 );
			await expect(
				transactionNote.length,
				'Assert transaction order notes quantity is 1'
			).toBe( 1 );
		}
	);
}
