import Fastlane from './Connection/Fastlane';
import { log } from './Helper/Debug';
import DomElementCollection from './Components/DomElementCollection';
import ShippingView from './Views/ShippingView';
import BillingView from './Views/BillingView';
import CardView from './Views/CardView';
import PayPalInsights from './Insights/PayPalInsights';
import {
	disable,
	enable,
} from '../../../ppcp-button/resources/js/modules/Helper/ButtonDisabler';
import { getCurrentPaymentMethod } from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';

/**
 * Internal customer details.
 *
 * @typedef {Object} CustomerDetails
 * @property {null|string} email    - Customer email.
 * @property {null|string} phone    - Fastlane phone number.
 * @property {null|Object} billing  - Billing details object.
 * @property {null|Object} shipping - Shipping details object.
 * @property {null|Object} card     - Payment details object.
 */

class AxoManager {
	axoConfig = null;
	ppcpConfig = null;
	$ = null;

	fastlane = null;
	/**
	 * @type {FastlaneCardComponent}
	 */
	cardComponent = null;

	initialized = false;
	hideGatewaySelection = false;
	phoneNumber = null;

	/**
	 * @type {CustomerDetails}
	 */
	data = {};
	status = {};
	styles = {};
	locale = 'en_us';

	el = null;
	emailInput = null;
	phoneInput = null;
	shippingView = null;
	billingView = null;
	cardView = null;

	constructor( namespace, axoConfig, ppcpConfig ) {
		this.namespace = namespace;
		this.axoConfig = axoConfig;
		this.ppcpConfig = ppcpConfig;

		this.fastlane = new Fastlane( namespace );
		this.$ = jQuery;

		this.status = {
			active: false,
			validEmail: false,
			hasProfile: false,
			useEmailWidget: this.useEmailWidget(),
			hasCard: false,
		};

		this.clearData();

		// TODO - Do we need a public `states` property for this?
		this.states = this.axoConfig.woocommerce.states;

		this.el = new DomElementCollection();

		this.emailInput = document.querySelector(
			this.el.fieldBillingEmail.selector + ' input'
		);

		this.styles = {
			root: {
				backgroundColorPrimary: '#ffffff',
			},
		};

		this.enabledShippingLocations =
			this.axoConfig.enabled_shipping_locations;

		this.registerEventHandlers();

		this.shippingView = new ShippingView(
			this.el.shippingAddressContainer.selector,
			this.el,
			this.states
		);
		this.billingView = new BillingView(
			this.el.billingAddressContainer.selector,
			this.el
		);
		this.cardView = new CardView(
			this.el.paymentContainer.selector + '-details',
			this.el,
			this
		);

		document.axoDebugSetStatus = ( key, value ) => {
			this.setStatus( key, value );
		};

		document.axoDebugObject = () => {
			return this;
		};

		if (
			this.axoConfig?.insights?.enabled &&
			this.axoConfig?.insights?.client_id &&
			this.axoConfig?.insights?.session_id
		) {
			PayPalInsights.config( this.axoConfig?.insights?.client_id, {
				debug: true,
			} );
			PayPalInsights.setSessionId( this.axoConfig?.insights?.session_id );
			PayPalInsights.trackJsLoad();

			if ( document.querySelector( '.woocommerce-checkout' ) ) {
				PayPalInsights.trackBeginCheckout( {
					amount: this.axoConfig?.insights?.amount,
					page_type: 'checkout',
					user_data: {
						country: 'US',
						is_store_member: false,
					},
				} );
			}
		}

		this.onChangePhone = this.onChangePhone.bind( this );
		this.initPhoneSyncWooToFastlane();

		this.triggerGatewayChange();
	}

	/**
	 * Checks if the current flow is the "Ryan flow": Ryan is a known customer who created a
	 * Fastlane profile before. Ryan can leverage all benefits of the accelerated 1-click checkout.
	 *
	 * @return {boolean} True means, Fastlane could link the customer's email to an existing account.
	 */
	get isRyanFlow() {
		return !! this.data.card;
	}

	/**
	 * CSS selector to target the Fastlane Card Component wrapper.
	 *
	 * @return {string} CSS selector.
	 */
	get cardFormSelector() {
		return this.el.paymentContainer.selector + '-form';
	}

