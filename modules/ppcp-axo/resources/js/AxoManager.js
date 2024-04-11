import Fastlane from "./Connection/Fastlane";
import {log} from "./Helper/Debug";
import DomElementCollection from "./Components/DomElementCollection";
import ShippingView from "./Views/ShippingView";
import BillingView from "./Views/BillingView";
import CardView from "./Views/CardView";

class AxoManager {

    constructor(axoConfig, ppcpConfig) {
        this.axoConfig = axoConfig;
        this.ppcpConfig = ppcpConfig;

        this.initialized = false;
        this.fastlane = new Fastlane();
        this.$ = jQuery;

        this.hideGatewaySelection = false;

        this.status = {
            active: false,
            validEmail: false,
            hasProfile: false,
            useEmailWidget: this.useEmailWidget()
        };

        this.data = {
            email: null,
            billing: null,
            shipping: null,
            card: null,
        };

        this.el = new DomElementCollection();

        this.styles = {
            root: {
                backgroundColorPrimary: '#ffffff'
            }
        };

        this.locale = 'en_us';

        this.registerEventHandlers();

        this.shippingView = new ShippingView(this.el.shippingAddressContainer.selector, this.el);
        this.billingView = new BillingView(this.el.billingAddressContainer.selector, this.el);
        this.cardView = new CardView(this.el.paymentContainer.selector + '-details', this.el, this);

        document.axoDebugSetStatus = (key, value) => {
            this.setStatus(key, value);
        }

        document.axoDebugObject = () => {
            console.log(this);
            return this;
        }
    }

    registerEventHandlers() {

        // Listen to Gateway Radio button changes.
        this.el.gatewayRadioButton.on('change', (ev) => {
            if (ev.target.checked) {
                this.activateAxo();
            } else {
                this.deactivateAxo();
            }
        });

        this.$(document).on('updated_checkout payment_method_selected', () => {
            this.triggerGatewayChange();
        });

        // On checkout form submitted.
        this.el.submitButton.on('click', () => {
            this.onClickSubmitButton();
            return false;
        })

        // Click change shipping address link.
        this.el.changeShippingAddressLink.on('click', async () => {
            if (this.status.hasProfile) {
                const { selectionChanged, selectedAddress } = await this.fastlane.profile.showShippingAddressSelector();

                console.log('selectedAddress', selectedAddress);

                if (selectionChanged) {
                    this.setShipping(selectedAddress);
                    this.shippingView.refresh();
                }
            }
        });

        // Click change billing address link.
        this.el.changeBillingAddressLink.on('click', async () => {
            if (this.status.hasProfile) {
                this.el.changeCardLink.trigger('click');
            }
        });

        // Click change card link.
        this.el.changeCardLink.on('click', async () => {
            const response = await this.fastlane.profile.showCardSelector();

            console.log('card response', response);

            if (response.selectionChanged) {

                console.log('response.selectedCard.paymentToken', response.selectedCard.paymentToken);

                this.setCard(response.selectedCard);
                this.setBilling({
                    address: response.selectedCard.paymentSource.card.billingAddress
                });
            }
        });

        // Cancel "continuation" mode.
        this.el.showGatewaySelectionLink.on('click', async () => {
            this.hideGatewaySelection = false;
            this.$('.wc_payment_methods label').show();
            this.cardView.refresh();
        });

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

        log('Scenario', scenario);

        // Reset some elements to a default status.
        this.el.watermarkContainer.hide();

        if (scenario.defaultSubmitButton) {
            this.el.defaultSubmitButton.show();
        } else {
            this.el.defaultSubmitButton.hide();
        }

        if (scenario.defaultEmailField) {
            this.el.fieldBillingEmail.show();
        } else {
            this.el.fieldBillingEmail.hide();
        }

        if (scenario.defaultFormFields) {
            this.el.customerDetails.show();
        } else {
            this.el.customerDetails.hide();
        }

        if (scenario.extraFormFields) {
            this.el.customerDetails.show();
            // Hiding of unwanted will be handled by the axoProfileViews handler.
        }

        if (scenario.axoEmailField) {
            this.showAxoEmailField();
            this.el.watermarkContainer.show();

            // Move watermark to after email.
            this.$(this.el.fieldBillingEmail.selector).append(
                this.$(this.el.watermarkContainer.selector)
            );

        } else {
            this.el.emailWidgetContainer.hide();
            if (!scenario.defaultEmailField) {
                this.el.fieldBillingEmail.hide();
            }
        }

        if (scenario.axoProfileViews) {
            this.shippingView.activate();
            this.billingView.activate();
            this.cardView.activate();

            // Move watermark to after shipping.
            this.$(this.el.shippingAddressContainer.selector).after(
                this.$(this.el.watermarkContainer.selector)
            );

            this.el.watermarkContainer.show();

        } else {
            this.shippingView.deactivate();
            this.billingView.deactivate();
            this.cardView.deactivate();
        }

        if (scenario.axoPaymentContainer) {
            this.el.paymentContainer.show();
        } else {
            this.el.paymentContainer.hide();
        }

        if (scenario.axoSubmitButton) {
            this.el.submitButtonContainer.show();
        } else {
            this.el.submitButtonContainer.hide();
        }

        this.ensureBillingFieldsConsistency();
        this.ensureShippingFieldsConsistency();
    }

