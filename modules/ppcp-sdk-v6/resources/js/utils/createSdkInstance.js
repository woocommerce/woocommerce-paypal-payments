/**
 * The SDK throws for an empty locale or one outside its own list (e.g. tr-TR);
 * without a locale it uses the browser language.
 *
 * @package
 */

const UNSUPPORTED_LOCALE = 'ERR_DEV_UNSUPPORTED_LOCALE';

/**
 * Calls paypal.createInstance(), dropping the locale when the SDK rejects it.
 *
 * @param {Object} paypal  - The v6 SDK global.
 * @param {Object} options - The createInstance() options.
 * @return {Promise<Object>} The SDK instance.
 */
export async function createSdkInstance( paypal, options ) {
	const { locale, ...rest } = options;

	if ( locale ) {
		try {
			return await paypal.createInstance( options );
		} catch ( error ) {
			if ( error?.code !== UNSUPPORTED_LOCALE ) {
				throw error;
			}
		}
	}

	return paypal.createInstance( rest );
}
