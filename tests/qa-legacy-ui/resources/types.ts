export type ShopConfig = {
	classicPages?: boolean; // false = block cart and checkout (default), true = classic cart & checkout pages
	settings?: WooCommerce.Settings; // WC settings
	taxes?: {
		options: WooCommerce.Settings; // Tax settings in WC > Settings > General tab
		rates: WooCommerce.CreateTax[]; // Tax rates to be active in WC > Settings > Taxes > Tax rates tab
	};
	customer?: WooCommerce.CreateCustomer; // Add registered customer
	subscription?: boolean; // if true WC Subscription plugin is activated
	products?: WooCommerce.CreateProduct[]; // Products to be created if not existing
	wpDebugging?: boolean; // if true sets { WP_DEBUG: true, SCRIPT_DEBUG: true }
};

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
