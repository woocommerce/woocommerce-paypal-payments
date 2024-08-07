import ConsoleLogger from '../../../../../ppcp-wc-gateway/resources/js/helper/ConsoleLogger';
import { apmButtonsInit } from '../Helper/ApmButtons';
import { PaymentContext } from '../Helper/CheckoutMethodState';
import {
	ButtonEvents,
	dispatchButtonEvent,
	observeButtonEvent,
} from '../Helper/PaymentButtonHelpers';

/**
 * Collection of all available styling options for this button.
 *
 * @typedef {Object} StylesCollection
 * @property {string} Default  - Default button styling.
 * @property {string} MiniCart - Styles for mini-cart button.
 */

/**
 * Collection of all available wrapper IDs that are possible for the button.
 *
 * @typedef {Object} WrapperCollection
 * @property {string} Default     - Default button wrapper.
 * @property {string} Gateway     - Wrapper for separate gateway.
 * @property {string} Block       - Wrapper for block checkout button.
 * @property {string} MiniCart    - Wrapper for mini-cart button.
 * @property {string} SmartButton - Wrapper for smart button container.
 */

/**
 * Base class for APM payment buttons, like GooglePay and ApplePay.
 *
 * This class is not intended for the PayPal button.
 */
export default class PaymentButton {
	/**
	 * @type {ConsoleLogger}
	 */
	#logger;

	/**
	 * @type {string}
	 */
	#methodId;

	/**
	 * Whether the payment button is initialized.
	 *
	 * @type {boolean}
	 */
	#isInitialized = false;

	/**
	 * The button's context.
	 *
	 * @type {string}
	 */
	#context;

	/**
	 * Object containing the IDs of all possible wrapper elements that might contain this
	 * button; only one wrapper is relevant, depending on the value of the context.
	 *
	 * @type {Object}
	 */
	#wrappers;

	/**
	 * @type {StylesCollection}
	 */
	#styles;

	/**
	 * APM relevant configuration; e.g., configuration of the GooglePay button
	 */
	#buttonConfig;

	/**
	 * Plugin-wide configuration; i.e., PayPal client ID, shop currency, etc.
	 */
	#ppcpConfig;

	/**
	 * Whether the current browser/website support the payment method.
	 *
	 * @type {boolean}
	 */
	#isEligible = false;

	/**
	 * Whether this button is visible. Modified by `show()` and `hide()`
	 *
	 * @type {boolean}
	 */
	#isVisible = true;

	/**
	 * The currently visible payment button.
	 *
	 * @see {PaymentButton.insertButton}
	 * @type {HTMLElement|null}
	 */
	#button = null;

	/**
	 * Initialize the payment button instance.
	 *
	 * @param {string}            methodId     - Payment method ID (slug, e.g., "ppcp-googlepay").
	 * @param {string}            context      - Button context name.
	 * @param {WrapperCollection} wrappers     - Button wrapper IDs, by context.
	 * @param {StylesCollection}  styles       - Button styles, by context.
	 * @param {Object}            buttonConfig - Payment button specific configuration.
	 * @param {Object}            ppcpConfig   - Plugin wide configuration object.
	 */
	constructor(
		methodId,
		context,
		wrappers,
		styles,
		buttonConfig,
		ppcpConfig
	) {
		const methodName = methodId.replace( /^ppcp?-/, '' );

		this.#methodId = methodId;

		this.#logger = new ConsoleLogger( methodName, context );
		this.#logger.enabled = !! buttonConfig?.is_debug;

		this.#context = context;
		this.#wrappers = wrappers;
		this.#styles = styles;
		this.#buttonConfig = buttonConfig;
		this.#ppcpConfig = ppcpConfig;

		apmButtonsInit( ppcpConfig );
		this.initEventListeners();
	}

	/**
	 * Internal ID of the payment gateway.
	 *
	 * @readonly
	 * @return {string} The internal gateway ID.
	 */
	get methodId() {
		return this.#methodId;
	}

	/**
	 * Whether the payment button was fully initialized.
	 *
	 * @readonly
	 * @return {boolean} True indicates, that the button was fully initialized.
	 */
	get isInitialized() {
		return this.#isInitialized;
	}

	/**
	 * The button's context.
	 *
	 * TODO: Convert the string to a context-object (primitive obsession smell)
	 *
	 * @readonly
	 * @return {string} The button context.
	 */
	get context() {
		return this.#context;
	}

