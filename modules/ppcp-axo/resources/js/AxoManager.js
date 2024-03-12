import Fastlane from "./Connection/Fastlane";
import MockData from "./Helper/MockData";
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

        this.isConnectProfile = false;
        this.isNewProfile = false;
        this.hideGatewaySelection = false;

        this.data = {
            billing: null,
            shipping: null,
            card: null,
        }

        this.el = new DomElementCollection();

        this.styles = {
            root: {
                backgroundColorPrimary: '#ffffff'
            }
        }

        this.locale = 'en_us';

        this.registerEventHandlers();

        this.shippingView = new ShippingView(this.el.shippingAddressContainer.selector, this.el);
        this.billingView = new BillingView(this.el.billingAddressContainer.selector, this.el);
        this.cardView = new CardView(this.el.paymentContainer.selector, this.el, this);
    }

    registerEventHandlers() {

        // Listen to Gateway Radio button changes.
        this.el.gatewayRadioButton.on('change', (ev) => {
            if (ev.target.checked) {
                this.showAxo();
            } else {
                this.hideAxo();
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
            if (this.isConnectProfile) {
                console.log('profile', this.fastlane.profile);

                //this.shippingView.deactivate();

                const { selectionChanged, selectedAddress } = await this.fastlane.profile.showShippingAddressSelector();

                console.log('selectedAddress', selectedAddress);

                if (selectionChanged) {
                    this.setShipping(selectedAddress);
                    this.shippingView.activate();
                }
            }
        });

        // Click change billing address link.
        this.el.changeBillingAddressLink.on('click', async () => {
            if (this.isConnectProfile) {
                this.el.changeCardLink.trigger('click');
            }
        });

        // Click change card link.
        this.el.changeCardLink.on('click', async () => {
            console.log('profile', this.fastlane.profile);

            const response = await this.fastlane.profile.showCardSelector();

            console.log('card response', response);

            if (response.selectionChanged) {
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

    showAxo() {
        this.initPlacements();
        this.initFastlane();

        if (!this.isNewProfile && !this.isConnectProfile) {
            this.el.allFields.hide();
        }

        if (this.useEmailWidget()) {
            this.el.emailWidgetContainer.show();
            this.el.fieldBillingEmail.hide();
        } else {
            this.el.emailWidgetContainer.hide();
            this.el.fieldBillingEmail.show();
        }

        if (this.isConnectProfile) {
            this.shippingView.activate();
            this.billingView.activate();

            this.el.emailWidgetContainer.hide();
            this.el.fieldBillingEmail.hide();
        }

        this.el.watermarkContainer.show();
        this.el.paymentContainer.show();
        this.el.submitButtonContainer.show();
        this.el.defaultSubmitButton.hide();
    }

    hideAxo() {
        this.el.allFields.show();

        this.shippingView.deactivate();
        this.billingView.deactivate();

        this.el.emailWidgetContainer.hide();
        this.el.watermarkContainer.hide();
        this.el.paymentContainer.hide();
        this.el.submitButtonContainer.hide();
        this.el.defaultSubmitButton.show();

        this.el.emailWidgetContainer.hide();
        this.el.fieldBillingEmail.show();
    }

    initPlacements() {
        let emailRow = document.querySelector(this.el.fieldBillingEmail.selector);

        const bc = this.el.billingAddressContainer;
        const sc = this.el.shippingAddressContainer;
        const ec = this.el.emailWidgetContainer;

        if (!document.querySelector(bc.selector)) {
            document.querySelector(bc.anchorSelector).insertAdjacentHTML('beforeend', `
                <div id="${bc.id}" class="${bc.className}"></div>
            `);
        }

        if (!document.querySelector(sc.selector)) {
            document.querySelector(sc.anchorSelector).insertAdjacentHTML('afterbegin', `
                <div id="${sc.id}" class="${sc.className}"></div>
            `);
        }

        if (this.useEmailWidget()) {

            // Display email widget.
            if (!document.querySelector(ec.selector)) {
                emailRow.parentNode.insertAdjacentHTML('afterbegin', `
                    <div id="${ec.id}" class="${ec.className}">
                    --- EMAIL WIDGET PLACEHOLDER ---
                    </div>
                `);
            }

        } else {

            // Move email row to first place.
            emailRow.parentNode.prepend(emailRow);
            emailRow.querySelector('input').focus();
        }
    }

    async initFastlane() {
        if (this.initialized) {
            return;
        }
        this.initialized = true;

        await this.connect();
        this.insertDomElements();
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

    insertDomElements() {
        this.emailInput = document.querySelector(this.el.fieldBillingEmail.selector + ' input');
        this.emailInput.insertAdjacentHTML('afterend', `
            <div class="${this.el.watermarkContainer.className}" id="${this.el.watermarkContainer.id}"></div>
        `);

        const gatewayPaymentContainer = document.querySelector('.payment_method_ppcp-axo-gateway');
        gatewayPaymentContainer.insertAdjacentHTML('beforeend', `
            <div id="${this.el.paymentContainer.id}" class="${this.el.paymentContainer.className} hidden"></div>
        `);
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
        log('Email changed: ' + this.emailInput.value);

        this.isConnectProfile = false;
        this.isNewProfile = false;
        this.hideGatewaySelection = false;

        this.el.allFields.hide();

        if (!this.emailInput.checkValidity()) {
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

            // TODO : enter hideOtherGateways mode

            const authResponse = await this.fastlane.identity.triggerAuthenticationFlow(lookupResponse.customerContextId);

            log('AuthResponse', authResponse);

            if (authResponse.authenticationState === 'succeeded') {
                log(JSON.stringify(authResponse));

                this.el.allFields.show();
                this.el.paymentContainer.show();


                // document.querySelector(this.el.paymentContainer.selector).innerHTML =
                //     '<a href="javascript:void(0)" data-ppcp-axo-change-card>Change card</a>';

                // Add addresses
                this.setShipping(authResponse.profileData.shippingAddress);
                // TODO : set billing
                this.setCard(authResponse.profileData.card);

                this.isConnectProfile = true;
                this.hideGatewaySelection = true;
                this.$('.wc_payment_methods label').hide();

                this.shippingView.activate();
                this.billingView.activate();
                this.cardView.activate();

            } else {
                // authentication failed or canceled by the customer
                log("Authentication Failed")
            }

        } else {
            // No profile found with this email address.
            // This is a guest customer.
            log('No profile found with this email address.');

            this.el.allFields.show();
            this.el.paymentContainer.show();

            this.isNewProfile = true;

            this.cardComponent = await this.fastlane
                .FastlaneCardComponent(MockData.cardComponent())
                .render(this.el.paymentContainer.selector);
        }
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
        try {
            this.cardComponent.tokenize(MockData.cardComponentTokenize()).then((response) => {
                this.submit(response.nonce);
            });
        } catch (e) {
            log('Error tokenizing.');
        }
    }

    submit(nonce) {
        // Send the nonce and previously captured device data to server to complete checkout
        log('nonce: ' + nonce);
        alert('nonce: ' + nonce);

        // Submit form.
        this.el.defaultSubmitButton.click();
    }

    useEmailWidget() {
        return this.axoConfig?.widgets?.email === 'use_widget';
    }

}

export default AxoManager;
