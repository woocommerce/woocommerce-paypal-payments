export const gatewaySetupProjects = [
	{
		name: 'setup-gateway-block-paypal-pay-later-acdc',
		dependencies: [ 'setup-pcp-block-germany' ],
		testMatch: /setup-gateway-paypal-pay-later-acdc\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-gateway-classic-paypal-pay-later-acdc',
		dependencies: [ 'setup-pcp-classic-germany' ],
		testMatch: /setup-gateway-paypal-pay-later-acdc\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-gateway-classic-standard-card-button',
		dependencies: [ 'setup-pcp-classic-germany' ],
		testMatch: /setup-gateway-standard-card-button\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-gateway-classic-debit-or-credit-card',
		dependencies: [ 'setup-pcp-classic-germany' ],
		testMatch: /setup-gateway-debit-or-credit-card\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-gateway-classic-pay-upon-invoice',
		dependencies: [ 'setup-pcp-classic-germany' ],
		testMatch: /setup-gateway-pay-upon-invoice\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-gateway-classic-venmo',
		dependencies: [ 'setup-pcp-classic-usa' ],
		testMatch: /setup-gateway-venmo\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-gateway-classic-oxxo',
		dependencies: [ 'setup-pcp-classic-mexico' ],
		testMatch: /setup-gateway-oxxo\.ts/,
		fullyParallel: false,
	},
];