	registerEventHandlers() {
		this.$( document ).on(
			'change',
			'input[name=payment_method]',
			( ev ) => {
				const map = {
					'ppcp-axo-gateway': 'card',
					'ppcp-gateway': 'paypal',
				};

				PayPalInsights.trackSelectPaymentMethod( {
					payment_method_selected: map[ ev.target.value ] || 'other',
					page_type: 'checkout',
				} );
			}
		);

		// Listen to Gateway Radio button changes.
		this.el.gatewayRadioButton.on( 'change', ( ev ) => {
			if ( ev.target.checked ) {
				this.activateAxo();
			} else {
				this.deactivateAxo();
			}
		} );

		this.$( document ).on(
			'updated_checkout payment_method_selected',
			() => {
				this.triggerGatewayChange();
			}
		);

		// On checkout form submitted.
		this.el.submitButton.on( 'click', () => {
			this.onClickSubmitButton();
			return false;
		} );

		// Click change shipping address link.
		this.el.changeShippingAddressLink.on( 'click', async () => {
			if ( this.status.hasProfile ) {
				const { selectionChanged, selectedAddress } =
					await this.fastlane.profile.showShippingAddressSelector();

				if ( selectionChanged ) {
					this.setShipping( selectedAddress );
					this.shippingView.refresh();
				}
			}
		} );

		// Click change billing address link.
		this.el.changeBillingAddressLink.on( 'click', async () => {
			if ( this.status.hasProfile ) {
				this.el.changeCardLink.trigger( 'click' );
			}
		} );

		// Click change card link.
		this.el.changeCardLink.on( 'click', async () => {
			const response = await this.fastlane.profile.showCardSelector();

			if ( response.selectionChanged ) {
				this.setCard( response.selectedCard );
				this.setBilling( {
					address:
						response.selectedCard.paymentSource.card.billingAddress,
				} );
			}
		} );

		// Cancel "continuation" mode.
		this.el.showGatewaySelectionLink.on( 'click', async () => {
			this.hideGatewaySelection = false;
			this.$( '.wc_payment_methods label' ).show();
			this.$( '.wc_payment_methods input' ).show();
			this.cardView.refresh();
		} );

		// Prevents sending checkout form when pressing Enter key on input field
		// and triggers customer lookup
		this.$( 'form.woocommerce-checkout input' ).on(
			'keydown',
			async ( ev ) => {
				if (
					ev.key === 'Enter' &&
					getCurrentPaymentMethod() === 'ppcp-axo-gateway'
				) {
					ev.preventDefault();
					log(
						`Enter key attempt - emailInput: ${ this.emailInput.value }`
					);
					log(
						`this.lastEmailCheckedIdentity: ${ this.lastEmailCheckedIdentity }`
					);
					this.validateEmail( this.el.fieldBillingEmail.selector );
					if (
						this.emailInput &&
						this.lastEmailCheckedIdentity !== this.emailInput.value
					) {
						await this.onChangeEmail();
					}
				}
			}
		);

		this.reEnableEmailInput();

		// Clear last email checked identity when email field is focused.
		this.$( '#billing_email_field input' ).on( 'focus', ( ev ) => {
			log(
				`Clear the last email checked: ${ this.lastEmailCheckedIdentity }`
			);
			this.lastEmailCheckedIdentity = '';
		} );

		// Listening to status update event
		document.addEventListener( 'axo_status_updated', ( ev ) => {
			const termsField = document.querySelector( "[name='terms-field']" );
			if ( termsField ) {
				const status = ev.detail;
				const shouldHide =
					status.active &&
					status.validEmail === false &&
					status.hasProfile === false;

				termsField.parentElement.style.display = shouldHide
					? 'none'
					: 'block';
			}
		} );
	}

