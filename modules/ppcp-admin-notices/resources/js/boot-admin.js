import DismissibleMessage from './DismissibleMessage';

class AdminMessageHandler {
	#notices = new Map();

	#config = {};

	constructor( config ) {
		this.#config = config;
		this.setupMessages();
	}

	/**
	 * Finds all mutable admin messages in the DOM and initializes them.
	 */
	setupMessages() {
		const muteConfig = this.#config?.ajax?.mute_message;
		const addDismissibleMessage = ( element ) => {
			try {
				const message = new DismissibleMessage( element, muteConfig );
				this.#notices.set( message.id, message );
			} catch ( ex ) {
				// Skip invalid elements, continue with next notice.
			}
		};

		document
			.querySelectorAll( '.notice[data-ppcp-msg-id]' )
			.forEach( addDismissibleMessage );
	}
}

new AdminMessageHandler( window.wc_admin_notices );
