import { loadSdkV6 } from '@ppcp-sdk-v6/sdkLoader';
import { fastlaneSdkV6Config } from '@ppcp-sdk-v6/utils/config';

class Fastlane {
	constructor( namespace ) {
		this.namespace = namespace;
		this.connection = null;
		this.identity = null;
		this.profile = null;
		this.FastlaneCardComponent = null;
		this.FastlanePaymentComponent = null;
		this.FastlaneWatermarkComponent = null;
	}

	/**
	 * Builds the Fastlane instance from whichever SDK owns the page.
	 *
	 * @param {Object} config - The v5 Fastlane options bag.
	 * @return {Promise<void>} Resolves once the members are available.
	 */
	connect( config ) {
		// Resolved here rather than passed in, so neither caller (AxoManager and
		// the block's useFastlaneSdk) has to know which SDK backs Fastlane.
		const v6Config = fastlaneSdkV6Config();

		return v6Config
			? this.connectV6( v6Config, config )
			: this.connectV5( config );
	}

	/**
	 * Takes Fastlane off the shared v6 SDK instance.
	 *
	 * loadSdkV6 memoizes that instance, so this shares one instance and one
	 * client token with the buttons and card fields on the page rather than
	 * loading a second SDK.
	 *
	 * @param {Object} v6Config - The wc_ppcp_sdk_v6 config.
	 * @param {Object} config   - The v5 Fastlane options bag.
	 * @return {Promise<void>} Resolves once the members are available.
	 */
	async connectV6( v6Config, config ) {
		const sdkInstance = await loadSdkV6(
			v6Config,
			v6Config.page_context || 'checkout'
		);

		// The bag is v5's shape. v6 documents createFastlane() with no arguments
		// and gives locale, styles, cardOptions.allowedBrands and
		// shippingAddressOptions.allowedLocations no other home, so it is passed
		// through: an ignored argument costs nothing, while dropping it would
		// silently lose the merchant's card-brand and shipping restrictions.
		this.init( await sdkInstance.createFastlane( config ) );
	}

	/**
	 * @param {Object} config - The v5 Fastlane options bag.
	 * @return {Promise<void>} Resolves once the members are available.
	 */
	connectV5( config ) {
		return new Promise( ( resolve, reject ) => {
			if ( ! window[ this.namespace ] ) {
				reject(
					new Error(
						`Namespace ${ this.namespace } not found on window object`
					)
				);
				return;
			}

			window[ this.namespace ]
				.Fastlane( config )
				.then( ( result ) => {
					this.init( result );
					resolve();
				} )
				.catch( ( error ) => {
					console.error( error );
					reject( error );
				} );
		} );
	}

	init( connection ) {
		this.connection = connection;
		this.identity = this.connection.identity;
		this.profile = this.connection.profile;
		this.FastlaneCardComponent = this.connection.FastlaneCardComponent;
		this.FastlanePaymentComponent =
			this.connection.FastlanePaymentComponent;
		this.FastlaneWatermarkComponent =
			this.connection.FastlaneWatermarkComponent;
	}

	setLocale( locale ) {
		this.connection.setLocale( locale );
	}
}

export default Fastlane;
