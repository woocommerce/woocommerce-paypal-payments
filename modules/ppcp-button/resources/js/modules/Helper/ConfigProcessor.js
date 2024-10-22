import merge from 'deepmerge';
import { v4 as uuidv4 } from 'uuid';
import { keysToCamelCase } from './Utils';

const processAxoConfig = ( config ) => {
	const scriptOptions = {};
	const sdkClientToken = config?.axo?.sdk_client_token;
	const uuid = uuidv4().replace( /-/g, '' );
	if ( sdkClientToken ) {
		scriptOptions[ 'data-sdk-client-token' ] = sdkClientToken;
		scriptOptions[ 'data-client-metadata-id' ] = uuid;
	}
	return scriptOptions;
};

const processUserIdToken = ( config, sdkClientToken ) => {
	const userIdToken = config?.save_payment_methods?.id_token;
	return userIdToken && ! sdkClientToken
		? { 'data-user-id-token': userIdToken }
		: {};
};

export const processConfig = ( config ) => {
	let scriptOptions = keysToCamelCase( config.url_params );
	if ( config.script_attributes ) {
		scriptOptions = merge( scriptOptions, config.script_attributes );
	}
	const axoOptions = processAxoConfig( config );
	const userIdTokenOptions = processUserIdToken(
		config,
		axoOptions[ 'data-sdk-client-token' ]
	);
	return merge.all( [ scriptOptions, axoOptions, userIdTokenOptions ] );
};
