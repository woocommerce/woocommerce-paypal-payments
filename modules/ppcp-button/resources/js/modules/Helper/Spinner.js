class Spinner {
	constructor( target = 'form.woocommerce-checkout' ) {
		this.target = target;
	}

	setTarget( target ) {
		this.target = target;
	}

	block() {
		jQuery( this.target ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
			baseZ: 10000,
		} );
	}

	unblock() {
		jQuery( this.target ).unblock();
	}

	static fullPage() {
		return new Spinner( window );
	}
}
export default Spinner;
