export const storeSetupProjects = [
	{
		name: 'setup-store-block-germany',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-block-germany\.setup\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-store-classic-germany',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-classic-germany\.setup\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-store-classic-mexico',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-classic-mexico\.setup\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-store-classic-usa',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-classic-usa\.setup\.ts/,
		fullyParallel: false,
	},
];