    identifyScenario(active, validEmail, hasProfile) {
        let response = {
            defaultSubmitButton: false,
            defaultEmailField: false,
            defaultFormFields: false,
            extraFormFields: false,
            axoEmailField: false,
            axoProfileViews: false,
            axoPaymentContainer: false,
            axoSubmitButton: false,
        }

        if (active && validEmail && hasProfile) {
            response.extraFormFields = true;
            response.axoProfileViews = true;
            response.axoPaymentContainer = true;
            response.axoSubmitButton = true;
            return response;
        }
        if (active && validEmail && !hasProfile) {
            response.defaultFormFields = true;
            response.axoEmailField = true;
            response.axoPaymentContainer = true;
            response.axoSubmitButton = true;
            return response;
        }
        if (active && !validEmail) {
            response.axoEmailField = true;
            return response;
        }
        if (!active) {
            response.defaultSubmitButton = true;
            response.defaultEmailField = true;
            response.defaultFormFields = true;
            return response;
        }
        throw new Error('Invalid scenario.');
    }

    ensureBillingFieldsConsistency() {
        const $billingFields = this.$('.woocommerce-billing-fields .form-row:visible');
        const $billingHeaders = this.$('.woocommerce-billing-fields h3');
        if (this.billingView.isActive()) {
            if ($billingFields.length) {
                $billingHeaders.show();
            } else {
                $billingHeaders.hide();
            }
        } else {
            $billingHeaders.show();
        }
    }

    ensureShippingFieldsConsistency() {
        const $shippingFields = this.$('.woocommerce-shipping-fields .form-row:visible');
        const $shippingHeaders = this.$('.woocommerce-shipping-fields h3');
        if (this.shippingView.isActive()) {
            if ($shippingFields.length) {
                $shippingHeaders.show();
            } else {
                $shippingHeaders.hide();
            }
        } else {
            $shippingHeaders.show();
        }
    }

    showAxoEmailField() {
        if (this.status.useEmailWidget) {
            this.el.emailWidgetContainer.show();
            this.el.fieldBillingEmail.hide();
        } else {
            this.el.emailWidgetContainer.hide();
            this.el.fieldBillingEmail.show();
        }
    }

    setStatus(key, value) {
        this.status[key] = value;

        log('Status updated', JSON.parse(JSON.stringify(this.status)));

        this.rerender();
    }

    activateAxo() {
        this.initPlacements();
        this.initFastlane();
        this.setStatus('active', true);

        const emailInput = document.querySelector(this.el.fieldBillingEmail.selector + ' input');
        if (emailInput && this.lastEmailCheckedIdentity !== emailInput.value) {
            this.onChangeEmail();
        }
    }

    deactivateAxo() {
        this.setStatus('active', false);
    }

