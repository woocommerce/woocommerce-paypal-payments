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
};

const venmo: Pcp.Gateway = {
	shortcut: 'venmo',
	country: 'usa',
	currency: 'USD',

	id: 'venmo',
	description: '',
	title: 'Venmo',
};

const payLater: Pcp.Gateway = {
	shortcut: 'paylater',
	country: 'usa',
	currency: 'USD',

	id: 'pay-later',
	description: '',
	title: 'PayPal Pay Later',
};

const acdc: Pcp.Gateway = {
	shortcut: 'acdc',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-credit-card-gateway',
	description: '',
	title: 'Debit & Credit Cards',
};

const acdc3ds: Pcp.Gateway = {
	...acdc,
	shortcut: 'acdc3ds',
	threeDSecure: 'always-3d-secure',
};

const fastlane: Pcp.Gateway = {
	shortcut: 'fastlane',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-axo-gateway',
	description: '',
	title: '',
};

const applepay: Pcp.Gateway = {
	shortcut: 'applepay',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-applepay',
	description: '',
	title: '',
};

const googlepay: Pcp.Gateway = {
	shortcut: 'googlepay',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-googlepay',
	description: '',
	title: '',
};

const bancontact: Pcp.Gateway = {
	shortcut: 'bancontact',
	country: 'belgium',
	currency: 'EUR',
	minAmount: '1.00',

	id: 'ppcp-bancontact',
	description: '',
	title: '',
};

const blik: Pcp.Gateway = {
	shortcut: 'blik',
	country: 'poland',
	currency: 'PLN',
	minAmount: '1.00',

	id: 'ppcp-blik',
	description: '',
	title: '',
};

const eps: Pcp.Gateway = {
	shortcut: 'eps',
	country: 'austria',
	currency: 'EUR',
	minAmount: '1.00',

	id: 'ppcp-eps',
	description: '',
	title: '',
};

const ideal: Pcp.Gateway = {
	shortcut: 'ideal',
	country: 'netherlands',
	currency: 'EUR',
	minAmount: '0.01',

	id: 'ppcp-ideal',
	description: '',
	title: '',
};

const mybank: Pcp.Gateway = {
	shortcut: 'mybank',
	country: 'italy',
	currency: 'EUR',

	id: 'ppcp-mybank',
	description: '',
	title: '',
};

const przelewy24: Pcp.Gateway = {
	shortcut: 'przelewy24',
	country: 'poland',
	currency: 'PLN', // EUR

	id: 'ppcp-p24',
	description: '',
	title: 'Przelewy24',
};

const trustly: Pcp.Gateway = {
	shortcut: 'trustly',
	country: 'austria', // Germany, Denmark, Estonia, Spain, Finland, UK, Lithuania, Latvia, Netherlands, Norway, Sweden
	currency: 'EUR', // DKK, SEK, GBP, NOK
	minAmount: '0.01',

	id: 'ppcp-trustly',
	description: '',
	title: '',
};

const multibanco: Pcp.Gateway = {
	shortcut: 'multibanco',
	country: 'portugal',
	currency: 'EUR',

	id: 'ppcp-multibanco',
	description: '',
	title: '',
};

const oxxo: Pcp.Gateway = {
	shortcut: 'oxxo',
	country: 'mexico',
	currency: 'MXD',

	id: 'ppcp-oxxo-gateway',
	description: '',
	title: 'OXXO',
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
};

const debitOrCreditCard: Pcp.Gateway = {
	shortcut: 'card',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-gateway',
	description: '',
	title: 'Credit or debit cards (via PayPal)',
};

const standardCardButton: Pcp.Gateway = {
	shortcut: 'card',
	country: 'usa',
	currency: 'USD',

	id: 'ppcp-card-button-gateway',
	description: '',
	title: 'Debit & Credit Cards',
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
