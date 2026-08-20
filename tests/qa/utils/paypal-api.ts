/**
 * External dependencies
 */
import { APIRequestContext, expect } from '@playwright/test';
import {
	createAuthHeader,
	getLast4CardDigits,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, Pcp, ShopOrder } from '../resources';

/**
 * Class for PayPal API
 */

export class PayPalApi {
	request: APIRequestContext;
	private sandboxBaseUrl = 'https://api-m.sandbox.paypal.com';
	private apiBaseUrl = `${ this.sandboxBaseUrl }/v2`;

	constructor( { request } ) {
		this.request = request;
	}

	private apiRequest = async (
		requestType: 'get' | 'post' | 'put' | 'delete',
		endPoint: string,
		merchant: Pcp.Merchant,
		data?: any
	) => {
		try {
			const token = await this.getAuthToken( merchant );
			const response = await this.request[ requestType ](
				this.apiBaseUrl + endPoint,
				{
					headers: {
						Authorization: `Bearer ${ token }`,
					},
					data,
				}
			);

			if ( ! ( await response.ok() ) ) {
				const body = await response.text();
				throw new Error(
					`Request failed with status ${ await response.status() } for ${ endPoint }: ${ body }`
				);
			}

			return await response.json();
		} catch ( error ) {
			console.error( 'An error occurred:', error );
			throw error; // Re-throw the error to propagate it further if needed
		}
	};

	/**
	 * Gets payment info by PayPal Transaction ID
	 *
	 * @param merchant - { client_id: '...', client_secret: '...' }
	 */
	getAuthToken = async ( merchant: Pcp.Merchant ) => {
		const response = await this.request.post(
			`${ this.sandboxBaseUrl }/v1/oauth2/token`,
			{
				headers: createAuthHeader(
					merchant.client_id,
					merchant.client_secret
				),
				form: { grant_type: 'client_credentials' },
			}
		);
		const json = await response.json();
		if ( ! response.ok() ) {
			throw new Error( `getAuthToken failed with status ${ response.status() }: ${ JSON.stringify( json ) }` );
		}
		return json.access_token;
	};

	/**
	 * Gets payment info by PayPal Transaction ID
	 *
	 * @param resourceId - PayPal transaction ID
	 * @param merchant   - { client_id: '...', client_secret: '...' }
	 */
	getCapturedPayment = async (
		resourceId: string,
		merchant: Pcp.Merchant
	) => {
		return await this.apiRequest(
			'get',
			`/payments/captures/${ resourceId }`,
			merchant
		);
	};

	/**
	 * Gets payment info for authorized transactions by PayPal Transaction ID
	 *
	 * @param resourceId - PayPal transaction ID
	 * @param merchant   - { client_id: '...', client_secret: '...' }
	 */
	getAuthorizedPayment = async (
		resourceId: string,
		merchant: Pcp.Merchant
	) => {
		return await this.apiRequest(
			'get',
			`/payments/authorizations/${ resourceId }`,
			merchant
		);
	};

	/**
	 * Gets order info by PayPal Order ID
	 *
	 * @param resourceId - PayPal order ID
	 * @param merchant   - { client_id: '...', client_secret: '...' }
	 */
	getOrder = async ( resourceId: string, merchant: Pcp.Merchant ) => {
		return await this.apiRequest(
			'get',
			`/checkout/orders/${ resourceId }`,
			merchant
		);
	};

	/**
	 * Gets order info by PayPal Order ID
	 *
	 * @param resourceId - PayPal order ID
	 * @param merchant   - { client_id: '...', client_secret: '...' }
	 */
	getRefund = async ( resourceId: string, merchant: Pcp.Merchant ) => {
		return await this.apiRequest(
			'get',
			`/payments/refunds/${ resourceId }`,
			merchant
		);
	};

	/**
	 * Lists recent webhook events for the merchant's account
	 * (GET /v1/notifications/webhooks-events). Temporary diagnostic for the
	 * ngrok webhook-delivery investigation: confirms whether PayPal actually
	 * generated an event at all, independent of whether the registered
	 * webhook URL could receive it.
	 *
	 * @param merchant - { client_id: '...', client_secret: '...' }
	 */
	getWebhookEvents = async ( merchant: Pcp.Merchant ) => {
		const token = await this.getAuthToken( merchant );
		const response = await this.request.get(
			`${ this.sandboxBaseUrl }/v1/notifications/webhooks-events`,
			{
				headers: {
					Authorization: `Bearer ${ token }`,
				},
			}
		);

		if ( ! ( await response.ok() ) ) {
			throw new Error(
				`getWebhookEvents failed with status ${ await response.status() }: ${ await response.text() }`
			);
		}

		const json = await response.json();
		return json.events ?? [];
	};