    initPlacements() {
        const wrapper = this.el.axoCustomerDetails;

        // Customer details container.
        if (!document.querySelector(wrapper.selector)) {
            document.querySelector(wrapper.anchorSelector).insertAdjacentHTML('afterbegin', `
                <div id="${wrapper.id}" class="${wrapper.className}"></div>
            `);
        }

        const wrapperElement = document.querySelector(wrapper.selector);

        // Billing view container.
        const bc = this.el.billingAddressContainer;
        if (!document.querySelector(bc.selector)) {
            wrapperElement.insertAdjacentHTML('beforeend', `
                <div id="${bc.id}" class="${bc.className}"></div>
            `);
        }

        // Shipping view container.
        const sc = this.el.shippingAddressContainer;
        if (!document.querySelector(sc.selector)) {
            wrapperElement.insertAdjacentHTML('beforeend', `
                <div id="${sc.id}" class="${sc.className}"></div>
            `);
        }

        // Watermark container
        const wc = this.el.watermarkContainer;
        if (!document.querySelector(wc.selector)) {
            this.emailInput = document.querySelector(this.el.fieldBillingEmail.selector + ' input');
            this.emailInput.insertAdjacentHTML('afterend', `
                <div class="${wc.className}" id="${wc.id}"></div>
            `);
        }

        // Payment container
        const pc = this.el.paymentContainer;
        if (!document.querySelector(pc.selector)) {
            const gatewayPaymentContainer = document.querySelector('.payment_method_ppcp-axo-gateway');
            gatewayPaymentContainer.insertAdjacentHTML('beforeend', `
                <div id="${pc.id}" class="${pc.className} hidden">
                    <div id="${pc.id}-form" class="${pc.className}-form"></div>
                    <div id="${pc.id}-details" class="${pc.className}-details"></div>
                </div>
            `);
        }

        if (this.useEmailWidget()) {

            // Display email widget.
            const ec = this.el.emailWidgetContainer;
            if (!document.querySelector(ec.selector)) {
                wrapperElement.insertAdjacentHTML('afterbegin', `
                    <div id="${ec.id}" class="${ec.className}">
                    --- EMAIL WIDGET PLACEHOLDER ---
                    </div>
                `);
            }

        } else {

            // Move email to the AXO container.
            let emailRow = document.querySelector(this.el.fieldBillingEmail.selector);
            wrapperElement.prepend(emailRow);
            emailRow.querySelector('input').focus();
        }
    }

    async initFastlane() {
        if (this.initialized) {
            return;
        }
        this.initialized = true;

        await this.connect();
        this.renderWatermark();
        this.watchEmail();
    }

    async connect() {
        window.localStorage.setItem('axoEnv', 'sandbox'); // TODO: check sandbox

        await this.fastlane.connect({
            locale: this.locale,
            styles: this.styles
        });

        this.fastlane.setLocale('en_us');
    }

    triggerGatewayChange() {
        this.el.gatewayRadioButton.trigger('change');
    }

    renderWatermark() {
        this.fastlane.FastlaneWatermarkComponent({
            includeAdditionalInfo: true
        }).render(this.el.watermarkContainer.selector);
    }

    watchEmail() {

        if (this.useEmailWidget()) {

            // TODO

        } else {

            this.emailInput = document.querySelector(this.el.fieldBillingEmail.selector + ' input');
            this.emailInput.addEventListener('change', async ()=> {
                this.onChangeEmail();
            });

            if (this.emailInput.value) {
                this.onChangeEmail();
            }
        }
    }

