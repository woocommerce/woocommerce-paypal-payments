/**
 * Internal dependencies
 */
import { Pcp } from './types';

const payPal: Pcp.Gateway = {
	title: 'PayPal',
	dataFundingSource: 'paypal',
	slug: 'ppcp-gateway',
};

const payLater: Pcp.Gateway = {
	title: 'PayPal Pay Later',
	dataFundingSource: 'paylater',
	slug: 'ppcp-gateway',
};

const oxxo: Pcp.Gateway = {
	title: 'OXXO',
	dataFundingSource: 'oxxo',
	slug: 'ppcp-oxxo-gateway',
};

const venmo: Pcp.Gateway = {
	title: 'Venmo',
	dataFundingSource: 'venmo',
	slug: 'ppcp-gateway',
};

const acdc: Pcp.Gateway = {
	title: 'Debit & Credit Cards',
	dataFundingSource: 'acdc',
	slug: 'ppcp-credit-card-gateway',
};

const acdc3ds: Pcp.Gateway = {
	title: 'Debit & Credit Cards',
	dataFundingSource: 'acdc',
	slug: 'ppcp-credit-card-gateway',
	acdc3ds: 'always-3d-secure',
};

const debitOrCreditCard: Pcp.Gateway = {
	title: 'Credit or debit cards (via PayPal)',
	dataFundingSource: 'card',
	slug: 'ppcp-gateway',
};

const standardCardButton: Pcp.Gateway = {
	title: 'Debit & Credit Cards',
	dataFundingSource: 'card',
	slug: 'ppcp-card-button-gateway',
};

const payUponInvoice: Pcp.Gateway = {
	title: 'Pay upon Invoice',
	dataFundingSource: 'pay_upon_invoice',
	slug: 'ppcp-pay-upon-invoice-gateway',
};

export const gateways = {
	payPal,
	payLater,
	oxxo,
	venmo,
	acdc,
	acdc3ds,
	debitOrCreditCard,
	standardCardButton,
	payUponInvoice,
};
