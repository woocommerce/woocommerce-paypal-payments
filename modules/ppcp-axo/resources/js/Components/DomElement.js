class DomElement {
	constructor( config ) {
		this.$ = jQuery;
		this.config = config;
		this.selector = this.config.selector;
		this.id = this.config.id || null;
		this.className = this.config.className || null;
		this.attributes = this.config.attributes || null;
		this.anchorSelector = this.config.anchorSelector || null;
	}

	trigger( action ) {
		this.$( this.selector ).trigger( action );
	}

	on( action, callable ) {
		this.$( document ).on( action, this.selector, callable );
	}

	hide() {
		this.$( this.selector ).hide();
	}

	show() {
		this.$( this.selector ).show();
	}

	click() {
		this.get().click();
	}

	get() {
		return document.querySelector( this.selector );
	}
}

export default DomElement;
