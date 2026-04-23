export type PcpMerchant = {
	email: string;
	password: string;
	client_id: string;
	client_secret: string;
	account_id: string;
};

export type PayPalAccount = {
	email: string;
	password: string;
};

export type PcpPaymentMethod =
	| 'PayPal'
	| 'PayLater'
	| 'OXXO'
	| 'Venmo'
	| 'ACDC'
	| 'ACDC3DS'
	| 'DebitOrCreditCard'
	| 'StandardCardButton'
	| 'PayUponInvoice';

export type PcpFundingSource =
	| 'paypal'
	| 'paylater'
	| 'oxxo'
	| 'venmo'
	| 'acdc'
	| 'card'
	| 'pay_upon_invoice';

export type PcpPayment = {
	gatewayName: string;
	method: PcpPaymentMethod;
	dataFundingSource: PcpFundingSource;
	gateway: string;
	card?: WooCommerce.CreditCard;
	payPalAccount?: PayPalAccount;
	useNotVaultedAccount?: PayPalAccount;
	birthDate?: string;
	isAuthorized?: boolean;
	isVaulted?: boolean;
	saveToAccount?: boolean;
	phone?: string;
};

export namespace PcpSettings {
	export type StandardPayments = { [ key: string ]: any };

	export type PayLater = { [ key: string ]: any };

	export type AdvancedCardProcessing = { [ key: string ]: any };

	export type StandardCardButton = { [ key: string ]: any };

	export type Oxxo = { [ key: string ]: any };

	export type PayUponInvoice = { [ key: string ]: any };
}

export type PcpConfig = {
	merchant?: PcpMerchant;
	clearPCPDB?: boolean;
	merchantIsDisconnected?: boolean;
	enablePayUponInvoice?: boolean;
	standardPayments?: PcpSettings.StandardPayments;
	payLater?: PcpSettings.PayLater;
	advancedCardProcessing?: PcpSettings.AdvancedCardProcessing;
	standardCardButton?: PcpSettings.StandardCardButton;
	oxxo?: PcpSettings.Oxxo;
	payUponInvoice?: PcpSettings.PayUponInvoice;
};