	/**
	 * Temporary diagnostic for the ngrok webhook-delivery investigation: logs
	 * whether PayPal generated any webhooks-events referencing the given
	 * resource id (e.g. a PayPal order id), regardless of whether the tunnel
	 * could actually deliver a webhook for it. Logs rather than asserts,
	 * since this is exploratory. Remove once the delivery question is settled.
	 *
	 * @param merchant   - { client_id: '...', client_secret: '...' }
	 * @param resourceId PayPal order/capture/authorization id to look for.
	 */
	logWebhookEventsForResource = async (
		merchant: Pcp.Merchant,
		resourceId: string
	) => {
		try {
			const events = await this.getWebhookEvents( merchant );
			const matches = events.filter( ( event ) =>
				JSON.stringify( event.resource ?? {} ).includes( resourceId )
			);
			console.log(
				`[ngrok-diag] PayPal webhooks-events: ${ events.length } total, ${ matches.length } matching resource ${ resourceId }`
			);
			matches.forEach( ( event ) =>
				console.log(
					`[ngrok-diag]   event id=${ event.id } type=${ event.event_type } create_time=${ event.create_time }`
				)
			);
		} catch ( error ) {
			console.error(
				'[ngrok-diag] Failed to fetch PayPal webhooks-events:',
				error
			);
		}
	};

	/**
	 * Gets PayPal order ID stored in WooCommerce meta_data
	 *
	 * @param wooCommerceOrderJson
	 */
	getOrderIdFromWooCommerce = async (
		wooCommerceOrderJson: WooCommerce.Order
	): Promise< string | undefined > => {
		const paypalOrderIdMeta = wooCommerceOrderJson.meta_data.find(
			( meta ) => meta.key === '_ppcp_paypal_order_id'
		);

		return paypalOrderIdMeta?.value;
	};

	/**
	 * Gets PayPal payment ID for different gateways
	 *
	 * @param payPalOrder
	 * @param payment
	 */
	getPaymentIdFromOrder = async ( payPalOrder, payment: Pcp.Payment ) => {
		const fundingSource = payment.gateway.shortcut;

		if ( fundingSource === 'pay_upon_invoice' ) {
			return '';
		}

		if ( fundingSource === 'oxxo' ) {
			return payPalOrder.purchase_units[ 0 ].payments.captures[ 0 ].id;
		}

		if ( payment.isAuthorized ) {
			return payPalOrder.purchase_units[ 0 ].payments.authorizations[ 0 ]
				.id;
		}

		return payPalOrder.purchase_units[ 0 ].payments.captures[ 0 ].id;
	};

	/**
	 * Fetches a payment (capture or authorization) from the PayPal API.
	 *
	 * @param paymentId    PayPal capture/authorization resource id.
	 * @param merchant     Credentials of the merchant that owns the resource.
	 * @param isAuthorized When true, query the authorizations endpoint instead of captures.
	 */
	getPayment = (
		paymentId: string,
		merchant: Pcp.Merchant,
		isAuthorized = false,
	) =>
		isAuthorized
			? this.getAuthorizedPayment( paymentId, merchant )
			: this.getCapturedPayment( paymentId, merchant );

	/**
	 *
	 * @param resourceId
	 * @param shopOrder
	 */
	getPayPalPaymentDetails = async (
		resourceId: string,
		shopOrder: ShopOrder,
	): Promise< PayPalPaymentDetails > => {
		const { merchant, payment } = shopOrder;
		const fundingSource = payment.gateway.shortcut;
		if ( fundingSource === 'pay_upon_invoice' ) {
			// PUI is not captured via PayPal payments endpoints
			return undefined;
		}
		const payPalPayment = await this.getPayment( resourceId, merchant, payment.isAuthorized );
		return {
			transactionId: resourceId,
			currency: payPalPayment.amount.currency_code,
			amount: payPalPayment.amount.value,
			grossAmount: payPalPayment.seller_receivable_breakdown?.gross_amount?.value,
			payPalFee: payPalPayment.seller_receivable_breakdown?.paypal_fee?.value,
			netAmount: payPalPayment.seller_receivable_breakdown?.net_amount?.value,
		};
	};

	// Assertions

