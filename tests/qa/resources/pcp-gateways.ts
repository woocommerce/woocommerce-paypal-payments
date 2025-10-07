/**
 * Internal dependencies
 */
import { Pcp } from './types';

const payPal: Pcp.Gateway = {
	shortcut: 'paypal',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-gateway',
	description: '',
	title: 'PayPal',
	titleInPcpSettings: 'PayPal',
	titleInWcSettings: 'PayPal',
	hasSettingsButton: true,
	enabled: true,

	paypalShowLogo: false,
};

const venmo: Pcp.Gateway = {
	shortcut: 'venmo',
	country: 'usa',
	currency: 'USD',

	id: 'venmo',
	description: '',
	title: 'Venmo',
	titleInPcpSettings: 'Venmo',
	titleInWcSettings: 'Venmo',
	hasSettingsButton: false,
	enabled: true,
};

const payLater: Pcp.Gateway = {
	shortcut: 'paylater',
	country: 'usa',
	currency: 'USD',

	id: 'pay-later',
	description: '',
	title: 'PayPal Pay Later',
	titleInPcpSettings: 'Pay Later',
	hasSettingsButton: false,
	enabled: false,
};

const acdc: Pcp.Gateway = {
	shortcut: 'acdc',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-credit-card-gateway',
	description: '',
	title: 'Debit & Credit Cards',
	titleInPcpSettings: 'Advanced Credit and Debit Card Payments',
	titleInWcSettings: 'Advanced Card Processing',
	hasSettingsButton: true,
	enabled: false,

	threeDSecure: 'no-3d-secure',
};

const acdc3ds: Pcp.Gateway = {
	...acdc,
	threeDSecure: 'always-3d-secure',
};

const fastlane: Pcp.Gateway = {
	shortcut: 'fastlane',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-axo-gateway',
	description: '',
	title: 'Fastlane',
	titleInPcpSettings: 'Fastlane by PayPal',
	titleInWcSettings: 'Fastlane Debit & Credit Cards',
	hasSettingsButton: true,
	dependsOn: 'acdc',
	enabled: false,

	fastlaneCardholderName: false,
	fastlaneDisplayWatermark: true,
};

const applepay: Pcp.Gateway = {
	shortcut: 'applepay',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-applepay',
	description: '',
	title: 'Apple Pay',
	titleInPcpSettings: 'Apple Pay',
	titleInWcSettings: 'Apple Pay (via PayPal)',
	hasSettingsButton: true,
	dependsOn: 'acdc',
	enabled: false,
};

const googlepay: Pcp.Gateway = {
	shortcut: 'googlepay',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-googlepay',
	description: '',
	title: 'Google Pay',
	titleInPcpSettings: 'Google Pay',
	titleInWcSettings: ' (via PayPal)',
	hasSettingsButton: true,
	dependsOn: 'acdc',
	enabled: false,
};

const bancontact: Pcp.Gateway = {
	shortcut: 'bancontact',
	country: 'belgium',
	currency: 'EUR',
	minAmount: '1.00',

	id: 'ppcp-bancontact',
	description: '',
	title: 'Bancontact',
	titleInPcpSettings: 'Bancontact',
	titleInWcSettings: 'Bancontact (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const blik: Pcp.Gateway = {
	shortcut: 'blik',
	country: 'poland',
	currency: 'PLN',
	minAmount: '1.00',

	id: 'ppcp-blik',
	description: '',
	title: 'BLIK',
	titleInPcpSettings: 'BLIK',
	titleInWcSettings: ' (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const eps: Pcp.Gateway = {
	shortcut: 'eps',
	country: 'austria',
	currency: 'EUR',
	minAmount: '1.00',

	id: 'ppcp-eps',
	description: '',
	title: 'eps',
	titleInPcpSettings: 'eps',
	titleInWcSettings: 'EPS (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const ideal: Pcp.Gateway = {
	shortcut: 'ideal',
	country: 'netherlands',
	currency: 'EUR',
	minAmount: '0.01',

	id: 'ppcp-ideal',
	description: '',
	title: 'iDEAL',
	titleInPcpSettings: 'iDEAL',
	titleInWcSettings: ' (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const mybank: Pcp.Gateway = {
	shortcut: 'mybank',
	country: 'italy',
	currency: 'EUR',

	id: 'ppcp-mybank',
	description: '',
	title: 'MyBank',
	titleInPcpSettings: 'MyBank',
	titleInWcSettings: ' (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const przelewy24: Pcp.Gateway = {
	shortcut: 'przelewy24',
	country: 'poland',
	currency: 'PLN', // EUR

	id: 'ppcp-p24',
	description: '',
	title: 'Przelewy24',
	titleInPcpSettings: 'Przelewy24',
	titleInWcSettings: ' (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const trustly: Pcp.Gateway = {
	shortcut: 'trustly',
	country: 'austria', // Germany, Denmark, Estonia, Spain, Finland, UK, Lithuania, Latvia, Netherlands, Norway, Sweden
	currency: 'EUR', // DKK, SEK, GBP, NOK
	minAmount: '0.01',

	id: 'ppcp-trustly',
	description: '',
	title: 'Trustly',
	titleInPcpSettings: 'Trustly',
	titleInWcSettings: 'Trustly (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const multibanco: Pcp.Gateway = {
	shortcut: 'multibanco',
	country: 'portugal',
	currency: 'EUR',

	id: 'ppcp-multibanco',
	description: '',
	title: 'Multibanco',
	titleInPcpSettings: 'Multibanco',
	titleInWcSettings: 'Multibanco (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const oxxo: Pcp.Gateway = {
	shortcut: 'oxxo',
	country: 'mexico',
	currency: 'MXD',

	id: 'ppcp-oxxo-gateway',
	description: '',
	title: 'OXXO',
	titleInPcpSettings: 'OXXO',
	titleInWcSettings: 'OXXO (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const payUponInvoice: Pcp.Gateway = {
	shortcut: 'pay_upon_invoice',
	country: 'germany',
	currency: 'EUR',
	minAmount: '5.00',
	maxAmount: '2500.00',

	id: 'ppcp-pay-upon-invoice-gateway',
	description: '',
	title: 'Pay upon Invoice',
	titleInPcpSettings: 'Pay upon Invoice',
	titleInWcSettings: 'Pay upon Invoice',
	hasSettingsButton: true,
	enabled: true,
};

const debitOrCreditCard: Pcp.Gateway = {
	shortcut: 'card',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-gateway',
	description: '',
	title: 'Credit or debit cards (via PayPal)',
	titleInPcpSettings: 'Credit or debit cards',
	titleInWcSettings: 'Credit or debit cards (via PayPal)',
	hasSettingsButton: true,
	enabled: true,
};

const standardCardButton: Pcp.Gateway = {
	shortcut: 'card',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-card-button-gateway',
	description: '',
	title: 'Debit & Credit Cards',
	titleInPcpSettings: 'Debit & Credit Cards',
	titleInWcSettings: 'Debit & Credit Cards',
	hasSettingsButton: true,
	enabled: true,
};

export const gateways = {
	payPal,
	venmo,
	payLater,

	acdc,
	acdc3ds,
	fastlane,
	applepay,
	googlepay,

	bancontact,
	blik,
	eps,
	ideal,
	mybank,
	przelewy24,
	trustly,
	multibanco,
	oxxo,
	payUponInvoice,
	debitOrCreditCard,
	standardCardButton,
};