	rerender() {
		/**
		 * active              | 0 1 1 1
		 * validEmail          | * 0 1 1
		 * hasProfile          | * * 0 1
		 * --------------------------------
		 * defaultSubmitButton | 1 0 0 0
		 * defaultEmailField   | 1 0 0 0
		 * defaultFormFields   | 1 0 1 0
		 * extraFormFields     | 0 0 0 1
		 * axoEmailField       | 0 1 0 0
		 * axoProfileViews     | 0 0 0 1
		 * axoPaymentContainer | 0 0 1 1
		 * axoSubmitButton     | 0 0 1 1
		 */
		const scenario = this.identifyScenario(
			this.status.active,
			this.status.validEmail,
			this.status.hasProfile
		);

		log( `Scenario: ${ JSON.stringify( scenario ) }` );

		// Reset some elements to a default status.
		this.el.watermarkContainer.hide();

		if ( scenario.defaultSubmitButton ) {
			this.el.defaultSubmitButton.show();
			this.el.billingEmailSubmitButton.hide();
		} else {
			this.el.defaultSubmitButton.hide();
			this.el.billingEmailSubmitButton.show();
		}

		if ( scenario.defaultEmailField ) {
			this.el.fieldBillingEmail.show();
		} else {
			this.el.fieldBillingEmail.hide();
		}

		if ( scenario.defaultFormFields ) {
			this.el.customerDetails.show();
			this.toggleLoaderAndOverlay(
				this.el.customerDetails,
				'loader',
				'ppcp-axo-overlay'
			);
		} else {
			this.el.customerDetails.hide();
		}

		if ( scenario.extraFormFields ) {
			this.el.customerDetails.show();
			// Hiding of unwanted will be handled by the axoProfileViews handler.
		}

		if ( scenario.axoEmailField ) {
			this.showAxoEmailField();
			this.el.watermarkContainer.show();

			// Move watermark to after email.
			document
				.querySelector(
					'#billing_email_field .woocommerce-input-wrapper'
				)
				.append(
					document.querySelector(
						this.el.watermarkContainer.selector
					)
				);
		} else {
			this.el.emailWidgetContainer.hide();
			if ( ! scenario.defaultEmailField ) {
				this.el.fieldBillingEmail.hide();
			}
		}

		if ( scenario.axoProfileViews ) {
			this.shippingView.activate();
			this.cardView.activate();

			if ( this.status.hasCard ) {
				this.billingView.activate();
			}

			// Move watermark to after shipping.
			this.$( this.el.shippingAddressContainer.selector ).after(
				this.$( this.el.watermarkContainer.selector )
			);

			this.el.watermarkContainer.show();

			// Add class to customer details container.
			this.$( this.el.axoCustomerDetails.selector ).addClass( 'col-1' );
		} else {
			this.shippingView.deactivate();
			this.billingView.deactivate();
			this.cardView.deactivate();
			this.$( this.el.axoCustomerDetails.selector ).removeClass(
				'col-1'
			);
		}

		if ( scenario.axoPaymentContainer ) {
			this.el.paymentContainer.show();
			this.el.gatewayDescription.hide();
			document
				.querySelector( this.el.billingEmailSubmitButton.selector )
				.setAttribute( 'disabled', 'disabled' );
		} else {
			this.el.paymentContainer.hide();
		}

		if ( scenario.axoSubmitButton ) {
			this.el.submitButtonContainer.show();
		} else {
			this.el.submitButtonContainer.hide();
		}

		this.ensureBillingFieldsConsistency();
		this.ensureShippingFieldsConsistency();
	}

	identifyScenario( active, validEmail, hasProfile ) {
		const response = {
			defaultSubmitButton: false,
			defaultEmailField: false,
			defaultFormFields: false,
			extraFormFields: false,
			axoEmailField: false,
			axoProfileViews: false,
			axoPaymentContainer: false,
			axoSubmitButton: false,
		};

		if ( active && validEmail && hasProfile ) {
			response.extraFormFields = true;
			response.axoProfileViews = true;
			response.axoPaymentContainer = true;
			response.axoSubmitButton = true;
			return response;
		}
		if ( active && validEmail && ! hasProfile ) {
			response.defaultFormFields = true;
			response.axoEmailField = true;
			response.axoPaymentContainer = true;
			response.axoSubmitButton = true;
			return response;
		}
		if ( active && ! validEmail ) {
			response.axoEmailField = true;
			return response;
		}
		if ( ! active ) {
			response.defaultSubmitButton = true;
			response.defaultEmailField = true;
			response.defaultFormFields = true;
			return response;
		}
		throw new Error( 'Invalid scenario.' );
	}

	ensureBillingFieldsConsistency() {
		const $billingFields = this.$(
			'.woocommerce-billing-fields .form-row:visible'
		);
		const $billingHeaders = this.$( '.woocommerce-billing-fields h3' );
		if ( this.billingView.isActive() ) {
			if ( $billingFields.length ) {
				$billingHeaders.show();
			} else {
				$billingHeaders.hide();
			}
		} else {
			$billingHeaders.show();
		}
	}

	ensureShippingFieldsConsistency() {
		const $shippingFields = this.$(
			'.woocommerce-shipping-fields .form-row:visible'
		);
		const $shippingHeaders = this.$( '.woocommerce-shipping-fields h3' );
		if ( this.shippingView.isActive() ) {
			if ( $shippingFields.length ) {
				$shippingHeaders.show();
			} else {
				$shippingHeaders.hide();
			}
		} else {
			$shippingHeaders.show();
		}
	}

	showAxoEmailField() {
		if ( this.status.useEmailWidget ) {
			this.el.emailWidgetContainer.show();
			this.el.fieldBillingEmail.hide();
		} else {
			this.el.emailWidgetContainer.hide();
			this.el.fieldBillingEmail.show();
		}
	}

	setStatus( key, value ) {
		this.status[ key ] = value;

		log( `Status updated: ${ JSON.stringify( this.status ) }` );

		document.dispatchEvent(
			new CustomEvent( 'axo_status_updated', { detail: this.status } )
		);

		this.rerender();
	}

