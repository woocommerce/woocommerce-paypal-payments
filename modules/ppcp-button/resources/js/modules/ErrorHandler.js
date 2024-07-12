class ErrorHandler {
	/**
	 * @param {string}  genericErrorText
	 * @param {Element} wrapper
	 */
	constructor( genericErrorText, wrapper ) {
		this.genericErrorText = genericErrorText;
		this.wrapper = wrapper;
	}

	genericError() {
		this.clear();
		this.message( this.genericErrorText );
	}

	appendPreparedErrorMessageElement( errorMessageElement ) {
		this._getMessageContainer().replaceWith( errorMessageElement );
	}

	/**
	 * @param {string} text
	 */
	message( text ) {
		this._addMessage( text );

		this._scrollToMessages();
	}

	/**
	 * @param {Array} texts
	 */
	messages( texts ) {
		texts.forEach( ( t ) => this._addMessage( t ) );

		this._scrollToMessages();
	}

	/**
	 * @return {string}
	 */
	currentHtml() {
		const messageContainer = this._getMessageContainer();
		return messageContainer.outerHTML;
	}

	/**
	 * @private
	 * @param {string} text
	 */
	_addMessage( text ) {
		if ( ! typeof String || text.length === 0 ) {
			throw new Error( 'A new message text must be a non-empty string.' );
		}

		const messageContainer = this._getMessageContainer();

		const messageNode = this._prepareMessageElement( text );
		messageContainer.appendChild( messageNode );
	}

	/**
	 * @private
	 */
	_scrollToMessages() {
		jQuery.scroll_to_notices( jQuery( '.woocommerce-error' ) );
	}

	/**
	 * @private
	 */
	_getMessageContainer() {
		let messageContainer = document.querySelector( 'ul.woocommerce-error' );
		if ( messageContainer === null ) {
			messageContainer = document.createElement( 'ul' );
			messageContainer.setAttribute( 'class', 'woocommerce-error' );
			messageContainer.setAttribute( 'role', 'alert' );
			jQuery( this.wrapper ).prepend( messageContainer );
		}
		return messageContainer;
	}

	/**
	 * @param message
	 * @private
	 */
	_prepareMessageElement( message ) {
		const li = document.createElement( 'li' );
		li.innerHTML = message;

		return li;
	}

	clear() {
		jQuery( '.woocommerce-error, .woocommerce-message' ).remove();
	}
}

export default ErrorHandler;
