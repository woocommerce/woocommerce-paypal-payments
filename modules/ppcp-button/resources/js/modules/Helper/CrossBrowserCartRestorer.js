import ResumeFlowHelper from './ResumeFlowHelper';
import Spinner from './Spinner';

/**
 * Handles cross-browser cart restoration for AppSwitch flows
 */
class CrossBrowserCartRestorer {
	constructor( config ) {
		this.config = config;
	}

	/**
	 * Check if we should restore (create cross-browser order)
	 *
	 * @return {boolean} True if this is a cross-browser AppSwitch return
	 */
	shouldRestore() {
		if ( ! this.isAppSwitchReturn() ) {
			return false;
		}

		const cartKey = this.getCartKeyFromHash();
		const savedCartHash = this.getSavedCartHashFromHash();

		if ( ! cartKey || ! savedCartHash ) {
			return false;
		}

		const currentCartHash = this.config.cart_hash;

		// If cart hashes match, no need for cross-browser handling.
		return currentCartHash !== savedCartHash;
	}

	restore() {
		const cartKey = this.getCartKeyFromHash();
		this.createCrossBrowserOrder( cartKey );
	}

	createCrossBrowserOrder( cartKey ) {
		const endpointConfig = this.config.ajax?.create_cross_browser_order;

		if ( ! endpointConfig || ! endpointConfig.endpoint ) {
			console.error(
				'Create cross-browser order endpoint not configured'
			);
			return;
		}

		const spinner = Spinner.fullPage();
		spinner.block();

		fetch( endpointConfig.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				nonce: endpointConfig.nonce,
				cart_key: cartKey,
			} ),
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				const { success, data } = response;

				if ( ! success ) {
					console.error(
						'Cross-browser order creation failed:',
						data?.message
					);
					return;
				}

				if ( ! data.redirect ) {
					console.error(
						'Missing redirect URL in cross-browser order creation response'
					);
					return;
				}

				this.cleanCrossBrowserAppSwitchParams();
				window.location.href = data.redirect + window.location.hash;
			} )
			.catch( ( error ) => {
				console.error( 'Error creating cross-browser order:', error );
			} )
			.finally( () => spinner.unblock() );
	}

	/**
	 * Clean cross-browser AppSwitch params from hash while preserving PayPal's params
	 */
	cleanCrossBrowserAppSwitchParams() {
		if ( ! window.location.hash ) {
			return;
		}

		const CROSS_BROWSER_APPSWITCH_PARAMS = [
			'pcp-return',
			'pcp-cart',
			'pcp-cart-hash',
		];

		const hashString = window.location.hash.substring( 1 );
		const params = hashString.split( '&' );

		const cleanedParams = params.filter( ( param ) => {
			const paramName = param.split( '=' )[ 0 ];
			return ! CROSS_BROWSER_APPSWITCH_PARAMS.includes( paramName );
		} );

		const baseUrl = window.location.pathname + window.location.search;

		const newHash = '#' + cleanedParams.join( '&' );
		window.history.replaceState( null, '', baseUrl + newHash );
	}

	isAppSwitchReturn() {
		const params = this.getHashParams();
		return params[ 'pcp-return' ] === 'button';
	}

	getCartKeyFromHash() {
		const params = this.getHashParams();
		return params[ 'pcp-cart' ] || null;
	}

	getSavedCartHashFromHash() {
		const params = this.getHashParams();
		return params[ 'pcp-cart-hash' ] || null;
	}

	getHashParams() {
		if ( ! window.location.hash ) {
			return {};
		}

		const hashString = window.location.hash.substring( 1 );
		const params = new URLSearchParams( hashString );

		return Object.fromEntries( params );
	}
}

export default CrossBrowserCartRestorer;
