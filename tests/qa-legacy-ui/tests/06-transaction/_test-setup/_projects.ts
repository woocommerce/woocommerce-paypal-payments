import { buildProject } from '../../../utils';

const project = buildProject( '06-transaction' );

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
	'transaction-usa-classic-bcdc',
	'transaction-usa-classic-bcdc-intent-authorized',
	'transaction-usa-classic-bcdc-paypal',
	'transaction-usa-classic-bcdc-paypal-intent-authorized',
].flatMap( project );