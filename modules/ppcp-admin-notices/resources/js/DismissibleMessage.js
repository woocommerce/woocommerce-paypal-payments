export default class DismissibleMessage {
	#notice = null;

	#muteConfig = {};

	#closeButton = null;

	#msgId = '';

	constructor( noticeElement, muteConfig ) {
		this.#notice = noticeElement;
		this.#muteConfig = muteConfig;
		this.#msgId = this.#notice.dataset.ppcpMsgId;

		// Quick sanitation.
		if ( ! this.#muteConfig?.endpoint || ! this.#muteConfig?.nonce ) {
			console.error( 'Ajax config (Mute):', this.#muteConfig );
			throw new Error(
				'Invalid ajax configuration for DismissibleMessage. Nonce/Endpoint missing'
			);
		}
		if ( ! this.#msgId ) {
			console.error( 'Notice Element:', this.#notice );
			throw new Error(
				'Invalid notice element passed to DismissibleMessage. No MsgId defined'
			);
		}

		this.onDismissClickProxy = this.onDismissClickProxy.bind( this );
		this.enableCloseButtons = this.enableCloseButtons.bind( this );
		this.disableCloseButtons = this.disableCloseButtons.bind( this );
		this.dismiss = this.dismiss.bind( this );

		this.addEventListeners();
	}

	get id() {
		return this.#msgId;
	}

	get closeButton() {
		if ( ! this.#closeButton ) {
			this.#closeButton = this.#notice.querySelector(
				'button.notice-dismiss'
			);
		}

		return this.#closeButton;
	}

	addEventListeners() {
		this.#notice.addEventListener(
			'click',
			this.onDismissClickProxy,
			true
		);
	}

	removeEventListeners() {
		this.#notice.removeEventListener(
			'click',
			this.onDismissClickProxy,
			true
		);
	}

	onDismissClickProxy( event ) {
		if ( ! event.target?.matches( 'button.notice-dismiss' ) ) {
			return;
		}

		this.disableCloseButtons();
		this.muteMessage();

		event.preventDefault();
		event.stopPropagation();
		return false;
	}

	disableCloseButtons() {
		this.closeButton.setAttribute( 'disabled', 'disabled' );
		this.closeButton.style.pointerEvents = 'none';
		this.closeButton.style.opacity = 0;
	}

	enableCloseButtons() {
		this.closeButton.removeAttribute( 'disabled', 'disabled' );
		this.closeButton.style.pointerEvents = '';
		this.closeButton.style.opacity = '';
	}

	showSpinner() {
		const spinner = document.createElement( 'span' );
		spinner.classList.add( 'spinner', 'is-active', 'doing-ajax' );

		this.#notice.appendChild( spinner );
	}

	/**
	 * Mute the message (on server side) and dismiss it (in browser).
	 */
	muteMessage() {
		this.#ajaxMuteMessage().then( this.dismiss );
	}

	/**
	 * Start an ajax request that marks the message as "muted" on server side.
	 *
	 * @return {Promise<any>} Resolves after the ajax request is completed.
	 */
	#ajaxMuteMessage() {
		this.showSpinner();

		const ajaxData = {
			id: this.id,
			nonce: this.#muteConfig.nonce,
		};

		return fetch( this.#muteConfig.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( ajaxData ),
		} ).then( ( response ) => response.json() );
	}

	/**
	 * Proxy to the original dismiss logic provided by WP core JS.
	 */
	dismiss() {
		this.removeEventListeners();
		this.enableCloseButtons();

		this.closeButton.dispatchEvent( new Event( 'click' ) );
	}
}
