/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import { annotateVisitor, expect, test } from '../../../utils';

/**
 * Diagnostic for PCP-2514, snapshot-then-renew: records PayPal's token list once
 * right before the renewal, without waiting, then the renewal outcome, whether
 * the WC card is still saved, and when PayPal lists the token. Never fails the test.
 *
 * @param root0                The diagnostic inputs.
 * @param root0.wooCommerceApi The WooCommerce API client.
 * @param root0.payPalApi      The PayPal API client.
 * @param root0.merchant       The merchant that owns the order.
 * @param root0.purchasedAt    Timestamp (ms) when the purchase completed.
 */
const savedPaymentMethodDiagnostics = ( {
	wooCommerceApi,
	payPalApi,
	merchant,
	purchasedAt,
} ) => {
	const log = [];
	let vault;
	const note = ( entry ) =>
		log.push( { elapsedMs: Date.now() - purchasedAt, ...entry } );
	const listTokenIds = async () =>
		(
			await payPalApi.getVaultTokensForCustomer(
				vault.customer.id,
				merchant
			)
		).map( ( token ) => token.id );
	const safely = async ( fn ) => {
		try {
			await fn();
		} catch ( error ) {
			note( { error: String( error ) } );
		}
	};

	return {
		beforeRenewal: ( orderId: number ) =>
			safely( async () => {
				const payPalOrderId = await payPalApi.getOrderIdFromWooCommerce(
					await wooCommerceApi.getOrder( orderId )
				);
				const payPalOrder = await payPalApi.getOrder(
					payPalOrderId,
					merchant
				);
				const [ sourceName ] = Object.keys(
					payPalOrder.payment_source ?? {}
				);
				vault =
					payPalOrder.payment_source?.[ sourceName ]?.attributes
						?.vault;
				note( {
					phase: 'before renewal',
					payPalOrderId,
					sourceName,
					vault,
				} );
				if ( vault?.customer?.id ) {
					note( {
						phase: 'before renewal',
						tokenIds: await listTokenIds(),
					} );
				}
			} ),
		afterRenewal: ( renewalOrderIds: number[], isCardSavedInWc: boolean ) =>
			safely( async () => {
				for ( const id of renewalOrderIds ) {
					const { status } = await wooCommerceApi.getOrder( id );
					note( {
						phase: 'after renewal',
						renewalOrderId: id,
						status,
					} );
				}
				note( { phase: 'after renewal', isCardSavedInWc } );
				for (
					let attempt = 1;
					vault?.customer?.id && attempt <= 6;
					attempt++
				) {
					const tokenIds = await listTokenIds();
					note( { phase: 'after renewal', attempt, tokenIds } );
					if ( tokenIds.includes( vault.id ) ) {
						break;
					}
					await new Promise( ( resolve ) =>
						setTimeout( resolve, 5_000 )
					);
				}
			} ),
		attach: async () => {
			console.log( `[saved-payment-method] ${ JSON.stringify( log ) }` );
			await test.info().attach( 'saved-payment-method-diagnostics', {
				body: JSON.stringify( log, null, 2 ),
				contentType: 'application/json',
			} );
		},
	};
};

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
				customerPaymentMethods,
				wooCommerceApi,
				payPalApi,
				pcpApi,
				wooCommerceOrderEdit,
				wooCommerceSubscriptionEdit,
			} ) => {
				test.fixme(
					products[ 0 ].name.includes( 'Free trial'),
					'For free trial subscriptions the vaulting component is not displayed',
				)
				test.setTimeout( 2.5 * 60_000 );
				const { title: gatewayTitle } = payment.gateway;
				
				await test.step( `Add product(s) to the cart`, async () => {
					await utils.fillVisitorsCart( products );
				} );

				await test.step( `Visit Checkout, make payment with ${ gatewayTitle }`, async () => {
					await classicCheckout.visit();
					await classicCheckout.completeCheckoutDetails( testOrder );
					await classicCheckout.payPalUi.makePayment( {
						merchant,
						payment,
						isPayPalSubscription: payment.isPayPalSubscription,
					} );
				} );
				const purchasedAt = Date.now();

				let orderId: number;
				let subscriptionId: number;
				let subscriptionJson: WooCommerce.Subscription;
				let payPalPaymentDetails: PayPalPaymentDetails = {};

				await test.step( `Assert order received`, async () => {
					await orderReceived.assertOrderDetails( testOrder );
					await orderReceived.assertNoErrors();

					orderId = await orderReceived.getOrderNumber();				
					const transactionId =
						( await wooCommerceApi.getOrder( orderId ) ).transaction_id;
					subscriptionId =
						await orderReceived.getSubscriptionNumber();
					subscriptionJson =
						await wooCommerceApi.getSubscription( subscriptionId );

					if (
						! ( await pcpApi.isPayPalSubscription( subscriptionJson ) )
					) {
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
					}
				} );

				let relatedParentOrder;
				let relatedSubscription;
				let relatedRenewalOrders = [];
				const total = await countTotals( testOrder );

				await test.step( `Assert details on order edit page`, async () => {
					relatedParentOrder = {
						id: orderId,
						relationship: 'Parent Order',
						status: 'Processing',
						total: total.order,
					};

					relatedSubscription = {
						id: subscriptionId,
						relationship: 'Subscription',
						status: 'Active',
						total: total.order,
					};

					await wooCommerceOrderEdit.visit( orderId );
					await wooCommerceOrderEdit.assertOrderDetails(
						testOrder,
						payPalPaymentDetails,
					);
					await wooCommerceOrderEdit.assertRelatedOrders(
						[ relatedSubscription ],
						currency
					);
				} );

				await test.step( `Assert details on subscription edit page`, async () => {
					await wooCommerceSubscriptionEdit.visit( subscriptionId );
					await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
						testOrder
					);
					await wooCommerceSubscriptionEdit.assertRelatedOrders(
						[ relatedParentOrder ],
						currency
					);
				} );

				const isWcSubscription =
					! ( await pcpApi.isPayPalSubscription( subscriptionJson ) );
				const diagnostics = savedPaymentMethodDiagnostics( {
					wooCommerceApi,
					payPalApi,
					merchant,
					purchasedAt,
				} );
				if ( isWcSubscription ) {
					test.setTimeout( test.info().timeout + 60_000 );
					await test.step( 'Diagnose: saved payment method before renewal', async () => {
						await diagnostics.beforeRenewal( orderId );
					} );
				}



				await test.step( `Subscription renewal`, async () => {
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

					// The renewal order notes carry the payment result which the order edit assertions don't show.
					for ( const id of renewalOrderIds ) {
						const notes = await wooCommerceApi.getOrderNotes( id );
						await test.info().attach( `renewal-notes-${ id }`, {
							body: JSON.stringify( notes, null, 2 ),
							contentType: 'application/json',
						} );
					}

					for ( const renewalOrderId of renewalOrderIds ) {
						relatedRenewalOrders.push( {
							id: renewalOrderId,
							relationship: 'Renewal Order',
							status: 'Processing',
							total: total.order,
						} );
					}
				} );

				if ( isWcSubscription ) {
					await test.step( 'Diagnose: saved payment method after renewal', async () => {
						await customerPaymentMethods.visit();
						const isCardSavedInWc =
							await customerPaymentMethods.isSavedPaymentMethod(
								payment
							);
						await diagnostics.afterRenewal(
							relatedRenewalOrders.map( ( order ) => order.id ),
							isCardSavedInWc
						);
						await diagnostics.attach();
					} );
				}

				await test.step( `Assert related orders on order edit page`, async () => {
					await wooCommerceOrderEdit.visit( orderId );
					await wooCommerceOrderEdit.assertRelatedOrders(
						[ relatedSubscription, ...relatedRenewalOrders ],
						currency
					);
				} );

				await test.step( `Assert related orders on subscription edit page`, async () => {
					await wooCommerceSubscriptionEdit.visit( subscriptionId );
					await wooCommerceSubscriptionEdit.assertRelatedOrders(
						[ relatedParentOrder, ...relatedRenewalOrders ],
						currency
					);
				} );

				await test.step( `Assert related orders on customer subscription page`, async () => {
					await customerSubscriptions.visit( subscriptionId );
					await customerSubscriptions.assertRelatedOrders(
						[ relatedParentOrder, ...relatedRenewalOrders ],
						currency
					);
				} );
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
					isPayPalSubscription: payment.isPayPalSubscription,
				} );
				await orderReceived.assertOrderDetails( testOrder );

				const orderId = await orderReceived.getOrderNumber();
				const subscriptionId =
					await orderReceived.getSubscriptionNumber();
				const subscriptionJson =
					await wooCommerceApi.getSubscription( subscriptionId );

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
