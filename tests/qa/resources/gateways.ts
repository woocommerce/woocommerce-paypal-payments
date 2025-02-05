/**
 * Internal dependencies
 */
import { cards } from './cards';
import { PcpPayment } from './types';
import { payPalAccounts } from './paypal-accounts';

const country = 'germany';

export const payPal: PcpPayment = {
	gatewayName: 'PayPal',
	method: 'PayPal',
	dataFundingSource: 'paypal',
	gateway: 'ppcp-gateway',
	payPalAccount: payPalAccounts[ country ],
};

export const payPalVaulted: PcpPayment = {
	...payPal,
	isVaulted: true,
};

export const payLater: PcpPayment = {
	gatewayName: 'PayPal Pay Later',
	method: 'PayLater',
	dataFundingSource: 'paylater',
	gateway: 'ppcp-gateway',
	payPalAccount: payPalAccounts[ country ],
};

export const oxxo: PcpPayment = {
	gatewayName: 'OXXO',
	method: 'OXXO',
	dataFundingSource: 'oxxo',
	gateway: 'ppcp-oxxo-gateway',
};

export const venmo: PcpPayment = {
	gatewayName: 'Venmo',
	method: 'Venmo',
	dataFundingSource: 'venmo',
	gateway: 'ppcp-gateway',
	payPalAccount: payPalAccounts.usa,
};

export const acdc: PcpPayment = {
	gatewayName: 'Debit & Credit Cards',
	method: 'ACDC',
	dataFundingSource: 'acdc',
	gateway: 'ppcp-credit-card-gateway',
	card: cards.visa,
};

export const acdc3ds: PcpPayment = {
	gatewayName: 'Debit & Credit Cards',
	method: 'ACDC3DS',
	dataFundingSource: 'acdc',
	gateway: 'ppcp-credit-card-gateway',
	card: cards.visa3ds,
};

export const debitOrCreditCard: PcpPayment = {
	gatewayName: 'Credit or debit cards (via PayPal)', //'Debit or Credit Cards',//
	method: 'DebitOrCreditCard',
	dataFundingSource: 'card',
	gateway: 'ppcp-gateway',
	card: cards.visa,
};

export const standardCardButton: PcpPayment = {
	gatewayName: 'Debit & Credit Cards',
	method: 'StandardCardButton',
	dataFundingSource: 'card',
	gateway: 'ppcp-card-button-gateway',
	card: cards.visa,
};

export const payUponInvoice: PcpPayment = {
	gatewayName: 'Pay upon Invoice',
	method: 'PayUponInvoice',
	dataFundingSource: 'pay_upon_invoice',
	gateway: 'ppcp-pay-upon-invoice-gateway',
	birthDate: '01.01.1991',
};
