import { loadCustomScript } from '@paypal/paypal-js';
import widgetBuilder from '../Renderer/WidgetBuilder';
import { debounce } from '../../../../../ppcp-blocks/resources/js/Helper/debounce';
import ConsoleLogger from '../../../../../ppcp-wc-gateway/resources/js/helper/ConsoleLogger';
import DummyPreviewButton from './DummyPreviewButton';

/**
 * Manages all PreviewButton instances of a certain payment method on the page.
 */
class PreviewButtonManager {
	/**
	 * @type {ConsoleLogger}
	 */
	#logger;

	/**
	 * Resolves the promise.
	 * Used by `this.boostrap()` to process enqueued initialization logic.
	 */
	#onInitResolver;

	/**
	 * A deferred Promise that is resolved once the page is ready.
	 * Deferred init logic can be added by using `this.#onInit.then(...)`
	 *
	 * @param {Promise<void>|null}
	 */
	#onInit;

	/**
	 * Initialize the new PreviewButtonManager.
	 *
	 * @param {string} methodName        - Name of the payment method, e.g. "Google Pay"
	 * @param {Object} buttonConfig
	 * @param {Object} defaultAttributes
	 */
	constructor( { methodName, buttonConfig, defaultAttributes } ) {
		// Define the payment method name in the derived class.
		this.methodName = methodName;

		this.buttonConfig = buttonConfig;
		this.defaultAttributes = defaultAttributes;

		this.isEnabled = true;
		this.buttons = {};
		this.apiConfig = null;
		this.apiError = '';

		this.#logger = new ConsoleLogger( this.methodName, 'preview-manager' );
		this.#logger.enabled = true; // Manually set this to true for development.

		this.#onInit = new Promise( ( resolve ) => {
			this.#onInitResolver = resolve;
		} );

		this.bootstrap = this.bootstrap.bind( this );
		this.renderPreview = this.renderPreview.bind( this );

		/**
		 * The "configureAllButtons" method applies ppcpConfig to all buttons that were created
		 * by this PreviewButtonManager instance. We debounce this method, as it should invoke
		 * only once, even if called multiple times in a row.
		 *
		 * This is required, as the `ppcp_paypal_render_preview` event does not fire for all
		 * buttons, but only a single time, passing in a random button's wrapper-ID; however,
		 * that event should always refresh all preview buttons, not only that single button.
		 */
		this._configureAllButtons = debounce(
			this._configureAllButtons.bind( this ),
			100
		);

		this.registerEventListeners();
	}

	/**
	 * Protected method that needs to be implemented by the derived class.
	 * Responsible for fetching and returning the PayPal configuration object for this payment
	 * method.
	 *
	 * @abstract
	 * @param {{}} payPal - The PayPal SDK object provided by WidgetBuilder.
	 * @return {Promise<{}>}
	 */
	// eslint-disable-next-line no-unused-vars
	async fetchConfig( payPal ) {
		throw new Error(
			'The "fetchConfig" method must be implemented by the derived class'
		);
	}

	/**
	 * Protected method that needs to be implemented by the derived class.
	 * This method is responsible for creating a new PreviewButton instance and returning it.
	 *
	 * @abstract
	 * @param {string} wrapperId - CSS ID of the wrapper element.
	 * @return {PreviewButton}
	 */
	// eslint-disable-next-line no-unused-vars
	createButtonInstance( wrapperId ) {
		throw new Error(
			'The "createButtonInstance" method must be implemented by the derived class'
		);
	}

	/**
	 * In case the button SDK could not be loaded from PayPal, we display this dummy button
	 * instead of keeping the preview box empty.
	 *
	 * This dummy is only visible on the admin side, and not rendered on the front-end.
	 *
	 * @param {string} wrapperId
	 * @return {any}
	 */
	createDummyButtonInstance( wrapperId ) {
		return new DummyPreviewButton( {
			selector: wrapperId,
			label: this.apiError,
			methodName: this.methodName,
		} );
	}

	registerEventListeners() {
		jQuery( document ).one( 'DOMContentLoaded', this.bootstrap );

		// General event that all APM buttons react to.
		jQuery( document ).on(
			'ppcp_paypal_render_preview',
			this.renderPreview
		);

		// Specific event to only (re)render the current APM button type.
		jQuery( document ).on(
			`ppcp_paypal_render_preview_${ this.methodName }`,
			this.renderPreview
		);
	}

	/**
	 * Output a debug message to the console, with a module-specific prefix.
	 *
	 * @param {string} message - Log message.
	 * @param {...any} args    - Optional. Additional args to output.
	 */
	log( message, ...args ) {
		this.#logger.log( message, ...args );
	}

	/**
	 * Output an error message to the console, with a module-specific prefix.
	 *
	 * @param {string} message - Log message.
	 * @param {...any} args    - Optional. Additional args to output.
	 */
	error( message, ...args ) {
		this.#logger.error( message, ...args );
	}

	/**
	 * Whether this is a dynamic preview of the APM button.
	 * A dynamic preview adjusts to the current form settings, while a static preview uses the
	 * style settings that were provided from server-side.
	 */
	isDynamic() {
		return !! document.querySelector(
			`[data-ppcp-apm-name="${ this.methodName }"]`
		);
	}

	/**
	 * Load dependencies and bootstrap the module.
	 * Returns a Promise that resolves once all dependencies were loaded and the module can be
	 * used without limitation.
	 *
	 * @return {Promise<void>}
	 */
	async bootstrap() {
		const MAX_WAIT_TIME = 10000; // Fail, if PayPal SDK is unavailable after 10 seconds.
		const RESOLVE_INTERVAL = 200;

		if ( ! this.buttonConfig?.sdk_url || ! widgetBuilder ) {
			this.error( 'Button could not be configured.' );
			return;
		}

		// This is a localization object of "gateway-settings.js". If it's missing, the script was
		// not loaded.
		if ( ! window.PayPalCommerceGatewaySettings ) {
			this.error(
				'PayPal settings are not fully loaded. Please clear the cache and reload the page.'
			);
			return;
		}

		// A helper function that clears the interval and resolves/rejects the promise.
		const resolveOrReject = ( resolve, reject, id, success = true ) => {
			clearInterval( id );
			success
				? resolve()
				: reject( 'Timeout while waiting for widgetBuilder.paypal' );
		};

		// Wait for the PayPal SDK to be ready.
		const paypalPromise = new Promise( ( resolve, reject ) => {
			let elapsedTime = 0;

			const id = setInterval( () => {
				if ( widgetBuilder.paypal ) {
					resolveOrReject( resolve, reject, id );
				} else if ( elapsedTime >= MAX_WAIT_TIME ) {
					resolveOrReject( resolve, reject, id, false );
				}
				elapsedTime += RESOLVE_INTERVAL;
			}, RESOLVE_INTERVAL );
		} );

		// Load the custom SDK script.
		const customScriptPromise = loadCustomScript( {
			url: this.buttonConfig.sdk_url,
		} );

		// Wait for both promises to resolve before continuing.
		await Promise.all( [ customScriptPromise, paypalPromise ] ).catch(
			( err ) => {
				console.log(
					`Failed to load ${ this.methodName } dependencies:`,
					err
				);
			}
		);

		/*
         The fetchConfig method requires two objects to succeed:
         (a) the SDK custom-script
         (b) the `widgetBuilder.paypal` object
         */
		try {
			this.apiConfig = await this.fetchConfig( widgetBuilder.paypal );
		} catch ( error ) {
			this.apiConfig = null;
		}

		// Avoid errors when there was a problem with loading the SDK.
		await this.#onInitResolver();

		this.#onInit = null;
	}

	/**
	 * Event handler, fires on `ppcp_paypal_render_preview`
	 *
	 * @param ev         - Ignored
	 * @param ppcpConfig - The button settings for the preview.
	 */
	renderPreview( ev, ppcpConfig ) {
		const id = ppcpConfig.button.wrapper;

		if ( ! id ) {
			this.error( 'Button did not provide a wrapper ID', ppcpConfig );
			return;
		}

		if ( ! this.shouldInsertPreviewButton( id ) ) {
			this.log( 'Skip preview rendering for this preview-box', id );
			return;
		}

		if ( ! this.buttons[ id ] ) {
			this._addButton( id, ppcpConfig );
		} else {
			this._configureButton( id, ppcpConfig );
		}
	}

	/**
	 * Determines if the preview box supports the current button.
	 *
	 * E.g. "Should the current preview-box display Google Pay buttons?"
	 *
	 * @param {string} previewId - ID of the inner preview box container.
	 * @return {boolean} True if the box is eligible for the preview button, false otherwise.
	 */
	shouldInsertPreviewButton( previewId ) {
		const container = document.querySelector( previewId );
		const box = container.closest( '.ppcp-preview' );
		const limit = box.dataset.ppcpPreviewBlock ?? 'all';

		return 'all' === limit || this.methodName === limit;
	}

	/**
	 * Applies a new configuration to an existing preview button.
	 *
	 * @private
	 * @param id
	 * @param ppcpConfig
	 */
	_configureButton( id, ppcpConfig ) {
		this.log( 'configureButton', id, ppcpConfig );

		this.buttons[ id ]
			.setDynamic( this.isDynamic() )
			.setPpcpConfig( ppcpConfig )
			.render();
	}

	/**
	 * Apples the provided configuration to all existing preview buttons.
	 *
	 * @private
	 * @param ppcpConfig - The new styling to use for the preview buttons.
	 */
	_configureAllButtons( ppcpConfig ) {
		this.log( 'configureAllButtons', ppcpConfig );

		Object.entries( this.buttons ).forEach( ( [ id, button ] ) => {
			const limitWrapper = ppcpConfig.button?.wrapper;

			/**
			 * When the ppcpConfig object specifies a button wrapper, then ensure to limit preview
			 * changes to this individual wrapper. If no button wrapper is defined, the
			 * configuration is relevant for all buttons on the page.
			 */
			if ( limitWrapper && button.wrapper !== limitWrapper ) {
				return;
			}

			this._configureButton( id, {
				...ppcpConfig,
				button: {
					...ppcpConfig.button,

					// The ppcpConfig object might refer to a different wrapper.
					// Fix the selector, to avoid unintentionally hidden preview buttons.
					wrapper: button.wrapper,
				},
			} );
		} );
	}

	/**
	 * Creates a new preview button, that is rendered once the bootstrapping Promise resolves.
	 *
	 * @private
	 * @param id         - The button to add.
	 * @param ppcpConfig - The styling to apply to the preview button.
	 */
	_addButton( id, ppcpConfig ) {
		this.log( 'addButton', id, ppcpConfig );

		const createButton = () => {
			if ( ! this.buttons[ id ] ) {
				this.log( 'createButton.new', id );

				let newInst;

				if ( this.apiConfig && 'object' === typeof this.apiConfig ) {
					newInst = this.createButtonInstance( id );
				} else {
					newInst = this.createDummyButtonInstance( id );
				}
				newInst.setButtonConfig( this.buttonConfig );

				this.buttons[ id ] = newInst;
			}

			this._configureButton( id, ppcpConfig );
		};

		if ( this.#onInit ) {
			this.#onInit.then( createButton );
		} else {
			createButton();
		}
	}

	/**
	 * Refreshes all buttons using the latest buttonConfig.
	 *
	 * @return {this} Reference to self, for chaining.
	 */
	renderButtons() {
		if ( this.isEnabled ) {
			Object.values( this.buttons ).forEach( ( button ) =>
				button.render()
			);
		} else {
			Object.values( this.buttons ).forEach( ( button ) =>
				button.remove()
			);
		}

		return this;
	}

	/**
	 * Enables this payment method, which re-creates or refreshes all buttons.
	 *
	 * @return {this} Reference to self, for chaining.
	 */
	enable() {
		if ( ! this.isEnabled ) {
			this.isEnabled = true;
			this.renderButtons();
		}

		return this;
	}

	/**
	 * Disables this payment method, effectively removing all preview buttons.
	 *
	 * @return {this} Reference to self, for chaining.
	 */
	disable() {
		if ( ! this.isEnabled ) {
			this.isEnabled = false;
			this.renderButtons();
		}

		return this;
	}
}

export default PreviewButtonManager;
