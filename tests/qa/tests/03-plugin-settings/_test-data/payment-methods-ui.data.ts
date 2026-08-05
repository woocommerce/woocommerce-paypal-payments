// expectedGatewayCount: approximate total number of .ppcp--method-item elements (soft assertion).
// expectedGroupCounts: expected count per section for easier debugging (soft assertions).
//   PayPal Checkout:          payPal + venmo + payLater = 3
//   Online Card Payments:     acdc + fastlane + applepay + googlepay = 4
//   Alternative Payments:     bancontact + blik + eps + ideal + mybank +
//                             przelewy24 + trustly + multibanco = 8
//   (OXXO, PayUponInvoice, and Pay with Crypto are not shown for this merchant/env right now)

export type PaymentMethodsUiTestData = {
	testKey: string;
	testLabel?: string;
	country: string;
	expectedGatewayCount: number;
	expectedGroupCounts: {
		paypalCheckout: number;
		onlineCardPayments: number;
		alternativePaymentMethods: number;
	};
	testGateways: string[];
};

const defaultUi: PaymentMethodsUiTestData[] = [
	{
		testKey: 'PCP-6228',
		testLabel: ' @Critical @Smoke',
		country: 'usa',
		expectedGatewayCount: 15,
		expectedGroupCounts: {
			paypalCheckout: 3,
			onlineCardPayments: 4,
			alternativePaymentMethods: 8,
		},
		testGateways: [
			'payPal',
			'venmo',
			'payLater',
			'acdc',
			'fastlane',
			'applepay',
			'googlepay',
			'bancontact',
			'blik',
			'eps',
			'ideal',
			'mybank',
			'przelewy24',
			'trustly',
			'multibanco',
		],
	},
];

export const paymentMethodsData = {
	defaultUi,
};
