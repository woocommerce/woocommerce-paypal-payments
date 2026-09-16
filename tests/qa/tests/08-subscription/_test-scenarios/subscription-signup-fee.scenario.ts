/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

/**
 * Reads the `_subscription_sign_up_fee` meta value off a subscription product.
 *
 * @param product
 */
const getSignUpFee = ( product: WooCommerce.CreateProduct ): number => {
	const meta = product.meta_data?.find(
		( item ) => item.key === '_subscription_sign_up_fee'
	);
	return meta ? parseFloat( meta.value ) : 0;
};

/**
 * Runs the checkout + renewal flow and asserts the sign-up fee is charged once on the
 * initial order and excluded from the renewal order.
 *
 * WooCommerce Subscriptions merges the sign-up fee straight into the product's line
 * subtotal for the first payment rather than exposing it as a separate WC_Order_Item_Fee
 * (confirmed live: the checkout page's "Subtotal:" row itself reads regular_price +
 * sign_up_fee, not the two shown separately) - so neither assertOrderDetails( testOrder )
 * nor countTotals()'s generic fees handling model it correctly. Building the expected
 * initial total with a product whose regular_price already includes the fee (matching
 * what the store actually charges tax/shipping on), and asserting the REST order total
 * directly rather than the checkout page's per-row breakdown.
 *
 * @param testOrder
 * @param root0
 * @param root0.utils
 * @param root0.checkout
 * @param root0.orderReceived
 * @param root0.wooCommerceApi
 * @param root0.pcpApi
 * @param root0.wooCommerceSubscriptionEdit
 */
const runSignUpFeeAssertions = async (
	testOrder: ShopOrder,
	{
		utils,
		checkout,
		orderReceived,
		wooCommerceApi,
		pcpApi,
		wooCommerceSubscriptionEdit,
	}
) => {
	const { payment, products, merchant } = testOrder;
	const [ product ] = products;
	const signUpFee = getSignUpFee( product );

	test.setTimeout( 2.5 * 60_000 );

	await test.step( `Add sign-up fee product to the cart, check out`, async () => {
		await utils.fillVisitorsCart( products );
		await checkout.visit();
		await checkout.completeCheckoutDetails( testOrder );
		await checkout.payPalUi.makePayment( {
			merchant,
			payment,
			isPayPalSubscription: payment.isPayPalSubscription,
		} );
		// Total can't be asserted generically - see comment above. Waiting on the heading
		// directly instead of calling assertOrderDetails(), since this repo's OrderReceived
		// override (tests/qa/utils/frontend/order-received.ts) unconditionally dereferences
		// order.payment.gateway.shortcut and isn't safe to call without an order.
		await expect(
			orderReceived.heading(),
			'Assert Order Received page heading'
		).toBeVisible();
	} );

	let orderId: number;
	let subscriptionId: number;

	await test.step( `Assert initial order total includes the sign-up fee`, async () => {
		const initialTotals = await countTotals( {
			...testOrder,
			products: [
				{
					...product,
					regular_price: (
						parseFloat( product.regular_price ) + signUpFee
					).toFixed( 2 ),
				},
			],
		} );
		const expectedInitialTotal = initialTotals.order.toFixed( 2 );

		orderId = await orderReceived.getOrderNumber();
		const order = await wooCommerceApi.getOrder( orderId );
		await expect(
			order.total,
			`Assert initial order total (incl. ${ signUpFee } sign-up fee) = ${ expectedInitialTotal }`
		).toBe( expectedInitialTotal );

		subscriptionId = await orderReceived.getSubscriptionNumber();
	} );

	await test.step( `Trigger renewal, assert renewal order total excludes the sign-up fee`, async () => {
		const subscriptionJson =
			await wooCommerceApi.getSubscription( subscriptionId );

		if ( await pcpApi.isPayPalSubscription( subscriptionJson ) ) {
			await pcpApi.triggerPayPalSubscriptionRenewal( subscriptionId );
		} else {
			await wooCommerceSubscriptionEdit.triggerSubscriptionRenewal(
				subscriptionId
			);
		}

		const renewalOrderIds =
			await wooCommerceApi.getSubscriptionRenewalOrderIds(
				subscriptionId
			);
		await expect(
			renewalOrderIds,
			'Assert one renewal order is created'
		).toHaveLength( 1 );

		const renewalTotals = await countTotals( testOrder );
		const expectedRenewalTotal = renewalTotals.order.toFixed( 2 );

		const renewalOrder = await wooCommerceApi.getOrder(
			renewalOrderIds[ 0 ]
		);
		await expect(
			renewalOrder.total,
			`Assert renewal order total (sign-up fee not charged again) = ${ expectedRenewalTotal }`
		).toBe( expectedRenewalTotal );
	} );
};

const testSignUpFeeOrderGuest = ( testOrder: ShopOrder ) => {
	const { title, payment, customer: guest } = testOrder;

	test.describe( () => {
		test.beforeAll( async ( { wooCommerceUtils } ) => {
			const previousEmails = [
				guest.email,
				payment.payPalAccount?.email,
			];
			for ( const email of previousEmails ) {
				await wooCommerceUtils.deleteCustomer( { email } );
			}
		} );

		test(
			title,
			annotateVisitor( guest ),
			async ( {
				utils,
				checkout,
				orderReceived,
				wooCommerceApi,
				pcpApi,
				wooCommerceSubscriptionEdit,
			} ) =>
				runSignUpFeeAssertions( testOrder, {
					utils,
					checkout,
					orderReceived,
					wooCommerceApi,
					pcpApi,
					wooCommerceSubscriptionEdit,
				} )
		);
	} );
};

const testSignUpFeeOrderCustomer = ( testOrder: ShopOrder ) => {
	const { title, customer } = testOrder;

	test.describe( () => {
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			title,
			annotateVisitor( customer ),
			async ( {
				utils,
				checkout,
				orderReceived,
				wooCommerceApi,
				pcpApi,
				wooCommerceSubscriptionEdit,
			} ) =>
				runSignUpFeeAssertions( testOrder, {
					utils,
					checkout,
					orderReceived,
					wooCommerceApi,
					pcpApi,
					wooCommerceSubscriptionEdit,
				} )
		);
	} );
};

export const testSubscriptionSignUpFee = {
	testSignUpFeeOrderGuest,
	testSignUpFeeOrderCustomer,
};
