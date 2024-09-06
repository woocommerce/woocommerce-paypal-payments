import ConsoleLogger from '../../../../../ppcp-wc-gateway/resources/js/helper/ConsoleLogger';
import { apmButtonsInit } from '../Helper/ApmButtons';
import {
	getCurrentPaymentMethod,
	PaymentContext,
	PaymentMethods,
} from '../Helper/CheckoutMethodState';
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
 * Adds the provided PaymentButton instance to a global payment-button collection.
 *
 * This is debugging logic that should not be used on a production site.
 *
 * @param {string}        methodName - Used to group the buttons.
 * @param {PaymentButton} button     - Appended to the button collection.
 */
const addToDebuggingCollection = ( methodName, button ) => {
	window.ppcpPaymentButtonList = window.ppcpPaymentButtonList || {};

	const collection = window.ppcpPaymentButtonList;

	collection[ methodName ] = collection[ methodName ] || [];
	collection[ methodName ].push( button );
};

/**
 * Provides a context-independent instance Map for `PaymentButton` components.
 *
 * This function addresses a potential issue in multi-context environments, such as pages using
 * Block-components. In these scenarios, multiple React execution contexts can lead to duplicate
 * `PaymentButton` instances. To prevent this, we store instances in a `Map` that is bound to the
 * document's `body` (the rendering context) rather than to individual React components
 * (execution contexts).
 *
 * The `Map` is created as a non-enumerable, non-writable, and non-configurable property of
 * `document.body` to ensure its integrity and prevent accidental modifications.
 *
 * @return {Map<any, any>} A Map containing all `PaymentButton` instances for the current page.
 */
const getInstances = () => {
	const collectionKey = '__ppcpPBInstances';

	if ( ! document.body[ collectionKey ] ) {
		Object.defineProperty( document.body, collectionKey, {
			value: new Map(),
			enumerable: false,
			writable: false,
			configurable: false,
		} );
	}

	return document.body[ collectionKey ];
};

/**
 * Base class for APM payment buttons, like GooglePay and ApplePay.
 *
 * This class is not intended for the PayPal button.
 */
export default class PaymentButton {
	/**
	 * Defines the implemented payment method.
	 *
	 * Used to identify and address the button internally.
	 * Overwrite this in the derived class.
	 *
	 * @type {string}
	 */
	static methodId = 'generic';

	/**
	 * CSS class that is added to the payment button wrapper.
	 *
	 * Overwrite this in the derived class.
	 *
	 * @type {string}
	 */
	static cssClass = '';

	/**
	 * @type {ConsoleLogger}
	 */
	#logger;

	/**
	 * Whether the payment button is initialized.
	 *
	 * @type {boolean}
	 */
	#isInitialized = false;

	/**
	 * Whether the one-time initialization of the payment gateway is complete.
	 *
	 * @type {boolean}
	 */
	#gatewayInitialized = false;

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
	 * Keeps track of CSS classes that were added to the wrapper element.
	 * We use this list to remove CSS classes that we've added, e.g. to change shape from
	 * pill to rect in the preview.
	 *
	 * @type {string[]}
	 */
	#appliedClasses = [];

	/**
	 * APM relevant configuration; e.g., configuration of the GooglePay button.
	 */
	#buttonConfig;

	/**
	 * Plugin-wide configuration; i.e., PayPal client ID, shop currency, etc.
	 */
	#ppcpConfig;

	/**
	 * A variation of a context bootstrap handler.
	 */
	#externalHandler;

	/**
	 * A variation of a context handler object, like CheckoutHandler.
	 * This handler provides a standardized interface for certain standardized checks and actions.
	 */
	#contextHandler;

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
	 * Factory method to create a new PaymentButton while limiting a single instance per context.
	 *
	 * @param {string}  context         - Button context name.
	 * @param {unknown} externalHandler - Handler object.
	 * @param {Object}  buttonConfig    - Payment button specific configuration.
	 * @param {Object}  ppcpConfig      - Plugin wide configuration object.
	 * @param {unknown} contextHandler  - Handler object.
	 * @return {PaymentButton} The button instance.
	 */
	static createButton(
		context,
		externalHandler,
		buttonConfig,
		ppcpConfig,
		contextHandler
	) {
		const buttonInstances = getInstances();
		const instanceKey = `${ this.methodId }.${ context }`;

		if ( ! buttonInstances.has( instanceKey ) ) {
			const button = new this(
				context,
				externalHandler,
				buttonConfig,
				ppcpConfig,
				contextHandler
			);

			buttonInstances.set( instanceKey, button );
		}

		return buttonInstances.get( instanceKey );
	}

