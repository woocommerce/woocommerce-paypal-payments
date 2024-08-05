import ConsoleLogger from '../../../../../ppcp-wc-gateway/resources/js/helper/ConsoleLogger';
import { apmButtonsInit } from '../Helper/ApmButtons';

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
	 * Whether the payment button is initialized.
	 *
	 * @type {boolean}
	 */
	#isInitialized = false;

	/**
	 * The button's context.
	 */
	#context;

	#buttonConfig;

	#ppcpConfig;

	constructor( gatewayName, context, buttonConfig, ppcpConfig ) {
		this.#logger = new ConsoleLogger( gatewayName, context );
		this.#logger.enabled = !! buttonConfig?.is_debug;

		this.#context = context;
		this.#buttonConfig = buttonConfig;
		this.#ppcpConfig = ppcpConfig;

		apmButtonsInit( ppcpConfig );
	}

	/**
	 * Whether the payment button was fully initialized. Read-only.
	 *
	 * @return {boolean} True indicates, that the button was fully initialized.
	 */
	get isInitialized() {
		return this.#isInitialized;
	}

	/**
	 * The button's context. Read-only.
	 *
	 * TODO: Convert the string to a context-object (primitive obsession smell)
	 *
	 * @return {string} The button context.
	 */
	get context() {
		return this.#context;
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
}
