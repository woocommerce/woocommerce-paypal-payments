const BASE_SETUP = '05-frontend-ui/_test-setup';
const BASE_SPEC = '05-frontend-ui';

function frontendUiProject( name: string ) {
	const safeName = name.replace( /\./g, '\\.' );
	return [
		{
			name: `setup-${ name }`,
			testMatch: new RegExp( `${ BASE_SETUP }/${ safeName }\\.setup\\.ts` ),
			fullyParallel: false,
		},
		{
			name: name,
			testMatch: new RegExp( `${ BASE_SPEC }/${ safeName }\\.spec\\.ts` ),
			dependencies: [ `setup-${ name }` ],
			fullyParallel: false,
		},
	];
}

export const frontendUiProjects = [
	'frontend-ui',
	'frontend-ui-acdc',
	'frontend-ui-pay-later',
].flatMap( frontendUiProject );