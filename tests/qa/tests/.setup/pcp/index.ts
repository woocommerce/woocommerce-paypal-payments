export const pcpSetupProjects = [
	{
		name: 'setup-pcp-block-germany',
		dependencies: [ 'setup-store-block-germany' ],
		testMatch: /setup-pcp-germany\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-pcp-classic-germany',
		dependencies: [ 'setup-store-classic-germany' ],
		testMatch: /setup-pcp-germany\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-pcp-classic-mexico',
		dependencies: [ 'setup-store-classic-mexico' ],
		testMatch: /setup-pcp-mexico\.ts/,
		fullyParallel: false,
	},
	{
		name: 'setup-pcp-classic-usa',
		dependencies: [ 'setup-store-classic-usa' ],
		testMatch: /setup-pcp-usa\.ts/,
		fullyParallel: false,
	},
];