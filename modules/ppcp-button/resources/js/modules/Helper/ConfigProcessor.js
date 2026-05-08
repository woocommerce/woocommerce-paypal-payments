import merge from 'deepmerge';
import { keysToCamelCase } from './Utils';

// Vault-component path takes precedence over the legacy user-id-token path.
// The two SDK attributes are mutually exclusive: PayPal's SDK does not accept
// both `data-sdk-client-token` and `data-user-id-token` on the same script tag.
const processSdkToken = ( config ) => {
	if ( config?.user?.is_logged !== true ) {
		return {};
	}
	const sdkClientToken = config?.vault_component?.sdk_client_token;
	if ( sdkClientToken ) {
		return { 'data-sdk-client-token': sdkClientToken };
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
