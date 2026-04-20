import { buildProject } from '../../../utils';

const project = buildProject( '07-vaulting' );

export const vaultingProjects = [
	'vaulting-transaction-usa-block',
	'vaulting-transaction-usa-classic',
].flatMap( project );