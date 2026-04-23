import merge from 'deepmerge';
import { keysToCamelCase } from './Utils';

const processUserIdToken = ( config ) => {
	const userIdToken = config?.save_payment_methods?.id_token;
	return userIdToken && config?.user?.is_logged === true
		? { 'data-user-id-token': userIdToken }
		: {};
};

export const processConfig = ( config ) => {
	let scriptOptions = keysToCamelCase( config.url_params );
	if ( config.script_attributes ) {
		scriptOptions = merge( scriptOptions, config.script_attributes );
	}

	const userIdTokenOptions = processUserIdToken( config );

	return merge.all( [ scriptOptions, userIdTokenOptions ] );
};
