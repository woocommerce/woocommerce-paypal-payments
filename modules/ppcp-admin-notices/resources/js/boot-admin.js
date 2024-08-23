import AdminMessage from './AdminMessage';

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
		document
			.querySelectorAll( '.notice[data-ppcp-msg-id]' )
			.forEach( ( notice ) => {
				const adminMessage = new AdminMessage(
					notice,
					this.#config.ajax.mute_message
				);

				this.#notices.set( adminMessage.id, adminMessage );
			} );
	}
}

new AdminMessageHandler( window.wc_admin_notices );
