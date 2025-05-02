/**
 * External dependencies
 */
import {
	RequestUtils,
	WooCommerceApi as WooCommerceApiBase,
} from '@inpsyde/playwright-utils/build';
import { Pcp } from '../resources';

export class PcpApi extends WooCommerceApiBase {
	requestUtils: RequestUtils;

	constructor( { request, requestUtils } ) {
		super( { request } );
		this.requestUtils = requestUtils;
	}

	connectMerchant = async (
		clientId: string,
		clientSecret: string,
		useSandbox: boolean = true
	) => {
		const response = await this.wcRequest(
			'post',
			'wc_paypal/authenticate/direct',
			{
				clientId,
				clientSecret,
				useSandbox,
				_locale: 'user',
			}
		);
		return response;
	};

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

	resetDb = () => this.disconnectMerchant( true );

	updatePcpPaymentMethods = async (
		data: Pcp.Api.PaymentMethods
	) => {
		const response = await this.wcRequest(
			'post',
			`wc_paypal/payment`,
			{
				...data,
				_locale: 'user',
			}
		);
		return response;
	};

	updatePcpSettings = async (
		data: Pcp.Api.Settings
	) => {
		const response = await this.wcRequest(
			'post',
			`wc_paypal/settings`,
			{
				...data,
				_locale: 'user',
			}
		);
		return response;
	};
}