	activateAxo() {
		this.initPlacements();
		this.initFastlane();
		this.setStatus( 'active', true );
		this.readPhoneFromWoo();

		log( `Attempt on activation - emailInput: ${ this.emailInput.value }` );
		log(
			`this.lastEmailCheckedIdentity: ${ this.lastEmailCheckedIdentity }`
		);
		if (
			this.emailInput &&
			this.lastEmailCheckedIdentity !== this.emailInput.value
		) {
			this.onChangeEmail();
		} else {
			this.refreshFastlanePrefills();
		}
	}

	deactivateAxo() {
		this.setStatus( 'active', false );
	}

	initPlacements() {
		const wrapper = this.el.axoCustomerDetails;

		// Customer details container.
		if ( ! document.querySelector( wrapper.selector ) ) {
			document.querySelector( wrapper.anchorSelector ).insertAdjacentHTML(
				'afterbegin',
				`
                <div id="${ wrapper.id }" class="${ wrapper.className }"></div>
            `
			);
		}

		const wrapperElement = document.querySelector( wrapper.selector );

		// Billing view container.
		const bc = this.el.billingAddressContainer;
		if ( ! document.querySelector( bc.selector ) ) {
			wrapperElement.insertAdjacentHTML(
				'beforeend',
				`
                <div id="${ bc.id }" class="${ bc.className }"></div>
            `
			);
		}

		// Shipping view container.
		const sc = this.el.shippingAddressContainer;
		if ( ! document.querySelector( sc.selector ) ) {
			wrapperElement.insertAdjacentHTML(
				'beforeend',
				`
                <div id="${ sc.id }" class="${ sc.className }"></div>
            `
			);
		}

		// billingEmailFieldWrapper
		const befw = this.el.billingEmailFieldWrapper;
		if ( ! document.querySelector( befw.selector ) ) {
			document
				.querySelector(
					'#billing_email_field .woocommerce-input-wrapper'
				)
				.insertAdjacentHTML(
					'afterend',
					`
                <div id="${ befw.id }"></div>
            `
				);
		}

		// Watermark container
		const wc = this.el.watermarkContainer;
		if ( ! document.querySelector( wc.selector ) ) {
			document.querySelector( befw.selector ).insertAdjacentHTML(
				'beforeend',
				`
                <div class="${ wc.className }" id="${ wc.id }"></div>
            `
			);
		}

		// Payment container
		const pc = this.el.paymentContainer;
		if ( ! document.querySelector( pc.selector ) ) {
			const gatewayPaymentContainer = document.querySelector(
				'.payment_method_ppcp-axo-gateway'
			);
			gatewayPaymentContainer.insertAdjacentHTML(
				'beforeend',
				`
                <div id="${ pc.id }" class="${ pc.className } axo-hidden">
                    <div id="${ pc.id }-form" class="${ pc.className }-form"></div>
                    <div id="${ pc.id }-details" class="${ pc.className }-details"></div>
                </div>
            `
			);
		}

		if ( this.useEmailWidget() ) {
			// Display email widget.
			const ec = this.el.emailWidgetContainer;
			if ( ! document.querySelector( ec.selector ) ) {
				wrapperElement.insertAdjacentHTML(
					'afterbegin',
					`
                    <div id="${ ec.id }" class="${ ec.className }">
                    --- EMAIL WIDGET PLACEHOLDER ---
                    </div>
                `
				);
			}
		} else {
			// Move email to the AXO container.
			const emailRow = document.querySelector(
				this.el.fieldBillingEmail.selector
			);
			wrapperElement.prepend( emailRow );
			document
				.querySelector( this.el.billingEmailFieldWrapper.selector )
				.prepend(
					document.querySelector(
						'#billing_email_field .woocommerce-input-wrapper'
					)
				);
		}
	}

	async initFastlane() {
		if ( this.initialized ) {
			return;
		}
		this.initialized = true;

		await this.connect();
		await this.renderWatermark();
		this.renderEmailSubmitButton();
		this.watchEmail();
	}

	async connect() {
		if ( this.axoConfig.environment.is_sandbox ) {
			window.localStorage.setItem( 'axoEnv', 'sandbox' );
		}

		await this.fastlane.connect( {
			locale: this.locale,
			styles: this.styles,
			shippingAddressOptions: {
				allowedLocations: this.enabledShippingLocations,
			},
		} );

		this.fastlane.setLocale( 'en_us' );
	}

	triggerGatewayChange() {
		this.el.gatewayRadioButton.trigger( 'change' );
	}

	async renderWatermark( includeAdditionalInfo = true ) {
		(
			await this.fastlane.FastlaneWatermarkComponent( {
				includeAdditionalInfo,
			} )
		).render( this.el.watermarkContainer.selector );

		this.toggleWatermarkLoading(
			this.el.watermarkContainer,
			'ppcp-axo-watermark-loading',
			'loader'
		);
	}