	/**
	 * Returns a list with all wrapper IDs for the implemented payment method, categorized by
	 * context.
	 *
	 * @abstract
	 * @param {Object} buttonConfig - Payment method specific configuration.
	 * @param {Object} ppcpConfig   - Global plugin configuration.
	 * @return {{MiniCart, Gateway, Block, SmartButton, Default}} The wrapper ID collection.
	 */
	// eslint-disable-next-line no-unused-vars
	static getWrappers( buttonConfig, ppcpConfig ) {
		throw new Error( 'Must be implemented in the child class' );
	}

	/**
	 * Returns a list of all button styles for the implemented payment method, categorized by
	 * context.
	 *
	 * @abstract
	 * @param {Object} buttonConfig - Payment method specific configuration.
	 * @param {Object} ppcpConfig   - Global plugin configuration.
	 * @return {{MiniCart: (*), Default: (*)}} Combined styles, separated by context.
	 */
	// eslint-disable-next-line no-unused-vars
	static getStyles( buttonConfig, ppcpConfig ) {
		throw new Error( 'Must be implemented in the child class' );
	}

	/**
	 * Initialize the payment button instance.
	 *
	 * Do not create new button instances directly; use the `createButton` method instead
	 * to avoid multiple button instances handling the same context.
	 *
	 * @private
	 * @param {string} context         - Button context name.
	 * @param {Object} externalHandler - Handler object.
	 * @param {Object} buttonConfig    - Payment button specific configuration.
	 * @param {Object} ppcpConfig      - Plugin wide configuration object.
	 * @param {Object} contextHandler  - Handler object.
	 */
	constructor(
		context,
		externalHandler = null,
		buttonConfig = {},
		ppcpConfig = {},
		contextHandler = null
	) {
		if ( this.methodId === PaymentButton.methodId ) {
			throw new Error( 'Cannot initialize the PaymentButton base class' );
		}

		if ( ! buttonConfig ) {
			buttonConfig = {};
		}

		const isDebugging = !! buttonConfig?.is_debug;
		const methodName = this.methodId.replace( /^ppcp?-/, '' );

		this.#context = context;
		this.#buttonConfig = buttonConfig;
		this.#ppcpConfig = ppcpConfig;
		this.#externalHandler = externalHandler;
		this.#contextHandler = contextHandler;

		this.#logger = new ConsoleLogger( methodName, context );

		if ( isDebugging ) {
			this.#logger.enabled = true;
			addToDebuggingCollection( methodName, this );
		}

		this.#wrappers = this.constructor.getWrappers(
			this.#buttonConfig,
			this.#ppcpConfig
		);
		this.applyButtonStyles( this.#buttonConfig );

		apmButtonsInit( this.#ppcpConfig );
		this.initEventListeners();
	}

	/**
	 * Internal ID of the payment gateway.
	 *
	 * @readonly
	 * @return {string} The internal gateway ID, defined in the derived class.
	 */
	get methodId() {
		return this.constructor.methodId;
	}

	/**
	 * CSS class that is added to the button wrapper.
	 *
	 * @readonly
	 * @return {string} CSS class, defined in the derived class.
	 */
	get cssClass() {
		return this.constructor.cssClass;
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
	 * Configuration, specific for the implemented payment button.
	 *
	 * @return {Object} Configuration object.
	 */
	get buttonConfig() {
		return this.#buttonConfig;
	}

	/**
	 * Plugin-wide configuration; i.e., PayPal client ID, shop currency, etc.
	 *
	 * @return {Object} Configuration object.
	 */
	get ppcpConfig() {
		return this.#ppcpConfig;
	}

	/**
	 * @return {Object} The bootstrap handler instance, or an empty object.
	 */
	get externalHandler() {
		return this.#externalHandler || {};
	}

	/**
	 * Access the button's context handler.
	 * When no context handler was provided (like for a preview button), an empty object is
	 * returned.
	 *
	 * @return {Object} The context handler instance, or an empty object.
	 */
	get contextHandler() {
		return this.#contextHandler || {};
	}

	/**
	 * Whether customers need to provide shipping details during payment.
	 *
	 * Can be extended by child classes to take method specific configuration into account.
	 *
	 * @return {boolean} True means, shipping fields are displayed and must be filled.
	 */
	get requiresShipping() {
		// Default check: Is shipping enabled in WooCommerce?
		return (
			'function' === typeof this.contextHandler.shippingAllowed &&
			this.contextHandler.shippingAllowed()
		);
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
	 * Whether the button is placed inside a classic gateway context.
	 *
	 * Classic gateway contexts are: Classic checkout, Pay for Order page.
	 *
	 * @return {boolean} True indicates, the button is located inside a classic gateway.
	 */
	get isInsideClassicGateway() {
		return PaymentContext.Gateways.includes( this.context );
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
			this.isInsideClassicGateway
		);
	}

	/**
	 * Whether the currently selected payment gateway is set to the payment method.
	 *
	 * Only relevant on checkout pages where "classic" payment gateways are rendered.
	 *
	 * @return {boolean} True means that this payment method is selected as current gateway.
	 */
	get isCurrentGateway() {
		if ( ! this.isInsideClassicGateway ) {
			// This means, the button's visibility is managed by another script.
			return true;
		}

		/*
		 * We need to rely on `getCurrentPaymentMethod()` here, as the `CheckoutBootstrap.js`
		 * module fires the "ButtonEvents.RENDER" event before any PaymentButton instances are
		 * created. I.e. we cannot observe the initial gateway selection event.
		 */
		const currentMethod = getCurrentPaymentMethod();

		if ( this.isSeparateGateway ) {
			return this.methodId === currentMethod;
		}

		// Button is rendered inside the Smart Buttons block.
		return PaymentMethods.PAYPAL === currentMethod;
	}

	/**
	 * Flags a preview button without actual payment logic.
	 *
	 * @return {boolean} True indicates a preview instance that has no payment logic.
	 */
	get isPreview() {
		return PaymentContext.Preview === this.context;
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
	 * Checks, if the payment button is still attached to the DOM.
	 *
	 * WooCommerce performs some partial reloads in many cases, which can lead to our payment
	 * button
	 * to move into the browser's memory. In that case, we need to recreate the button in the
	 * updated DOM.
	 *
	 * @return {boolean} True means, the button is still present (and typically visible) on the
	 *     page.
	 */
	get isButtonAttached() {
		if ( ! this.#button ) {
			return false;
		}

		let parent = this.#button.parentElement;
		while ( parent?.parentElement ) {
			if ( 'BODY' === parent.tagName ) {
				return true;
			}

			parent = parent.parentElement;
		}

		return false;
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
	 * Open or close a log-group
	 *
	 * @param {?string} [label=null] Group label.
	 */
	logGroup( label = null ) {
		this.#logger.group( label );
	}

	/**
	 * Determines if the current button instance has valid and complete configuration details.
	 * Used during initialization to decide if the button can be initialized or should be skipped.
	 *
	 * Can be implemented by the derived class.
	 *
	 * @param {boolean} [silent=false] - Set to true to suppress console errors.
	 * @return {boolean} True indicates the config is valid and initialization can continue.
	 */
	validateConfiguration( silent = false ) {
		return true;
	}

	applyButtonStyles( buttonConfig, ppcpConfig = null ) {
		if ( ! ppcpConfig ) {
			ppcpConfig = this.ppcpConfig;
		}

		this.#styles = this.constructor.getStyles( buttonConfig, ppcpConfig );

		if ( this.isInitialized ) {
			this.triggerRedraw();
		}
	}

	/**
	 * Configures the button instance. Must be called before the initial `init()`.
	 *
	 * Parameters are defined by the derived class.
	 *
	 * @abstract
	 */
	configure() {}

	/**
	 * Must be named `init()` to simulate "protected" visibility:
	 * Since the derived class also implements a method with the same name, this method can only
	 * be called by the derived class, but not from any other code.
	 */
	init() {
		this.#isInitialized = true;
	}

	/**
	 * Must be named `reinit()` to simulate "protected" visibility:
	 * Since the derived class also implements a method with the same name, this method can only
	 * be called by the derived class, but not from any other code.
	 */
	reinit() {
		this.#isInitialized = false;
		this.#isEligible = false;
	}

	triggerRedraw() {
		this.showPaymentGateway();

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
		if ( this.isInsideClassicGateway ) {
			const parentMethod = this.isSeparateGateway
				? this.methodId
				: PaymentMethods.PAYPAL;

			// Hide the button right after the user selected _any_ gateway.
			observeButtonEvent( {
				event: ButtonEvents.INVALIDATE,
				callback: () => ( this.isVisible = false ),
			} );

			// Show the button (again) when the user selected the current gateway.
			observeButtonEvent( {
				event: ButtonEvents.RENDER,
				paymentMethod: parentMethod,
				callback: () => ( this.isVisible = true ),
			} );
		}
	}

	/**
	 * Refreshes the payment button on the page.
	 */
	refresh() {
		if ( ! this.isPresent ) {
			return;
		}

		this.applyWrapperStyles();

		if ( this.isEligible && this.isCurrentGateway && this.isVisible ) {
			if ( ! this.isButtonAttached ) {
				this.log( 'refresh.addButton' );
				this.addButton();
			}
		}
	}

	/**
	 * Makes the payment gateway visible by removing initial inline styles from the DOM.
	 * Also, removes the button-placeholder container from the smart button block.
	 *
	 * Only relevant on the checkout page, i.e., when `this.isSeparateGateway` is `true`
	 */
	showPaymentGateway() {
		if (
			this.#gatewayInitialized ||
			! this.isSeparateGateway ||
			! this.isEligible
		) {
			return;
		}

		const styleSelector = `style[data-hide-gateway="${ this.methodId }"]`;
		const wrapperSelector = `#${ this.wrappers.Default }`;

		document
			.querySelectorAll( styleSelector )
			.forEach( ( el ) => el.remove() );

		document
			.querySelectorAll( wrapperSelector )
			.forEach( ( el ) => el.remove() );

		this.log( 'Show gateway' );
		this.#gatewayInitialized = true;

		// This code runs only once, during button initialization, and fixes the initial visibility.
		this.isVisible = this.isCurrentGateway;
	}

	/**
	 * Applies CSS classes and inline styling to the payment button wrapper.
	 */
	applyWrapperStyles() {
		const wrapper = this.wrapperElement;
		const { shape, height } = this.style;

		for ( const classItem of this.#appliedClasses ) {
			wrapper.classList.remove( classItem );
		}

		this.#appliedClasses = [];

		const newClasses = [
			`ppcp-button-${ shape }`,
			'ppcp-button-apm',
			this.cssClass,
		];

		wrapper.classList.add( ...newClasses );
		this.#appliedClasses.push( ...newClasses );

		if ( height ) {
			wrapper.style.height = `${ height }px`;
		}

		// Apply the wrapper visibility.
		wrapper.style.display = this.isVisible ? 'block' : 'none';
	}

	/**
	 * Creates a new payment button (HTMLElement) and must call `this.insertButton()` to display
	 * that button in the correct wrapper.
	 *
	 * @abstract
	 */
	addButton() {
		throw new Error( 'Must be implemented by the child class' );
	}

	/**
	 * Prepares the button wrapper element and inserts the provided payment button into the DOM.
	 *
	 * If a payment button was previously inserted to the wrapper, calling this method again will
	 * first remove the previous button.
	 *
	 * @param {HTMLElement} button - The button element to inject.
	 */
	insertButton( button ) {
		if ( ! this.isPresent ) {
			return;
		}

		const wrapper = this.wrapperElement;

		if ( this.#button ) {
			this.removeButton();
		}

		this.log( 'addButton', button );

		this.#button = button;
		wrapper.appendChild( this.#button );
	}

	/**
	 * Removes the payment button from the DOM.
	 */
	removeButton() {
		if ( ! this.isPresent || ! this.#button ) {
			return;
		}

		this.log( 'removeButton' );

		try {
			this.wrapperElement.removeChild( this.#button );
		} catch ( Exception ) {
			// Ignore this.
		}

		this.#button = null;
	}
}
