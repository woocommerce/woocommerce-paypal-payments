/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

export const testSubscriptionRenewal = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant, currency } =
		testOrder;

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
				classicCheckout,
				orderReceived,
				customerSubscriptions,
				wooCommerceApi,
				payPalApi,
				pcpApi,
				wooCommerceOrderEdit,
				wooCommerceSubscriptionEdit,
			} ) => {
				test.setTimeout( 2.5 * 60_000 );
				// Precondition: purchase test subscription
				await utils.fillVisitorsCart( products );
				await classicCheckout.visit();
				await classicCheckout.completeCheckoutDetails( testOrder );
				await classicCheckout.payPalUi.makePayment( {
					merchant,
					payment,
				} );
				await orderReceived.assertOrderDetails( testOrder );

				const orderId = await orderReceived.getOrderNumber();
				const { transaction_id: transactionId } =
					await wooCommerceApi.getOrder( orderId );
				const subscriptionId =
					await orderReceived.getSubscriptionNumber();
				const subscriptionJson = await wooCommerceApi.getSubscription(
					subscriptionId
				);

				const total = await countTotals( testOrder );

				const relatedParentOrder = {
					id: orderId,
					relationship: 'Parent Order',
					status: 'Processing',
					total: total.order,
				};

				const relatedSubscription = {
					id: subscriptionId,
					relationship: 'Subscription',
					status: 'Active',
					total: total.order,
				};

				let pcpData = {};
				// TODO: clarify expected paypal data
				if (
					! ( await pcpApi.isPayPalSubscription( subscriptionJson ) )
				) {
					pcpData = {
						transactionId,
						payPalFee: await payPalApi.getFee(
							transactionId,
							testOrder
						),
						payPalPayout: await payPalApi.getPayout(
							transactionId,
							testOrder
						),
					};
				}

				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails(
					testOrder,
					pcpData
				);
				await wooCommerceOrderEdit.assertRelatedOrders(
					[ relatedSubscription ],
					currency
				);

				await wooCommerceSubscriptionEdit.visit( subscriptionId );
				await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
					testOrder
				);
				await wooCommerceSubscriptionEdit.assertRelatedOrders(
					[ relatedParentOrder ],
					currency
				);

				// Subscription renewal
				if ( await pcpApi.isPayPalSubscription( subscriptionJson ) ) {
					await pcpApi.triggerPayPalSubscriptionRenewal(
						subscriptionId
					);
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

				const relatedRenewalOrders = [];

				for ( const renewalOrderId of renewalOrderIds ) {
					relatedRenewalOrders.push( {
						id: renewalOrderId,
						relationship: 'Renewal Order',
						status: 'Processing',
						total: total.order,
					} );
				}

				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertRelatedOrders(
					[ relatedSubscription, ...relatedRenewalOrders ],
					currency
				);

				await wooCommerceSubscriptionEdit.visit( subscriptionId );
				await wooCommerceSubscriptionEdit.assertRelatedOrders(
					[ relatedParentOrder, ...relatedRenewalOrders ],
					currency
				);

				await customerSubscriptions.visit( subscriptionId );
				await customerSubscriptions.assertRelatedOrders(
					[ relatedParentOrder, ...relatedRenewalOrders ],
					currency
				);
			}
		);
	} );
};

export const testFreeTrialSubscriptionRenewal = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, currency, merchant } =
		testOrder;

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
				classicCheckout,
				orderReceived,
				customerSubscriptions,
				wooCommerceApi,
				pcpApi,
				wooCommerceOrderEdit,
				wooCommerceSubscriptionEdit,
			} ) => {
				test.setTimeout( 2 * 60_000 );
				// Precondition: purchase test subscription
				await utils.fillVisitorsCart( products );
				await classicCheckout.visit();
				await classicCheckout.completeCheckoutDetails( testOrder );
				await classicCheckout.payPalUi.makePayment( {
					merchant,
					payment,
				} );
				await orderReceived.assertOrderDetails( testOrder );

				const orderId = await orderReceived.getOrderNumber();
				const subscriptionId =
					await orderReceived.getSubscriptionNumber();
				const subscriptionJson = await wooCommerceApi.getSubscription(
					subscriptionId
				);

				const freeTrialTotal = await countTotals( testOrder );
				// Assert free-trial test order with 0 price and shipping
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails( testOrder );

				// For free-trial subscription product set trial length = 0 so for renewal order it's not counted as 0 price
				testOrder.products[ 0 ] = await setSubscriptionTrialLength(
					products[ 0 ],
					'0'
				);
				// Count order totals for subscription and upcoming renewal orders
				const total = await countTotals( testOrder );

				const relatedParentOrder = {
					id: orderId,
					relationship: 'Parent Order',
					status: 'Processing',
					total: freeTrialTotal.order,
				};

				const relatedSubscription = {
					id: subscriptionId,
					relationship: 'Subscription',
					status: 'Active',
					total: total.order,
				};
				await wooCommerceOrderEdit.assertRelatedOrders(
					[ relatedSubscription ],
					currency
				);

				await wooCommerceSubscriptionEdit.visit( subscriptionId );
				await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
					testOrder
				);
				await wooCommerceSubscriptionEdit.assertRelatedOrders(
					[ relatedParentOrder ],
					currency
				);

				// Subscription renewal
				if ( await pcpApi.isPayPalSubscription( subscriptionJson ) ) {
					await pcpApi.triggerPayPalSubscriptionRenewal(
						subscriptionId
					);
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

				const relatedRenewalOrders = [];

				for ( const renewalOrderId of renewalOrderIds ) {
					relatedRenewalOrders.push( {
						id: renewalOrderId,
						relationship: 'Renewal Order',
						status: 'Processing',
						total: total.order,
					} );
				}

				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertRelatedOrders(
					[ relatedSubscription, ...relatedRenewalOrders ],
					currency
				);

				await wooCommerceSubscriptionEdit.visit( subscriptionId );
				await wooCommerceSubscriptionEdit.assertRelatedOrders(
					[ relatedParentOrder, ...relatedRenewalOrders ],
					currency
				);

				await customerSubscriptions.visit( subscriptionId );
				await customerSubscriptions.assertRelatedOrders(
					[ relatedParentOrder, ...relatedRenewalOrders ],
					currency
				);
			}
		);
	} );
};

const setSubscriptionTrialLength = (
	product: WooCommerce.CreateProduct,
	value = '0'
) => {
	if ( ! product.meta_data ) {
		return product;
	}

	return {
		...product,
		meta_data: product.meta_data.map( ( item ) =>
			item.key === '_subscription_trial_length'
				? { ...item, value }
				: item
		),
	};
};