	renderEmailSubmitButton() {
		const billingEmailSubmitButton = this.el.billingEmailSubmitButton;
		const billingEmailSubmitButtonSpinner =
			this.el.billingEmailSubmitButtonSpinner;

		if ( ! document.querySelector( billingEmailSubmitButton.selector ) ) {
			const wrapper = document.querySelector(
				'#billing_email_field .woocommerce-input-wrapper'
			);
			const watermarkContainer = document.querySelector(
				'#ppcp-axo-watermark-container'
			);

			wrapper.insertAdjacentHTML(
				'beforeend',
				`
            <button type="button" id="${ billingEmailSubmitButton.id }" class="${ billingEmailSubmitButton.className }">
                ${ this.axoConfig.billing_email_button_text }
                <span id="${ billingEmailSubmitButtonSpinner.id }"></span>
            </button>
            `
			);

			const buttonElement = document.querySelector(
				billingEmailSubmitButton.selector
			);

			// Reorder button to ensure it's before the watermark container
			wrapper.insertBefore( buttonElement, watermarkContainer );

			buttonElement.offsetHeight;
			buttonElement.classList.remove(
				'ppcp-axo-billing-email-submit-button-hidden'
			);
			buttonElement.classList.add(
				'ppcp-axo-billing-email-submit-button-loaded'
			);
		}
	}

	watchEmail() {
		if ( this.useEmailWidget() ) {
			// TODO
		} else {
			this.emailInput.addEventListener( 'change', async () => {
				log(
					`Change event attempt - emailInput: ${ this.emailInput.value }`
				);
				log(
					`this.lastEmailCheckedIdentity: ${ this.lastEmailCheckedIdentity }`
				);
				if (
					this.emailInput &&
					this.lastEmailCheckedIdentity !== this.emailInput.value
				) {
					this.validateEmail( this.el.fieldBillingEmail.selector );
					this.onChangeEmail();
				}
			} );

			log(
				`Last, this.emailInput.value attempt - emailInput: ${ this.emailInput.value }`
			);
			log(
				`this.lastEmailCheckedIdentity: ${ this.lastEmailCheckedIdentity }`
			);
			if ( this.emailInput.value ) {
				this.onChangeEmail();
			}
		}
	}

	/**
	 * Locates the WooCommerce checkout "billing phone" field and adds event listeners to it.
	 */
	initPhoneSyncWooToFastlane() {
		this.phoneInput = document.querySelector( '#billing_phone' );
		this.phoneInput?.addEventListener( 'change', this.onChangePhone );
	}

	/**
	 * Strips the country prefix and non-numeric characters from the phone number. If the remaining
	 * phone number is valid, it's returned. Otherwise, the function returns null.
	 *
	 * @param {string} number - Phone number to sanitize.
	 * @return {string|null} A valid US phone number, or null if the number is invalid.
	 */
	sanitizePhoneNumber( number ) {
		const localNumber = number.replace( /^\+1/, '' );
		const cleanNumber = localNumber.replace( /\D/g, '' );

		// All valid US mobile numbers have exactly 10 digits.
		return cleanNumber.length === 10 ? cleanNumber : null;
	}

	/**
	 * Reads the phone number from the WooCommerce checkout form, sanitizes it, and (if valid)
	 * stores it in the internal customer details object.
	 *
	 * @return {boolean} True, if the internal phone number was updated.
	 */
	readPhoneFromWoo() {
		if ( ! this.phoneInput ) {
			return false;
		}

		const phoneNumber = this.phoneInput.value;
		const validPhoneNumber = this.sanitizePhoneNumber( phoneNumber );

		if ( ! validPhoneNumber ) {
			return false;
		}

		this.data.phone = validPhoneNumber;
		return true;
	}

	async onChangeEmail() {
		if ( ! this.status.active ) {
			log( 'Email checking skipped, AXO not active.' );
			return;
		}

		if ( ! this.emailInput ) {
			log( 'Email field not initialized.' );
			return;
		}

		if ( this.data.email === this.emailInput.value ) {
			log( 'Email has not changed since last validation.' );
			return;
		}

		log(
			`Email changed: ${
				this.emailInput ? this.emailInput.value : '<empty>'
			}`
		);
		this.clearData();

		this.emailInput.value = this.stripSpaces( this.emailInput.value );

		this.$( this.el.paymentContainer.selector + '-details' ).html( '' );
		this.removeFastlaneComponent();

		this.setStatus( 'validEmail', false );
		this.setStatus( 'hasProfile', false );

		this.hideGatewaySelection = false;

		this.lastEmailCheckedIdentity = this.emailInput.value;

		if (
			! this.emailInput.value ||
			! this.emailInput.checkValidity() ||
			! this.validateEmailFormat( this.emailInput.value )
		) {
			log( 'The email address is not valid.' );
			return;
		}

		this.data.email = this.emailInput.value;
		this.billingView.setData( this.data );

		this.readPhoneFromWoo();

		if ( ! this.fastlane.identity ) {
			log( 'Not initialized.' );
			return;
		}

		PayPalInsights.trackSubmitCheckoutEmail( {
			page_type: 'checkout',
		} );

		this.disableGatewaySelection();
		this.spinnerToggleLoaderAndOverlay(
			this.el.billingEmailSubmitButtonSpinner,
			'loader',
			'ppcp-axo-overlay'
		);
		await this.lookupCustomerByEmail();
		this.spinnerToggleLoaderAndOverlay(
			this.el.billingEmailSubmitButtonSpinner,
			'loader',
			'ppcp-axo-overlay'
		);
		this.enableGatewaySelection();
	}

