import { buildProject } from '../../../utils';

const project = buildProject( '05-frontend-ui' );

export const frontendUiProjects = [
	'frontend-ui',
	'frontend-ui-acdc',
	'frontend-ui-pay-later',
].flatMap( project );
