describe( 'fraudnet.js hosted_fields_loaded listener', () => {
	beforeAll( () => {
		global.FraudNetConfig = {
			f: 'fraudnet-session-id',
			s: 'store-id',
			sandbox: '0',
		};
		require( './fraudnet.js' );
		window.dispatchEvent( new Event( 'load' ) );
	} );

	beforeEach( () => {
		delete window.PAYPAL;
	} );

	it( 'does not throw when window.PAYPAL is not defined (e.g. c.paypal.com/da/r/fb.js was blocked or has not finished loading yet)', () => {
		expect( () => {
			document.dispatchEvent( new CustomEvent( 'hosted_fields_loaded' ) );
		} ).not.toThrow();
	} );

	it( 'calls window.PAYPAL.asyncData.initAndCollect() when it is available', () => {
		const initAndCollect = jest.fn();
		window.PAYPAL = { asyncData: { initAndCollect } };

		document.dispatchEvent( new CustomEvent( 'hosted_fields_loaded' ) );

		expect( initAndCollect ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not throw and does not call initAndCollect when window.PAYPAL.asyncData.initAndCollect is not a function', () => {
		window.PAYPAL = { asyncData: {} };

		expect( () => {
			document.dispatchEvent( new CustomEvent( 'hosted_fields_loaded' ) );
		} ).not.toThrow();
	} );
} );
