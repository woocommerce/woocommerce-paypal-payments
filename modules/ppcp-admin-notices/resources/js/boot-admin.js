import DismissibleMessage from './DismissibleMessage';

class AdminMessageHandler {
	#config = {};

	constructor( config ) {
		this.#config = config;
		this.setupDismissibleMessages();
	}

	/**
	 * Finds all mutable admin messages in the DOM and initializes them.
	 */
	setupDismissibleMessages() {
		const muteConfig = this.#config?.ajax?.mute_message;

		const addDismissibleMessage = ( element ) => {
			new DismissibleMessage( element, muteConfig );
		};

		document
			.querySelectorAll( '.notice[data-ppcp-msg-id]' )
			.forEach( addDismissibleMessage );
	}
}

new AdminMessageHandler( window.wc_admin_notices );