	/**
	 * Event handler that fires when the customer changed the phone number in the WooCommerce
	 * checkout form. If Fastlane is active, the component is refreshed.
	 *
	 * @return {Promise<void>}
	 */
	async onChangePhone() {
		const hasChanged = this.readPhoneFromWoo();

		if ( hasChanged && this.status.active ) {
			await this.refreshFastlanePrefills();
		}

		return Promise.resolve();
	}

	async lookupCustomerByEmail() {
		const lookupResponse =
			await this.fastlane.identity.lookupCustomerByEmail(
				this.emailInput.value
			);

		log( `lookupCustomerByEmail: ${ JSON.stringify( lookupResponse ) }` );

		if ( lookupResponse.customerContextId ) {
			// Email is associated with a Connect profile or a PayPal member.
			// Authenticate the customer to get access to their profile.
			log(
				'Email is associated with a Connect profile or a PayPal member'
			);

			const authResponse =
				await this.fastlane.identity.triggerAuthenticationFlow(
					lookupResponse.customerContextId
				);

			log(
				`AuthResponse - triggerAuthenticationFlow: ${ JSON.stringify(
					authResponse
				) }`
			);

			if ( authResponse.authenticationState === 'succeeded' ) {
				const shippingData = authResponse.profileData.shippingAddress;
				if ( shippingData ) {
					this.setShipping( shippingData );
				}

				if ( authResponse.profileData.card ) {
					this.setStatus( 'hasCard', true );
				} else {
					await this.initializeFastlaneComponent();
				}

				const cardBillingAddress =
					authResponse.profileData?.card?.paymentSource?.card
						?.billingAddress;
				if ( cardBillingAddress ) {
					this.setCard( authResponse.profileData.card );

					const billingData = {
						address: cardBillingAddress,
					};
					const phoneNumber =
						authResponse.profileData?.shippingAddress?.phoneNumber
							?.nationalNumber ?? '';
					if ( phoneNumber ) {
						billingData.phoneNumber = phoneNumber;
					}

					this.setBilling( billingData );
				}

				this.setStatus( 'validEmail', true );
				this.setStatus( 'hasProfile', true );

				this.hideGatewaySelection = true;
				this.$( '.wc_payment_methods label' ).hide();
				this.$( '.wc_payment_methods input' ).hide();

				await this.renderWatermark( false );

				this.rerender();
			} else {
				// authentication failed or canceled by the customer
				// set status as guest customer
				log( 'Authentication Failed' );

				this.setStatus( 'validEmail', true );
				this.setStatus( 'hasProfile', false );

				await this.renderWatermark( true );
				await this.initializeFastlaneComponent();
			}
		} else {
			// No profile found with this email address.
			// This is a guest customer.
			log( 'No profile found with this email address.' );

			this.setStatus( 'validEmail', true );
			this.setStatus( 'hasProfile', false );

			await this.renderWatermark( true );
			await this.initializeFastlaneComponent();
		}
	}

	disableGatewaySelection() {
		this.$( '.wc_payment_methods input' ).prop( 'disabled', true );
	}

	enableGatewaySelection() {
		this.$( '.wc_payment_methods input' ).prop( 'disabled', false );
	}

	clearData() {
		this.data = {
			email: null,
			phone: null,
			billing: null,
			shipping: null,
			card: null,
		};
	}

	setShipping( shipping ) {
		this.data.shipping = shipping;
		this.shippingView.setData( this.data );
	}

	setBilling( billing ) {
		this.data.billing = billing;
		this.billingView.setData( this.data );
	}

	setCard( card ) {
		this.data.card = card;
		this.cardView.setData( this.data );
	}

