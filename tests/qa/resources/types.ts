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
};

export namespace PcpSettings {
	export type StandardPayments = { [ key: string ]: any };

	export type PayLater = { [ key: string ]: any };

	export type AdvancedCardProcessing = { [ key: string ]: any };

	export type StandardCardButton = { [ key: string ]: any };

	export type Oxxo = { [ key: string ]: any };

	export type PayUponInvoice = { [ key: string ]: any };

	export type OnboardingStepTitle =
		| 'PayPal Payments'
		| 'Set up store type'
		| 'Select product types'
		| 'Choose checkout options'
		| 'Connect your PayPal account';

	export type OnboardingAdvancedOptions = {
		enableSandboxMode: boolean;
		enableManualConnection: boolean;
		merchant: PcpMerchant;
	};

	export type OnboardingProductTypes = {
		enableVirtual: boolean;
		enablePhysicalGoods: boolean;
	};

	export type OnboardingCheckoutOptions = {
		enableOptionalPaymentMethods: boolean;
	};
}

export type PcpConfig = {
	merchant?: PcpMerchant;
	clearPCPDB?: boolean;
	disconnectMerchant?: boolean;
	enablePayUponInvoice?: boolean;
	standardPayments?: PcpSettings.StandardPayments;
	payLater?: PcpSettings.PayLater;
	advancedCardProcessing?: PcpSettings.AdvancedCardProcessing;
	standardCardButton?: PcpSettings.StandardCardButton;
	oxxo?: PcpSettings.Oxxo;
	payUponInvoice?: PcpSettings.PayUponInvoice;
};
