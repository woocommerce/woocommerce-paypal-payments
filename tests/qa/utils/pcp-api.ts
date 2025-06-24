/**
 * External dependencies
 */
import {
	RequestUtils,
	WooCommerceApi as WooCommerceApiBase,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { Pcp } from '../resources';
import urls from './urls';

/**
 * Class for REST API interactions with PCP Settings.
 */
export class PcpApi extends WooCommerceApiBase {
	requestUtils: RequestUtils;

	constructor( { request, requestUtils } ) {
		super( { request } );
		this.requestUtils = requestUtils;
	}

	/**
	 * Connects merchant via REST API.
	 *
	 * @param clientId          PayPal merchant's client ID
	 * @param clientSecret      PayPal merchant's client Isecret
	 * @param onboardingOptions
	 */
	connectMerchant = async (
		clientId: string,
		clientSecret: string,
		onboardingOptions: Pcp.Api.OnboardingOptions = {
			isCasualSeller: false,
			products: [ 'physical', 'virtual' ],
		}
	) => {
		// Preset onboarding options
		await this.wcRequest( 'post', 'wc_paypal/onboarding', {
			...onboardingOptions,
			_locale: 'user',
		} );
		// Merchant connection request
		const response = await this.wcRequest(
			'post',
			'wc_paypal/authenticate/direct',
			{
				clientId,
				clientSecret,
				useSandbox: onboardingOptions?.useSandbox || true,
				_locale: 'user',
			}
		);
		return response;
	};

	/**
	 * Disconnects merchant via REST API with optional DB reset parameter.
	 *
	 * @param reset
	 */
	disconnectMerchant = async ( reset: boolean = false ) => {
		const response = await this.wcRequest(
			'post',
			'wc_paypal/authenticate/disconnect',
			{
				reset,
				_locale: 'user',
			}
		);
		return response;
	};

	/**
	 * Disconnects merchant with DB reset via REST API.
	 */
	resetDb = () => this.disconnectMerchant( true );

	/**
	 * Updates Payment Methods tab via REST API.
	 *
	 * @example of data (all params are optional):
	 * {
	 * 		fastlaneCardholderName: false,
	 *		fastlaneDisplayWatermark: true,
	 *		paypalShowLogo: false,
	 *		threeDSecure: 'always-3d-secure',
	 * 		"ppcp-gateway": { enabled: true },
	 * 		"pay-later": { enabled: true },
	 * 	}
	 *
	 * @param data
	 */
	updatePcpPaymentMethods = async ( data: Pcp.Api.PaymentMethods ) => {
		const response = await this.wcRequest( 'post', `wc_paypal/payment`, {
			...data,
			_locale: 'user',
		} );
		return response;
	};

	/**
	 * Updates Settings tab via REST API.
	 *
	 * @param data
	 */
	updatePcpSettings = async ( data: Pcp.Api.Settings ) => {
		const response = await this.wcRequest( 'post', `wc_paypal/settings`, {
			...data,
			_locale: 'user',
		} );
		return response;
	};

	/**
	 * Triggers Vaulting Subscription Renewal process
	 * 
	 * @param subscriptionId
	 */
	triggerVaultingSubscriptionRenewal = async ( subscriptionId: number ) => {
		const url = urls.admin.wooCommerce.subscription.edit + subscriptionId;
		const wpnonce = await this.requestUtils.getPageNonce( url );
		const formData = {
			_wpnonce: wpnonce,
			post_ID: subscriptionId,
			action: 'edit_order',
			wc_order_action: 'wcs_process_renewal',
		};
		const response = await this.requestUtils.submitPageForm( url, formData );
		return response.ok();
	}

	getPayPalSubscriptionBillingId = async ( subscriptionId: number ) => {
		const subscription = await this.getSubscription( subscriptionId );

		if( ! subscription ) {
			console.error( `Subscription #${ subscriptionId } was not found.` );
		}

		const subscriptionMeta = subscription.meta_data.find(
			( meta ) => meta.key === 'ppcp_subscription'
		);

		return subscriptionMeta.value;
	}

	/**
	 * Triggers PayPal Subscription Renewal process
	 * 
	 * @param subscriptionId
	 */
	triggerPayPalSubscriptionRenewal = async ( subscriptionId: number ) => {
		const billingId = await this.getPayPalSubscriptionBillingId( subscriptionId );
		const response = await this.requestUtils.request.post(
			urls.payPalWebhook, {
			data: {
				id: 'NOT-IMPORTANT',
				event_type: 'PAYMENT.SALE.COMPLETED',
				resource: {
					billing_agreement_id: billingId,
					id: 'NOT-IMPORTANT',
				},
			},
		} );
		return response.ok();
	}
}
