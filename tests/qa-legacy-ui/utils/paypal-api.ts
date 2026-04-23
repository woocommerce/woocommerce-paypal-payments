/**
 * External dependencies
 */
import { APIRequestContext, expect } from '@playwright/test';
import { createAuthHeader } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PcpFundingSource, PcpMerchant, PcpPayment } from '../resources';

/**
 * Class for PayPal API
 */

export class PayPalAPI {
	request: APIRequestContext;
	apiBaseUrl = 'https://api-m.sandbox.paypal.com/v2';

	constructor( { request } ) {
		this.request = request;
	}

	private apiRequest = async (
		requestType: string,
		endPoint: string,
		merchant: PcpMerchant,
		data?: any
	) => {
		try {
			const response = await this.request[ requestType ](
				this.apiBaseUrl + endPoint,
				{
					headers: createAuthHeader(
						merchant.client_id,
						merchant.client_secret
					),
					data,
				}
			);

			if ( ! ( await response.ok() ) ) {
				throw new Error(
					`Request failed with status ${ await response.status() }`
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
	getToken = async ( merchant: PcpMerchant ) => {
		const response = await this.request.post(
			'https://api.sandbox.paypal.com/v1/oauth2/token',
			{
				headers: createAuthHeader(
					merchant.client_id,
					merchant.client_secret
				),
				form: { grant_type: 'client_credentials' },
			}
		);
		return ( await response.json() ).access_token;
	};

	/**
	 * Gets payment info by PayPal Transaction ID
	 *
	 * @param resourceId - PayPal transaction ID
	 * @param merchant   - { client_id: '...', client_secret: '...' }
	 */
	getCapturedPayment = async (
		resourceId: string,
		merchant: PcpMerchant
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
		merchant: PcpMerchant
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
	getOrder = async ( resourceId: string, merchant: PcpMerchant ) => {
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
	getRefund = async ( resourceId: string, merchant: PcpMerchant ) => {
		return await this.apiRequest(
			'get',
			`/payments/refunds/${ resourceId }`,
			merchant
		);
	};

	getOrderIdFromWooCommerce = async (
		wooCommerceOrderJson: WooCommerce.Order
	) => {
		return wooCommerceOrderJson.meta_data.filter(
			( el ) => el.key === '_ppcp_paypal_order_id'
		)[ 0 ].value;
	};

	/**
	 * Gets PayPal payment ID for different gateways
	 *
	 * @param payPalOrder
	 * @param payment
	 */
	getPaymentIdFromOrder = async ( payPalOrder, payment: PcpPayment ) => {
		const fundingSource: PcpFundingSource = payment.dataFundingSource;

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
	 * Gets payment from PayPal API
	 *
	 * @param paymentId
	 * @param shopOrder
	 */
	getPayment = async (
		paymentId: string,
		shopOrder: WooCommerce.ShopOrder
	) => {
		if ( shopOrder.payment.isAuthorized ) {
			return await this.getAuthorizedPayment(
				paymentId,
				shopOrder.merchant
			);
		}
		return await this.getCapturedPayment( paymentId, shopOrder.merchant );
	};

	/**
	 *
	 * @param resourceId
	 * @param shopOrder
	 */
	getFee = async ( resourceId: string, shopOrder: WooCommerce.ShopOrder ) => {
		const fundingSource: PcpFundingSource =
			shopOrder.payment.dataFundingSource;
		if (
			[ 'pay_upon_invoice', 'oxxo' ].includes( fundingSource ) ||
			shopOrder.payment.isAuthorized
		) {
			return;
		}
		const payment = await this.getPayment( resourceId, shopOrder );
		return payment.seller_receivable_breakdown.paypal_fee.value;
	};

	getPayout = async (
		resourceId: string,
		shopOrder: WooCommerce.ShopOrder
	) => {
		const fundingSource: PcpFundingSource =
			shopOrder.payment.dataFundingSource;
		if (
			[ 'pay_upon_invoice', 'oxxo' ].includes( fundingSource ) ||
			shopOrder.payment.isAuthorized
		) {
			return;
		}
		const payment = await this.getPayment( resourceId, shopOrder );
		return payment.seller_receivable_breakdown.net_amount.value;
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
		shopOrder: WooCommerce.ShopOrder
	) => {
		const fundingSource: PcpFundingSource =
			shopOrder.payment.dataFundingSource;
		const payPalOrderId = await this.getOrderIdFromWooCommerce(
			wooCommerceOrderJson
		);
		await expect( payPalOrderId ).not.toHaveLength( 0 );

		const payPalOrder = await this.getOrder(
			payPalOrderId,
			shopOrder.merchant
		);

		if ( fundingSource !== 'oxxo' ) {
			const payPalPaymentId = await this.getPaymentIdFromOrder(
				payPalOrder,
				shopOrder.payment
			);
			await expect(
				String( wooCommerceOrderJson.transaction_id )
			).toEqual( String( payPalPaymentId ) );
		}

		const expectedIntent = shopOrder.payment.isAuthorized
			? 'AUTHORIZE'
			: 'CAPTURE';
		await expect( payPalOrder.intent ).toEqual( expectedIntent );

		switch ( fundingSource ) {
			case 'oxxo':
				await expect( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect( payPalOrder.payment_source ).toHaveProperty(
					'oxxo'
				);
				await expect( payPalOrder.payment_source.oxxo.email ).toEqual(
					shopOrder.customer.email
				);
				break;

			case 'acdc':
				await expect( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect( payPalOrder.payment_source ).toHaveProperty(
					'card'
				);
				await expect( payPalOrder.payment_source.card.name ).toEqual(
					`${ shopOrder.customer.first_name } ${ shopOrder.customer.last_name }`
				);
				break;

			case 'card':
				await expect( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect( payPalOrder.payment_source ).toHaveProperty(
					'paypal'
				);
				await expect(
					payPalOrder.payment_source.paypal.email_address
				).toEqual( shopOrder.customer.email );
				await expect(
					payPalOrder.payment_source.paypal.name.given_name
				).toEqual( shopOrder.customer.first_name );
				await expect(
					payPalOrder.payment_source.paypal.name.surname
				).toEqual( shopOrder.customer.last_name );
				break;

			case 'pay_upon_invoice':
				await expect( payPalOrder.status ).toEqual(
					'PENDING_APPROVAL'
				);
				const birthDate = shopOrder.payment.birthDate.split( '.' );
				await expect( payPalOrder.payment_source ).toHaveProperty(
					'pay_upon_invoice'
				);
				await expect(
					payPalOrder.payment_source.pay_upon_invoice.birth_date
				).toEqual(
					`${ birthDate[ 2 ] }-${ birthDate[ 1 ] }-${ birthDate[ 0 ] }`
				);
				break;

			default:
				await expect( payPalOrder.status ).toEqual( 'COMPLETED' );
				await expect( payPalOrder.payment_source ).toHaveProperty(
					'paypal'
				);
				if ( shopOrder.payment.isVaulted ) {
					await expect(
						payPalOrder.payment_source.paypal
					).toHaveProperty( 'attributes' );
					await expect(
						payPalOrder.payment_source.paypal.attributes
					).toHaveProperty( 'vault' );
					await expect(
						payPalOrder.payment_source.paypal.attributes.vault
							.status
					).toEqual( 'VAULTED' );
					break;
				}

				// SIARHEI-TODO fix this:
				// if (!(shopOrder.payment.isVaulted==false && shopOrder.payment.method === 'PayPal')) {
				await expect(
					payPalOrder.payment_source.paypal.email_address
				).toEqual( shopOrder.payment.payPalAccount.email );
			// break;
			// }
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
		shopOrder: WooCommerce.ShopOrder
	) => {
		const fundingSource: PcpFundingSource =
			shopOrder.payment.dataFundingSource;

		if ( fundingSource === 'pay_upon_invoice' ) {
			return;
		}

		const payment = await this.getPayment( paymentId, shopOrder );

		if ( fundingSource === 'oxxo' ) {
			await expect( payment.status ).toEqual( 'PENDING' );
			return;
		}

		if ( shopOrder.payment.isAuthorized ) {
			await expect( payment.status ).toEqual( 'CREATED' );
			return;
		}

		await expect( payment.status ).toEqual( 'COMPLETED' );
	};
}
