import merge from 'deepmerge';
import { keysToCamelCase } from './Utils';

// data-sdk-client-token is reserved exclusively for the vault component's own
// dedicated SDK namespace (ppcpVaultComponent). Shared SDK loads must not carry it.
const processSdkToken = ( config ) => {
	if ( config?.user?.is_logged !== true ) {
		return {};
	}
	if ( config?.vault_component?.is_eligible ) {
		return {};
	}
	const userIdToken = config?.save_payment_methods?.id_token;
	if ( userIdToken ) {
		return { 'data-user-id-token': userIdToken };
	}
	return {};
};

export const processConfig = ( config ) => {
	let scriptOptions = keysToCamelCase( config.url_params );
	if ( config.script_attributes ) {
		scriptOptions = merge( scriptOptions, config.script_attributes );
	}

	const sdkTokenOptions = processSdkToken( config );

	return merge.all( [ scriptOptions, sdkTokenOptions ] );
};