	/**
	 * Button wrapper details.
	 *
	 * @readonly
	 * @return {WrapperCollection} Wrapper IDs.
	 */
	get wrappers() {
		return this.#wrappers;
	}

	/**
	 * Returns the context-relevant button style object.
	 *
	 * @readonly
	 * @return {string} Styling options.
	 */
	get style() {
		if ( PaymentContext.MiniCart === this.context ) {
			return this.#styles.MiniCart;
		}

		return this.#styles.Default;
	}

	/**
	 * Returns the context-relevant wrapper ID.
	 *
	 * @readonly
	 * @return {string} The wrapper-element's ID (without the `#` prefix).
	 */
	get wrapperId() {
		if ( PaymentContext.MiniCart === this.context ) {
			return this.wrappers.MiniCart;
		} else if ( this.isSeparateGateway ) {
			return this.wrappers.Gateway;
		} else if ( PaymentContext.Blocks.includes( this.context ) ) {
			return this.wrappers.Block;
		}

		return this.wrappers.Default;
	}

	/**
	 * Determines if the current payment button should be rendered as a stand-alone gateway.
	 * The return value `false` usually means, that the payment button is bundled with all available
	 * payment buttons.
	 *
	 * The decision depends on the button context (placement) and the plugin settings.
	 *
	 * @return {boolean} True, if the current button represents a stand-alone gateway.
	 */
	get isSeparateGateway() {
		return (
			this.#buttonConfig.is_wc_gateway_enabled &&
			PaymentContext.Gateways.includes( this.context )
		);
	}

	/**
	 * Determines if the current button instance has valid and complete configuration details.
	 * Used during initialization to decide if the button can be initialized or should be skipped.
	 *
	 * Can be implemented by the derived class.
	 *
	 * @return {boolean} True indicates the config is valid and initialization can continue.
	 */
	get isConfigValid() {
		return true;
	}

	/**
	 * Whether the browser can accept this payment method.
	 *
	 * @return {boolean} True, if payments are technically possible.
	 */
	get isEligible() {
		return this.#isEligible;
	}

	/**
	 * Changes the eligibility state of this button component.
	 *
	 * @param {boolean} newState Whether the browser can accept payments.
	 */
	set isEligible( newState ) {
		if ( newState === this.#isEligible ) {
			return;
		}

		this.#isEligible = newState;
		this.triggerRedraw();
	}

	/**
	 * The visibility state of the button.
	 * This flag does not reflect actual visibility on the page, but rather, if the button
	 * is intended/allowed to be displayed, in case all other checks pass.
	 *
	 * @return {boolean} True indicates, that the button can be displayed.
	 */
	get isVisible() {
		return this.#isVisible;
	}

	/**
	 * Change the visibility of the button.
	 *
	 * A visible button does not always force the button to render on the page. It only means, that
	 * the button is allowed or not allowed to render, if certain other conditions are met.
	 *
	 * @param {boolean} newState Whether rendering the button is allowed.
	 */
	set isVisible( newState ) {
		if ( this.#isVisible === newState ) {
			return;
		}

		this.#isVisible = newState;
		this.triggerRedraw();
	}

	/**
	 * Returns the HTML element that wraps the current button
	 *
	 * @readonly
	 * @return {HTMLElement|null} The wrapper element, or null.
	 */
	get wrapperElement() {
		return document.getElementById( this.wrapperId );
	}

	/**
	 * Checks whether the main button-wrapper is present in the current DOM.
	 *
	 * @readonly
	 * @return {boolean} True, if the button context (wrapper element) is found.
	 */
	get isPresent() {
		return this.wrapperElement instanceof HTMLElement;
	}

	/**
	 * Returns an array of HTMLElements that belong to the payment button.
	 *
	 * @readonly
	 * @return {HTMLElement[]} List of payment button wrapper elements.
	 */
	get allElements() {
		const selectors = [];

		// Payment button (Pay now, smart button block)
		selectors.push( `#${ this.wrapperId }` );

		// Block Checkout: Express checkout button.
		if ( PaymentContext.Blocks.includes( this.context ) ) {
			selectors.push( `#${ this.wrappers.Block }` );
		}

		// Classic Checkout: Separate gateway.
		if ( this.isSeparateGateway ) {
			selectors.push(
				`.wc_payment_method.payment_method_${ this.methodId }`
			);
		}

		this.log( 'Wrapper Elements:', selectors );
		return /** @type {HTMLElement[]} */ selectors.flatMap( ( selector ) =>
			Array.from( document.querySelectorAll( selector ) )
		);
	}