	/**
	 * Asserts PayPal order for different funding sources
	 *
	 * @param wooCommerceOrderJson
	 * @param shopOrder
	 */
	assertOrder = async (
		wooCommerceOrderJson: WooCommerce.Order,
		shopOrder: ShopOrder
	) => {
		const { merchant, payment, customer } = shopOrder;
		const fundingSource = payment.gateway.shortcut;
		const payPalOrderId =
			await this.getOrderIdFromWooCommerce( wooCommerceOrderJson );

		await expect
			.soft(
				payPalOrderId,
				'"_ppcp_paypal_order_id" should be present in WooCommerce Order "meta_data"'
			)
			.toBeDefined();

		if ( ! payPalOrderId ) {
			return;
		}

		await expect.soft( payPalOrderId ).toMatch( /^[A-Z0-9]{17}$/ );

		const payPalOrder = await this.getOrder(
			payPalOrderId,
			merchant
		);

		if ( fundingSource !== 'oxxo' ) {
			const payPalPaymentId = await this.getPaymentIdFromOrder(
				payPalOrder,
				payment
			);
			await expect
				.soft( String( wooCommerceOrderJson.transaction_id ) )
				.toEqual( String( payPalPaymentId ) );
		}

		const expectedIntent = payment.isAuthorized
			? 'AUTHORIZE'
			: 'CAPTURE';
		await expect.soft( payPalOrder.intent ).toEqual( expectedIntent );

		switch ( fundingSource ) {
			case 'oxxo': {
				await expect.soft( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect
					.soft( payPalOrder.payment_source )
					.toHaveProperty( 'oxxo' );
				await expect
					.soft( payPalOrder.payment_source.oxxo.email )
					.toEqual( customer.email );
				break;
			}

			case 'acdc':
			case 'fastlane': {
				await expect.soft( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect
					.soft( payPalOrder.payment_source )
					.toHaveProperty( 'card' );
				await expect
					.soft( payPalOrder.payment_source.card.last_digits )
					.toEqual(
						getLast4CardDigits( payment.card.card_number )
					);
				break;
			}	

			case 'card':
				await expect.soft( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect
					.soft( payPalOrder.payment_source )
					.toHaveProperty( 'paypal' );
				await expect
					.soft( payPalOrder.payment_source.paypal.email_address )
					.toEqual( customer.email );
				await expect
					.soft( payPalOrder.payment_source.paypal.name.given_name )
					.toEqual( customer.first_name );
				await expect
					.soft( payPalOrder.payment_source.paypal.name.surname )
					.toEqual( customer.last_name );
				break;

			case 'pay_upon_invoice': {
				await expect
					.soft( payPalOrder.status )
					.toEqual( 'PENDING_APPROVAL' );
				const birthDate = payment.birthDate.split( '.' );
				await expect
					.soft( payPalOrder.payment_source )
					.toHaveProperty( 'pay_upon_invoice' );
				await expect
					.soft(
						payPalOrder.payment_source.pay_upon_invoice.birth_date
					)
					.toEqual(
						`${ birthDate[ 2 ] }-${ birthDate[ 1 ] }-${ birthDate[ 0 ] }`
					);
				break;
			}

			default: {
				await expect.soft( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect
					.soft( payPalOrder.payment_source )
					.toHaveProperty( 'paypal' );
				if ( payment.isVaulted ) {
					await expect
						.soft( payPalOrder.payment_source.paypal )
						.toHaveProperty( 'attributes' );
					await expect
						.soft( payPalOrder.payment_source.paypal.attributes )
						.toHaveProperty( 'vault' );
					await expect
						.soft(
							payPalOrder.payment_source.paypal.attributes.vault
								.status
						)
						.toEqual( 'VAULTED' );
					break;
				}
				await expect
					.soft( payPalOrder.payment_source.paypal.email_address )
					.toEqual( payment.payPalAccount.email );
			}
		}
	};

	/**
	 * Asserts PayPal payment
	 *
	 * @param paymentId PayPal payment ID
	 * @param shopOrder
	 */
	assertPayment = async (
		paymentId: string,
		shopOrder: ShopOrder
	) => {
		const { payment, merchant } = shopOrder;
		const fundingSource = payment.gateway.shortcut;

		if ( fundingSource === 'pay_upon_invoice' ) {
			return;
		}

		const payPalPayment = await this.getPayment( paymentId, merchant, payment.isAuthorized );

		if ( fundingSource === 'oxxo' ) {
			await expect.soft( payPalPayment.status ).toEqual( 'PENDING' );
			return;
		}

		if ( payment.isAuthorized ) {
			await expect.soft( payPalPayment.status ).toEqual( 'CREATED' );
			return;
		}

		await expect.soft( payPalPayment.status ).toEqual( 'COMPLETED' );
	};
}
