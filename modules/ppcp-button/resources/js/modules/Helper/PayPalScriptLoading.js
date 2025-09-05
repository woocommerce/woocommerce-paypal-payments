import { loadScript } from '@paypal/paypal-js';
import dataClientIdAttributeHandler from '../DataClientIdAttributeHandler';
import widgetBuilder from '../Renderer/WidgetBuilder';
import { processConfig } from './ConfigProcessor';

const loadedScripts = new Map();
const scriptPromises = new Map();

const handleDataClientIdAttribute = async ( scriptOptions, config ) => {
	if (
		config.data_client_id?.set_attribute &&
		config.vault_v3_enabled !== true
	) {
		return new Promise( ( resolve, reject ) => {
			dataClientIdAttributeHandler(
				scriptOptions,
				config.data_client_id,
				( paypal ) => {
					widgetBuilder.setPaypal( paypal );
					resolve( paypal );
				},
				reject
			);
		} );
	}
	return null;
};

export const loadPayPalScript = async ( namespace, config ) => {
	if ( ! namespace ) {
		throw new Error( 'Namespace is required' );
	}

	if ( loadedScripts.has( namespace ) ) {
		console.log( `Script already loaded for namespace: ${ namespace }` );
		return loadedScripts.get( namespace );
	}

	if ( scriptPromises.has( namespace ) ) {
		console.log(
			`Script loading in progress for namespace: ${ namespace }`
		);
		return scriptPromises.get( namespace );
	}

	const scriptOptions = {
		...processConfig( config ),
		'data-namespace': namespace,
	};

	const dataClientIdResult = await handleDataClientIdAttribute(
		scriptOptions,
		config
	);
	if ( dataClientIdResult ) {
		return dataClientIdResult;
	}

	const scriptPromise = new Promise( ( resolve, reject ) => {
		loadScript( scriptOptions )
			.then( ( script ) => {
				widgetBuilder.setPaypal( script );
				loadedScripts.set( namespace, script );
				console.log( `Script loaded for namespace: ${ namespace }` );
				resolve( script );
			} )
			.catch( ( error ) => {
				console.error(
					`Failed to load script for namespace: ${ namespace }`,
					error
				);
				reject( error );
			} )
			.finally( () => {
				scriptPromises.delete( namespace );
			} );
	} );

	scriptPromises.set( namespace, scriptPromise );
	return scriptPromise;
};

export const loadAndRenderPayPalScript = async (
	namespace,
	options,
	renderFunction,
	renderTarget
) => {
	if ( ! namespace ) {
		throw new Error( 'Namespace is required' );
	}

	const scriptOptions = {
		...options,
		'data-namespace': namespace,
	};

	const script = await loadScript( scriptOptions );
	widgetBuilder.setPaypal( script );
	await renderFunction( script, renderTarget );
};
