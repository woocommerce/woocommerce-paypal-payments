import { buildProject } from '../../../utils';

const project = buildProject( '08-refund' );

export const refundProjects = [
	'refund-usa-block',
].flatMap( project );