    async onChangeEmail () {
        this.clearData();

        if (!this.status.active) {
            log('Email checking skipped, AXO not active.');
            return;
        }

        if (!this.emailInput) {
            log('Email field not initialized.');
            return;
        }

        log('Email changed: ' + (this.emailInput ? this.emailInput.value : '<empty>'));

        this.$(this.el.paymentContainer.selector + '-detail').html('');
        this.$(this.el.paymentContainer.selector + '-form').html('');

        this.setStatus('validEmail', false);
        this.setStatus('hasProfile', false);

        this.hideGatewaySelection = false;

        this.lastEmailCheckedIdentity = this.emailInput.value;

        if (!this.emailInput.value || !this.emailInput.checkValidity()) {
            log('The email address is not valid.');
            return;
        }

        this.data.email = this.emailInput.value;
        this.billingView.setData(this.data);

        const lookupResponse = await this.fastlane.identity.lookupCustomerByEmail(this.emailInput.value);

        if (lookupResponse.customerContextId) {
            // Email is associated with a Connect profile or a PayPal member.
            // Authenticate the customer to get access to their profile.
            log('Email is associated with a Connect profile or a PayPal member');

            const authResponse = await this.fastlane.identity.triggerAuthenticationFlow(lookupResponse.customerContextId);

            log('AuthResponse', authResponse);

            if (authResponse.authenticationState === 'succeeded') {
                log(JSON.stringify(authResponse));

                // Add addresses
                this.setShipping(authResponse.profileData.shippingAddress);
                this.setBilling({
                    address: authResponse.profileData.card.paymentSource.card.billingAddress
                });
                this.setCard(authResponse.profileData.card);

                console.log('authResponse', authResponse);

                this.setStatus('validEmail', true);
                this.setStatus('hasProfile', true);

                this.hideGatewaySelection = true;
                this.$('.wc_payment_methods label').hide();

                this.rerender();

            } else {
                // authentication failed or canceled by the customer
                log("Authentication Failed")
            }

        } else {
            // No profile found with this email address.
            // This is a guest customer.
            log('No profile found with this email address.');

            this.setStatus('validEmail', true);
            this.setStatus('hasProfile', false);

            console.log('this.cardComponentData()', this.cardComponentData());

            this.cardComponent = await this.fastlane
                .FastlaneCardComponent(
                    this.cardComponentData()
                )
                .render(this.el.paymentContainer.selector + '-form');
        }
    }

    clearData() {
        this.data = {
            email: null,
            billing: null,
            shipping: null,
            card: null,
        };
    }

    setShipping(shipping) {
        this.data.shipping = shipping;
        this.shippingView.setData(this.data);
    }

    setBilling(billing) {
        this.data.billing = billing;
        this.billingView.setData(this.data);
    }

    setCard(card) {
        this.data.card = card;
        this.cardView.setData(this.data);
    }

    onClickSubmitButton() {
        // TODO: validate data.

        if (this.data.card) { // Ryan flow
            log('Ryan flow.');

            jQuery('#ship-to-different-address-checkbox').prop('checked', 'checked');
            this.billingView.copyDataToForm();
            this.shippingView.copyDataToForm();
            this.cardView.copyDataToForm();

            this.submit(this.data.card.id);

        } else { // Gary flow
            log('Gary flow.');

            try {
                this.cardComponent.tokenize(
                    this.tokenizeData()
                ).then((response) => {
                    this.submit(response.nonce);
                });
            } catch (e) {
                log('Error tokenizing.');
            }
        }
    }

    cardComponentData() {
        return {
            fields: {
                cardholderName: {} // optionally pass this to show the card holder name
            }
        }
    }

    tokenizeData() {
        return {
            name: {
                fullName: this.billingView.fullName()
            },
            billingAddress: {
                addressLine1: this.billingView.inputValue('street1'),
                addressLine2: this.billingView.inputValue('street2'),
                adminArea1: this.billingView.inputValue('city'),
                adminArea2: this.billingView.inputValue('stateCode'),
                postalCode: this.billingView.inputValue('postCode'),
                countryCode: this.billingView.inputValue('countryCode'),
            }
        }
    }

    submit(nonce) {
        // Send the nonce and previously captured device data to server to complete checkout
        log('nonce: ' + nonce);
        alert('nonce: ' + nonce);

        // Submit form.
        if (!this.el.axoNonceInput.get()) {
            this.$('.woocommerce-checkout').append(`<input type="hidden" id="${this.el.axoNonceInput.id}" name="axo_nonce" value="" />`);
        }

        this.el.axoNonceInput.get().value = nonce;

        this.el.defaultSubmitButton.click();
    }

    useEmailWidget() {
        return this.axoConfig?.widgets?.email === 'use_widget';
    }

}

export default AxoManager;
