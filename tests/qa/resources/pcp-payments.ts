/**
 * Internal dependencies
 */
import { cards } from './cards';
import { Pcp } from './types';
import { gateways } from './pcp-gateways';
import { payPalAccounts } from './paypal-accounts';

const country = process.env.WC_DEFAULT_COUNTRY || 'usa';

const payPal: Pcp.Payment = {
	gateway: gateways.payPal,
	payPalAccount: payPalAccounts[ country ],
};

const payPalVaulted: Pcp.Payment = {
	...payPal,
	isVaulted: true,
};

const payLater: Pcp.Payment = {
	gateway: gateways.payLater,
	payPalAccount: payPalAccounts[ country ],
};

const oxxo: Pcp.Payment = {
	gateway: gateways.oxxo,
};

const venmo: Pcp.Payment = {
	gateway: gateways.venmo,
	payPalAccount: payPalAccounts.usa,
};

const acdc: Pcp.Payment = {
	gateway: gateways.acdc,
	card: cards.mastercard,
};

const acdc3ds: Pcp.Payment = {
	gateway: gateways.acdc3ds,
	card: cards.visa3ds,
};

const fastlaneGary: Pcp.Payment = {
	gateway: gateways.fastlane,
	fastlaneFlow: 'gary',
	card: cards.visa,
};

const fastlaneRyan: Pcp.Payment = {
	gateway: gateways.fastlane,
	fastlaneFlow: 'ryan',
	fastlaneOtp: '111111',
	card: cards.visaFastlane,
};

const bcdc: Pcp.Payment = {
	gateway: gateways.bcdc,
	card: cards.visa,
};

const pui: Pcp.Payment = {
	gateway: gateways.pui,
	birthDate: '01.01.1991',
	phone: '+39123456789',
};

const googlePay: Pcp.Payment = {
	gateway: gateways.googlepay,
	card: cards.visa,
};

export const payments = {
	payPal,
	payPalVaulted,
	payLater,
	oxxo,
	venmo,
	acdc,
	acdc3ds,
	fastlaneGary,
	fastlaneRyan,
	bcdc,
	pui,
	googlePay,
};