	/**
	 * Log a debug detail to the browser console.
	 *
	 * @param {any} args
	 */
	log( ...args ) {
		this.#logger.log( ...args );
	}

	/**
	 * Log an error message to the browser console.
	 *
	 * @param {any} args
	 */
	error( ...args ) {
		this.#logger.error( ...args );
	}

	/**
	 * Must be named `init()` to simulate "protected" visibility:
	 * Since the derived class also implements a method with the same name, this method can only
	 * be called by the derived class, but not from any other code.
	 *
	 * @protected
	 */
	init() {
		this.#isInitialized = true;
	}

	/**
	 * Must be named `reinit()` to simulate "protected" visibility:
	 * Since the derived class also implements a method with the same name, this method can only
	 * be called by the derived class, but not from any other code.
	 *
	 * @protected
	 */
	reinit() {
		this.#isInitialized = false;
	}

	triggerRedraw() {
		dispatchButtonEvent( {
			event: ButtonEvents.REDRAW,
			paymentMethod: this.methodId,
		} );
	}

	/**
	 * Attaches event listeners to show or hide the payment button when needed.
	 */
	initEventListeners() {
		// Refresh the button - this might show, hide or re-create the payment button.
		observeButtonEvent( {
			event: ButtonEvents.REDRAW,
			paymentMethod: this.methodId,
			callback: () => this.refresh(),
		} );

		// Events relevant for buttons inside a payment gateway.
		if ( PaymentContext.Gateways.includes( this.context ) ) {
			// Hide the button right after the user selected _any_ gateway.
			observeButtonEvent( {
				event: ButtonEvents.INVALIDATE,
				callback: () => ( this.isVisible = false ),
			} );

			// Show the button (again) when the user selected the current gateway.
			observeButtonEvent( {
				event: ButtonEvents.RENDER,
				paymentMethod: this.methodId,
				callback: () => ( this.isVisible = true ),
			} );
		}
	}

	/**
	 * Refreshes the payment button on the page.
	 */
	refresh() {
		const showButtonWrapper = () => {
			this.log( 'Show' );

			const styleSelectors = `style[data-hide-gateway="${ this.methodId }"]`;

			document
				.querySelectorAll( styleSelectors )
				.forEach( ( el ) => el.remove() );

			this.allElements.forEach( ( element ) => {
				element.style.display = 'block';
			} );
		};

		const hideButtonWrapper = () => {
			this.log( 'Hide' );

			this.allElements.forEach( ( element ) => {
				element.style.display = 'none';
			} );
		};

		// Refresh or hide the actual payment button.
		if ( this.isVisible ) {
			this.addButton();
		} else {
			this.removeButton();
		}

		// Show the wrapper or gateway entry, i.e. add space for the button.
		if ( this.isEligible && this.isPresent ) {
			showButtonWrapper();
		} else {
			hideButtonWrapper();
		}
	}

	/**
	 * Prepares the button wrapper element and inserts the provided payment button into the DOM.
	 *
	 * @param {HTMLElement} button - The button element to inject.
	 */
	insertButton( button ) {
		if ( ! this.isPresent ) {
			return;
		}

		if ( this.#button ) {
			this.#button.remove();
		}

		this.#button = button;
		this.log( 'addButton', button );

		const wrapper = this.wrapperElement;
		const { shape, height } = this.style;
		const methodSlug = this.methodId.replace( /^ppcp?-/, '' );

		wrapper.classList.add(
			`ppcp-button-${ shape }`,
			'ppcp-button-apm',
			`ppcp-button-${ methodSlug }`
		);

		if ( height ) {
			wrapper.style.height = `${ height }px`;
		}

		wrapper.appendChild( button );
	}

	/**
	 * Removes the payment button from the DOM.
	 */
	removeButton() {
		if ( ! this.isPresent ) {
			return;
		}

		this.log( 'removeButton' );

		if ( this.#button ) {
			this.#button.remove();
		}
		this.#button = null;
	}
}
