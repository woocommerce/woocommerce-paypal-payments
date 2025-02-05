export const storeSetupProjects = [
	{
		name: 'setup-store-block-germany',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-block-germany\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-store-classic-germany',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-classic-germany\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-store-classic-mexico',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-classic-mexico\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-store-classic-usa',
		dependencies: [ 'setup-woocommerce' ],
		testMatch: /setup-store-classic-usa\.ts/,
		fullyParallel: false,
	},
];