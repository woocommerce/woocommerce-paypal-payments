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

	connect( config ) {
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