	onClickSubmitButton() {
		// TODO: validate data.

		if ( this.isRyanFlow ) {
			// Ryan flow
			log( 'Starting Ryan flow.' );

			this.$( '#ship-to-different-address-checkbox' ).prop(
				'checked',
				'checked'
			);

			const data = {};
			this.billingView.toSubmitData( data );
			this.shippingView.toSubmitData( data );
			this.cardView.toSubmitData( data );

			this.ensureBillingPhoneNumber( data );

			log( `Ryan flow - submitted nonce: ${ this.data.card.id }` );

			this.submit( this.data.card.id, data );
		} else {
			// Gary flow
			log( 'Starting Gary flow.' );

			try {
				this.cardComponent
					.getPaymentToken( this.tokenizeData() )
					.then( ( response ) => {
						log( `Gary flow - submitted nonce: ${ response.id }` );
						this.submit( response.id );
					} );
			} catch ( e ) {
				alert( 'Error tokenizing data.' );
				log( `Error tokenizing data. ${ e.message }`, 'error' );
			}
		}
	}

	cardComponentData() {
		const config = {
			fields: {
				cardholderName: {
					enabled: this.axoConfig.name_on_card === '1',
				},
			},
			styles: this.deleteKeysWithEmptyString(
				this.axoConfig.style_options
			),
		};

		// Ryan is a known customer, we do not need his phone number.
		if ( this.data.phone && ! this.isRyanFlow ) {
			config.fields.phoneNumber = {
				prefill: this.data.phone,
			};
		}

		return config;
	}

	/**
	 * Initializes the Fastlane UI component, using configuration provided by the
	 * `cardComponentData()` method. If the UI component was already initialized, nothing happens.
	 *
	 * @return {Promise<*>} Resolves when the component was rendered.
	 */
	async initializeFastlaneComponent() {
		if ( ! this.status.active || this.cardComponent ) {
			return Promise.resolve();
		}

		const elem = this.cardFormSelector;
		const config = this.cardComponentData();

		this.cardComponent =
			await this.fastlane.FastlaneCardComponent( config );

		return this.cardComponent.render( elem );
	}

	/**
	 * Reverts the changes made by `initializeFastlaneComponent()`.
	 *
	 * Calling this method will lose any input that the user made inside the
	 * Fastlane Card Component.
	 */
	removeFastlaneComponent() {
		document.querySelector( this.cardFormSelector ).innerHTML = '';

		this.cardComponent = null;
	}

	/**
	 * Updates the prefill-values in the UI component. This method only updates empty fields.
	 *
	 * @return {Promise<*>} Resolves when the component was refreshed.
	 */
	async refreshFastlanePrefills() {
		if ( ! this.cardComponent ) {
			return Promise.resolve();
		}

		const { fields } = this.cardComponentData();
		const prefills = Object.keys( fields ).reduce( ( result, key ) => {
			if ( fields[ key ].hasOwnProperty( 'prefill' ) ) {
				result[ key ] = fields[ key ].prefill;
			}
			return result;
		}, {} );

		return this.cardComponent.updatePrefills( prefills );
	}

	tokenizeData() {
		return {
			cardholderName: {
				fullName: this.billingView.fullName(),
			},
			billingAddress: {
				addressLine1: this.billingView.inputValue( 'street1' ),
				addressLine2: this.billingView.inputValue( 'street2' ),
				adminArea1: this.billingView.inputValue( 'stateCode' ),
				adminArea2: this.billingView.inputValue( 'city' ),
				postalCode: this.billingView.inputValue( 'postCode' ),
				countryCode: this.billingView.inputValue( 'countryCode' ),
			},
		};
	}

	submit( nonce, data ) {
		// Send the nonce and previously captured device data to server to complete checkout
		if ( ! this.el.axoNonceInput.get() ) {
			this.$( 'form.woocommerce-checkout' ).append(
				`<input type="hidden" id="${ this.el.axoNonceInput.id }" name="axo_nonce" value="" />`
			);
		}

		this.el.axoNonceInput.get().value = nonce;

		PayPalInsights.trackEndCheckout( {
			amount: this.axoConfig?.insights?.amount,
			page_type: 'checkout',
			payment_method_selected: 'card',
			user_data: {
				country: 'US',
				is_store_member: false,
			},
		} );

		if ( data ) {
			// Ryan flow.
			const form = document.querySelector( 'form.woocommerce-checkout' );
			const formData = new FormData( form );

			this.showLoading();

			// Fill in form data.
			Object.keys( data ).forEach( ( key ) => {
				formData.set( key, data[ key ] );
			} );

			// Set type of user (Ryan) to be received on WC gateway process payment request.
			formData.set( 'fastlane_member', true );

			fetch( wc_checkout_params.checkout_url, {
				// TODO: maybe create a new endpoint to process_payment.
				method: 'POST',
				body: formData,
			} )
				.then( ( response ) => response.json() )
				.then( ( responseData ) => {
					if ( responseData.result === 'failure' ) {
						if ( responseData.messages ) {
							const $notices = this.$(
								'.woocommerce-notices-wrapper'
							).eq( 0 );
							$notices.html( responseData.messages );
							this.$( 'html, body' ).animate(
								{
									scrollTop: $notices.offset().top,
								},
								500
							);
						}

						log(
							`Error sending checkout form. ${ responseData }`,
							'error'
						);

						this.hideLoading();
						return;
					}
					if ( responseData.redirect ) {
						window.location.href = responseData.redirect;
					}
				} )
				.catch( ( error ) => {
					log(
						`Error sending checkout form. ${ error.message }`,
						'error'
					);

					this.hideLoading();
				} );
		} else {
			// Gary flow.
			this.el.defaultSubmitButton.click();
		}
	}

