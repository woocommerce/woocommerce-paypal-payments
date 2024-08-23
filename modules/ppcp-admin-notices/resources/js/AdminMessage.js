export default class AdminMessage {
	#notice = null;

	#muteConfig = {};

	#closeButton = null;

	#msgId = '';

	constructor( noticeElement, muteConfig ) {
		this.#notice = noticeElement;
		this.#muteConfig = muteConfig;
		this.#msgId = noticeElement.dataset.ppcpMsgId;

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

	muteMessage() {
		this.ajaxMuteMessage().then( this.dismiss );
	}

	ajaxMuteMessage() {
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

	dismiss() {
		this.removeEventListeners();
		this.enableCloseButtons();

		this.closeButton.dispatchEvent( new Event( 'click' ) );
	}
}
