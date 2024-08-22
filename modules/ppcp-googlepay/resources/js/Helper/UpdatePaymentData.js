class UpdatePaymentData {
	constructor( config ) {
		this.config = config;
	}

	update( paymentData ) {
		return new Promise( ( resolve, reject ) => {
			fetch( this.config.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: this.config.nonce,
					paymentData,
				} ),
			} )
				.then( ( result ) => result.json() )
				.then( ( result ) => {
					if ( ! result.success ) {
						return;
					}

					resolve( result.data );
				} );
		} );
	}
}

export default UpdatePaymentData;
