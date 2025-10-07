/**
 * Internal dependencies
 */
import { merchants, PcpConfig, PcpSettings } from '.';

const country = 'usa';

const standardPaymentsDefault: PcpSettings.StandardPayments = {
	enableGateway: true, // Check "Enable/Disable" checkbox
	requireFinalConfirmation: true, // Check "Require final confirmation on checkout" checkbox
	vaulting: false, // Uncheck vaulting
	standardCardButton: false, // Uncheck "Create gateway for Standard Card Button" checkbox
	intent: 'Capture', // Set intent to "Capture"
	customizeSmartButtonsPerLocation: true, // Check "Customize smart buttons per location" checkbox
	singleProductButtonLayout: 'Vertical',
};

export const pcpConfigDefault: PcpConfig = {
	clearPCPDB: true, // clear PCP DB
	merchant: merchants[ country ],
	standardPayments: standardPaymentsDefault,
};

export const pcpConfigVaulting = {
	clearPCPDB: true, // clear PCP DB
	merchant: merchants[ country ],
	standardPayments: {
		...standardPaymentsDefault,
		vaulting: true,
	},
};

export const pcpConfigGermany = {
	...pcpConfigDefault,
	merchant: merchants.germany,
	enablePayUponInvoice: true, // true for Germany
};

export const pcpConfigMexico = {
	...pcpConfigDefault,
	merchant: merchants.mexico,
};

export const pcpConfigUsa = {
	...pcpConfigDefault,
	merchant: merchants.usa,
};

export const pcpConfigVaultingUsa = {
	...pcpConfigVaulting,
	merchant: merchants.usa,
};
