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
	'transaction-usa-classic-debit-or-credit-card',
	'transaction-usa-classic-debit-or-credit-card-intent-authorized',
	'transaction-usa-classic-standard-card-button',
	'transaction-usa-classic-standard-card-button-intent-authorized',
].flatMap( project );