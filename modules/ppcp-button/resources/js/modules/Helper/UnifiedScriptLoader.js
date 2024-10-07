import { loadScript } from '@paypal/paypal-js';

class UnifiedScriptLoader {
	constructor() {
		this.loadedScripts = new Map();
		this.scriptPromises = new Map();
	}

	async loadPayPalScript( namespace, config ) {
		if ( ! namespace ) {
			throw new Error( 'Namespace is required' );
		}

		if ( this.loadedScripts.has( namespace ) ) {
			console.log(
				`PayPal script already loaded for namespace: ${ namespace }`
			);
			return this.loadedScripts.get( namespace );
		}

		if ( this.scriptPromises.has( namespace ) ) {
			console.log(
				`PayPal script loading in progress for namespace: ${ namespace }`
			);
			return this.scriptPromises.get( namespace );
		}

		const scriptPromise = new Promise( ( resolve, reject ) => {
			const scriptOptions = {
				...config.url_params,
				...config.script_attributes,
				'data-namespace': namespace,
			};

			if ( config.axo?.sdk_client_token ) {
				scriptOptions[ 'data-sdk-client-token' ] =
					config.axo.sdk_client_token;
				scriptOptions[ 'data-client-metadata-id' ] =
					config.axo.client_metadata_id;
			}

			if (
				config.save_payment_methods?.id_token &&
				! config.axo?.sdk_client_token
			) {
				scriptOptions[ 'data-user-id-token' ] =
					config.save_payment_methods.id_token;
			}

			loadScript( scriptOptions )
				.then( ( paypal ) => {
					this.loadedScripts.set( namespace, paypal );
					console.log(
						`PayPal script loaded for namespace: ${ namespace }`
					);
					resolve( paypal );
				} )
				.catch( ( error ) => {
					console.error(
						`Failed to load PayPal script for namespace: ${ namespace }`,
						error
					);
					reject( error );
				} )
				.finally( () => {
					this.scriptPromises.delete( namespace );
				} );
		} );

		this.scriptPromises.set( namespace, scriptPromise );
		return scriptPromise;
	}
}

export default new UnifiedScriptLoader();
