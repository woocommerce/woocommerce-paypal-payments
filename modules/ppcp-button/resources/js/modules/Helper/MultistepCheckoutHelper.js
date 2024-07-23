import { refreshButtons } from './ButtonRefreshHelper';

const DEFAULT_TRIGGER_ELEMENT_SELECTOR = '.woocommerce-checkout-payment';

/**
 * The MultistepCheckoutHelper class ensures the initialization of payment buttons
 * on websites using a multistep checkout plugin. These plugins usually hide the
 * payment button on page load up and reveal it later using JS. During the
 * invisibility period of wrappers, some payment buttons fail to initialize,
 * so we wait for the payment element to be visible.
 *
 * @property {HTMLElement} form           - Checkout form element.
 * @property {HTMLElement} triggerElement - Element, which visibility we need to detect.
 * @property {boolean}     isVisible      - Whether the triggerElement is visible.
 */
class MultistepCheckoutHelper {
	/**
	 * Selector that defines the HTML element we are waiting to become visible.
	 * @type {string}
	 */
	#triggerElementSelector;

	/**
	 * Interval (in milliseconds) in which the visibility of the trigger element is checked.
	 * @type {number}
	 */
	#intervalTime = 150;

	/**
	 * The interval ID returned by the setInterval() method.
	 * @type {number|false}
	 */
	#intervalId;

	/**
	 * Selector passed to the constructor that identifies the checkout form
	 * @type {string}
	 */
	#formSelector;

	/**
	 * @param {string} formSelector           - Selector of the checkout form
	 * @param {string} triggerElementSelector - Optional. Selector of the dependant element.
	 */
	constructor( formSelector, triggerElementSelector = '' ) {
		this.#formSelector = formSelector;
		this.#triggerElementSelector =
			triggerElementSelector || DEFAULT_TRIGGER_ELEMENT_SELECTOR;
		this.#intervalId = false;

		/*
         Start the visibility checker after a brief delay. This allows eventual multistep plugins to
         dynamically prepare the checkout page, so we can decide whether this helper is needed.
         */
		setTimeout( () => {
			if ( this.form && ! this.isVisible ) {
				this.start();
			}
		}, 250 );
	}

	/**
	 * The checkout form element.
	 * @return {Element|null} - Form element or null.
	 */
	get form() {
		return document.querySelector( this.#formSelector );
	}

	/**
	 * The element which must be visible before payment buttons should be initialized.
	 * @return {Element|null} - Trigger element or null.
	 */
	get triggerElement() {
		return this.form?.querySelector( this.#triggerElementSelector );
	}

	/**
	 * Checks the visibility of the payment button wrapper.
	 * @return {boolean} - returns boolean value on the basis of visibility of element.
	 */
	get isVisible() {
		const box = this.triggerElement?.getBoundingClientRect();

		return !! ( box && box.width && box.height );
	}

	/**
	 * Starts the observation of the DOM, initiates monitoring the checkout form.
	 * To ensure multiple calls to start don't create multiple intervals, we first call stop.
	 */
	start() {
		this.stop();
		this.#intervalId = setInterval(
			() => this.checkElement(),
			this.#intervalTime
		);
	}

	/**
	 * Stops the observation of the checkout form.
	 * Multiple calls to stop are safe as clearInterval doesn't throw if provided ID doesn't exist.
	 */
	stop() {
		if ( this.#intervalId ) {
			clearInterval( this.#intervalId );
			this.#intervalId = false;
		}
	}

	/**
	 * Checks if the trigger element is visible.
	 * If visible, it initialises the payment buttons and stops the observation.
	 */
	checkElement() {
		if ( this.isVisible ) {
			refreshButtons();
			this.stop();
		}
	}
}

export default MultistepCheckoutHelper;
