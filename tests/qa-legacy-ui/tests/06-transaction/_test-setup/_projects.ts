const BASE_SETUP = '06-transaction/_test-setup';
const BASE_SPEC = '06-transaction';

function transactionProject( name: string ) {
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

export const transactionProjects = [
	'transaction-germany-classic',
	'transaction-mexico-classic',
	'transaction-usa-block',
	'transaction-usa-block-intent-authorized',
	'transaction-usa-block-vertical-buttons',
	'transaction-usa-classic',
	'transaction-usa-classic-acdc-3ds',
	'transaction-usa-classic-button-orientation',
	'transaction-usa-classic-intent-authorized',
	'transaction-usa-classic-specific-merchant',
	'transaction-usa-classic-debit-or-credit-card',
	'transaction-usa-classic-debit-or-credit-card-intent-authorized',
	'transaction-usa-classic-standard-card-button',
	'transaction-usa-classic-standard-card-button-intent-authorized',
].flatMap( transactionProject );