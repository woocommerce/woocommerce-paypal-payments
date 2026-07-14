import merge from 'deepmerge';
import { keysToCamelCase } from './Utils';

// Contexts where the vault component actually renders and loads its own
// dedicated SDK (data-sdk-client-token, ppcpVaultComponent namespace). On these
// the shared SDK must NOT carry a buyer-scoped data-user-id-token, to avoid two
// conflicting buyer tokens. On every other context (express buttons on product,
// cart, mini-cart) the vault component never initializes, so the legacy
// data-user-id-token must still be attached for vaulting / returning-buyer
// recognition to work.
const VAULT_COMPONENT_CONTEXTS = [ 'checkout', 'checkout-block', 'pay-now' ];

export const getUserIdToken = ( config ) => {
	if ( config?.user?.is_logged !== true ) {
		return null;
	}

	const userIdToken = config?.save_payment_methods?.id_token;
	if ( ! userIdToken ) {
		return null;
	}

	const vaultComponentTakesOver =
		config?.vault_component?.is_eligible === true &&
		VAULT_COMPONENT_CONTEXTS.includes( config?.context );

	return vaultComponentTakesOver ? null : userIdToken;
};

const processSdkToken = ( config ) => {
	const userIdToken = getUserIdToken( config );
	return userIdToken ? { 'data-user-id-token': userIdToken } : {};
};

export const processConfig = ( config ) => {
	let scriptOptions = keysToCamelCase( config.url_params );
	if ( config.script_attributes ) {
		scriptOptions = merge( scriptOptions, config.script_attributes );
	}

	const sdkTokenOptions = processSdkToken( config );

	return merge.all( [ scriptOptions, sdkTokenOptions ] );
};