	showLoading() {
		const submitContainerSelector = '.woocommerce-checkout-payment';
		jQuery( 'form.woocommerce-checkout' ).append(
			'<div class="blockUI blockOverlay" style="z-index: 1000; border: medium; margin: 0px; padding: 0px; width: 100%; height: 100%; top: 0px; left: 0px; background: rgb(255, 255, 255); opacity: 0.6; cursor: default; position: absolute;"></div>'
		);
		disable( submitContainerSelector );
	}

	hideLoading() {
		const submitContainerSelector = '.woocommerce-checkout-payment';
		jQuery( 'form.woocommerce-checkout .blockOverlay' ).remove();
		enable( submitContainerSelector );
	}

	useEmailWidget() {
		return this.axoConfig?.widgets?.email === 'use_widget';
	}

	deleteKeysWithEmptyString = ( obj ) => {
		for ( const key of Object.keys( obj ) ) {
			if ( obj[ key ] === '' ) {
				delete obj[ key ];
			} else if ( typeof obj[ key ] === 'object' ) {
				obj[ key ] = this.deleteKeysWithEmptyString( obj[ key ] );
				if ( Object.keys( obj[ key ] ).length === 0 ) {
					delete obj[ key ];
				}
			}
		}

		return Array.isArray( obj ) ? obj.filter( ( val ) => val ) : obj;
	};

	ensureBillingPhoneNumber( data ) {
		if ( data.billing_phone === '' ) {
			let phone = '';
			const cc = this.data.shipping?.phoneNumber?.countryCode;
			const number = this.data.shipping?.phoneNumber?.nationalNumber;

			if ( cc ) {
				phone = `+${ cc } `;
			}
			phone += number;

			data.billing_phone = phone;
		}
	}

	toggleLoaderAndOverlay( element, loaderClass, overlayClass ) {
		const loader = document.querySelector(
			`${ element.selector } .${ loaderClass }`
		);
		const overlay = document.querySelector(
			`${ element.selector } .${ overlayClass }`
		);
		if ( loader ) {
			loader.classList.toggle( loaderClass );
		}
		if ( overlay ) {
			overlay.classList.toggle( overlayClass );
		}
	}

	spinnerToggleLoaderAndOverlay( element, loaderClass, overlayClass ) {
		const spinner = document.querySelector( `${ element.selector }` );
		if ( spinner ) {
			spinner.classList.toggle( loaderClass );
			spinner.classList.toggle( overlayClass );
		}
	}

	toggleWatermarkLoading( container, loadingClass, loaderClass ) {
		const watermarkLoading = document.querySelector(
			`${ container.selector }.${ loadingClass }`
		);
		const watermarkLoader = document.querySelector(
			`${ container.selector }.${ loaderClass }`
		);
		if ( watermarkLoading ) {
			watermarkLoading.classList.toggle( loadingClass );
		}
		if ( watermarkLoader ) {
			watermarkLoader.classList.toggle( loaderClass );
		}
	}

	validateEmailFormat( value ) {
		const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return emailPattern.test( value );
	}

	stripSpaces( str ) {
		return str.replace( /\s+/g, '' );
	}

	validateEmail( billingEmail ) {
		const billingEmailSelector = document.querySelector( billingEmail );
		const value = document.querySelector( billingEmail + ' input' ).value;

		if ( this.validateEmailFormat( value ) ) {
			billingEmailSelector.classList.remove( 'woocommerce-invalid' );
			billingEmailSelector.classList.add( 'woocommerce-validated' );
			this.setStatus( 'validEmail', true );
		} else {
			billingEmailSelector.classList.remove( 'woocommerce-validated' );
			billingEmailSelector.classList.add( 'woocommerce-invalid' );
			this.setStatus( 'validEmail', false );
		}
	}

	reEnableEmailInput() {
		const reEnableInput = ( ev ) => {
			const submitButton = document.querySelector(
				this.el.billingEmailSubmitButton.selector
			);
			if ( submitButton.hasAttribute( 'disabled' ) ) {
				submitButton.removeAttribute( 'disabled' );
			}
		};

		this.$( '#billing_email_field input' ).on( 'focus', reEnableInput );
		this.$( '#billing_email_field input' ).on( 'input', reEnableInput );
		this.$( '#billing_email_field input' ).on( 'click', reEnableInput );
	}
}

export default AxoManager;
