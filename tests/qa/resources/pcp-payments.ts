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

/**
 * Shards that run concurrently must not share a PayPal buyer account: a second
 * login invalidates the first one's session, which surfaces as the checkout
 * popup closing mid-flow and the order-received redirect never arriving. The
 * shards below each get their own account; transaction-usa keeps the default.
 */
const payPalRefund: Pcp.Payment = {
	...payPal,
	payPalAccount: payPalAccounts.usaRefund,
};

const payPalVaulting: Pcp.Payment = {
	...payPal,
	payPalAccount: payPalAccounts.usaVaulting,
};

const payPalSubscription: Pcp.Payment = {
	...payPal,
	payPalAccount: payPalAccounts.usaSubscription,
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

const acdcVisa: Pcp.Payment = {
	gateway: gateways.acdc,
	card: cards.visa,
};

const acdcVisa2: Pcp.Payment = {
	gateway: gateways.acdc,
	card: cards.visa2,
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
	payPalRefund,
	payPalVaulting,
	payPalSubscription,
	payLater,
	oxxo,
	venmo,
	acdc,
	acdcVisa,
	acdcVisa2,
	acdc3ds,
	fastlaneGary,
	fastlaneRyan,
	bcdc,
	pui,
	googlePay,
};
