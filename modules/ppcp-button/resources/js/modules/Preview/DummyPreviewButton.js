import PreviewButton from './PreviewButton';

/**
 * Dummy preview button, to use in case an APM button cannot be rendered
 */
export default class DummyPreviewButton extends PreviewButton {
	#innerEl;

	constructor( args ) {
		super( args );

		this.selector = `${ args.selector }Dummy`;
		this.label = args.label || 'Not Available';
	}

	createNewWrapper() {
		const wrapper = super.createNewWrapper();
		wrapper.classList.add( 'ppcp-button-apm', 'ppcp-button-dummy' );

		return wrapper;
	}

	createButton( buttonConfig ) {
		this.#innerEl?.remove();

		this.#innerEl = document.createElement( 'div' );
		this.#innerEl.innerHTML = `<div class="reason">${ this.label }</div>`;

		this.domWrapper.appendChild( this.#innerEl );
	}
}
