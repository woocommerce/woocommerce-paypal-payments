/**
 * External dependencies
 */
import {
	expect,
	RequestUtils,
	WooCommerceApi as WooCommerceApiBase,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { Pcp } from '../resources';
import urls from './urls';
import { generateRandomString } from './helpers/';

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
	 * Fails when a PCP endpoint reports an error.
	 * PCP endpoints answer HTTP 200 with { success: false, message } on failure.
	 *
	 * @param response Parsed response of a wc_paypal/* request.
	 * @param action   What was requested, for the error message.
	 */
	private assertSuccess = ( response, action: string ) => {
		expect(
			response?.success,
			`Assert PCP ${ action } succeeded: ${ response?.message ?? '' }`
		).toBe( true );
	};

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
		// Preset onboarding options (without gatewaysSynced — sync must run after
		// authentication so ACDC eligibility can be checked with a live connection).
		const onboardingResponse = await this.wcRequest(
			'post',
			'wc_paypal/onboarding',
			{
				...onboardingOptions,
				gatewaysRefreshed: true,
				_locale: 'user',
			}
		);
		this.assertSuccess( onboardingResponse, 'onboarding options update' );
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
		this.assertSuccess( response, 'merchant connection' );
		// Sync gateway states now that the merchant is connected, so that
		// eligibility checks (e.g. ACDC) use live PayPal API responses instead
		// of returning false due to an unauthenticated state.
		const syncResponse = await this.wcRequest(
			'post',
			'wc_paypal/onboarding',
			{
				gatewaysSynced: true,
				_locale: 'user',
			}
		);
		this.assertSuccess( syncResponse, 'gateway sync' );
		await this.updatePcpSettings( {
			invoicePrefix: `${ generateRandomString( 8 ) }-`,
		} );
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
		this.assertSuccess( response, 'merchant disconnection' );
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
		this.assertSuccess( response, 'payment methods update' );
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
		this.assertSuccess( response, 'settings update' );
		return response;
	};

	/**
	 * Updates Settings tab via REST API.
	 *
	 * @param data
	 */
	updatePcpStyling = async ( data: Pcp.Api.Styling.Settings ) => {
		const response = await this.wcRequest( 'post', `wc_paypal/styling`, {
			...data,
			_locale: 'user',
		} );
		this.assertSuccess( response, 'styling update' );
		return response;
	};

	isPayPalSubscription( subscription: WooCommerce.Subscription ): boolean {
		return !! subscription?.meta_data?.some(
			( meta ) => meta.key === 'ppcp_subscription'
		);
	}

	getPayPalSubscriptionBillingId = async ( subscriptionId: number ) => {
		const subscription = await this.getSubscription( subscriptionId );

		if ( ! subscription ) {
			console.error( `Subscription #${ subscriptionId } was not found.` );
			return 0;
		}

		const subscriptionMeta = subscription.meta_data.find(
			( meta ) => meta.key === 'ppcp_subscription'
		);

		return subscriptionMeta.value;
	};

	/**
	 * Get number of Subscription renewals
	 *
	 * @param subscriptionId
	 */
	getSubscriptionRenewalCount = async ( subscriptionId: number ) => {
		const subscription = await this.getSubscription( subscriptionId );
		const subscriptionMeta = subscription.meta_data.find(
			( meta ) => meta.key === '_subscription_renewal_order_ids_cache'
		);
		return subscriptionMeta?.value?.length ?? 0;
	};

	/**
	 * Triggers PayPal Subscription Renewal process by mocking paypal webhook.
	 * Sometimes it is required to send double request, so the retry
	 * mechanism is implemented
	 *
	 * @param subscriptionId
	 */
	triggerPayPalSubscriptionRenewal = async ( subscriptionId: number ) => {
		const initialRenewalCount =
			await this.getSubscriptionRenewalCount( subscriptionId );

		const billingId =
			await this.getPayPalSubscriptionBillingId( subscriptionId );

		const data = {
			id: 'NOT-IMPORTANT',
			event_type: 'PAYMENT.SALE.COMPLETED',
			resource: {
				billing_agreement_id: billingId,
				id: 'NOT-IMPORTANT',
			},
		};

		let isRenewalTriggered = false;
		let i = 0;

		while ( ! isRenewalTriggered && i < 2 ) {
			i++;

			const response = await this.requestUtils.request.post(
				urls.payPalWebhook,
				{ data }
			);

			expect
				.soft(
					response.ok(),
					`Assert PayPal Subscription Renewal request (${ i }) to be OK`
				)
				.toBeTruthy();

			// Wait up to 30 seconds for the renewal before resending: a resend while the
			// first webhook is still processing creates a second renewal order.
			for ( let retry = 0; retry < 30 && ! isRenewalTriggered; retry++ ) {
				const renewalCount =
					await this.getSubscriptionRenewalCount( subscriptionId );
				isRenewalTriggered = renewalCount > initialRenewalCount;
				if ( ! isRenewalTriggered ) {
					await new Promise( ( resolve ) =>
						setTimeout( resolve, 1000 )
					);
				}
			}
		}

		expect
			.soft(
				isRenewalTriggered,
				'Assert PayPal Subscription Renewal is triggered'
			)
			.toBeTruthy();

		return isRenewalTriggered;
	};
}
