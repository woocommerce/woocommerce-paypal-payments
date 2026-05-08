import merge from 'deepmerge';
import { keysToCamelCase } from './Utils';

const processSdkClientToken = ( config ) => {
	const sdkClientToken = config?.vault_component?.sdk_client_token;
	return sdkClientToken && config?.user?.is_logged === true
		? { 'data-sdk-client-token': sdkClientToken }
		: {};
};

export const processConfig = ( config ) => {
	let scriptOptions = keysToCamelCase( config.url_params );
	if ( config.script_attributes ) {
		scriptOptions = merge( scriptOptions, config.script_attributes );
	}

	const sdkClientTokenOptions = processSdkClientToken( config );

	return merge.all( [ scriptOptions, sdkClientTokenOptions ] );
};
