import Fastlane from "./Entity/Fastlane";
import MockData from "./Helper/MockData";
import {log} from "./Helper/Debug";
import {hide, show} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';
import FormFieldGroup from "./Helper/FormFieldGroup";

class AxoManager {

    constructor(axoConfig, ppcpConfig) {
        this.axoConfig = axoConfig;
        this.ppcpConfig = ppcpConfig;

        this.initialized = false;
        this.fastlane = new Fastlane();
        this.$ = jQuery;

        this.isConnectProfile = false;
        this.hideGatewaySelection = false;

        this.data = {
            billing: null,
            shipping: null,
            card: null,
        }

        this.elements = {
            gatewayRadioButton: {
                selector: '#payment_method_ppcp-axo-gateway',
            },
            defaultSubmitButton: {
                selector: '#place_order',
            },
            paymentContainer: {
                id: 'ppcp-axo-payment-container',
                selector: '#ppcp-axo-payment-container',
                className: 'ppcp-axo-payment-container'
            },
            watermarkContainer: {
                id: 'ppcp-axo-watermark-container',
                selector: '#ppcp-axo-watermark-container',
                className: 'ppcp-axo-watermark-container'
            },
            emailWidgetContainer: {
                id: 'ppcp-axo-email-widget',
                selector: '#ppcp-axo-email-widget',
                className: 'ppcp-axo-email-widget'
            },
            shippingAddressContainer: {
                id: 'ppcp-axo-shipping-address-container',
                selector: '#ppcp-axo-shipping-address-container',
                className: 'ppcp-axo-shipping-address-container',
                anchorSelector: '.woocommerce-shipping-fields'
            },
            billingAddressContainer: {
                id: 'ppcp-axo-billing-address-container',
                selector: '#ppcp-axo-billing-address-container',
                className: 'ppcp-axo-billing-address-container',
                anchorSelector: '.woocommerce-billing-fields__field-wrapper'
            },
            fieldBillingEmail: {
                selector: '#billing_email_field'
            },
            submitButtonContainer: {
                selector: '#ppcp-axo-submit-button-container',
                buttonSelector: '#ppcp-axo-submit-button-container button'
            },
        }

        this.styles = {
            root: {
                backgroundColorPrimary: '#ffffff'
            }
        }

        this.locale = 'en_us';

        this.registerEventHandlers();

        this.shippingFormFields = new FormFieldGroup({
            baseSelector: '.woocommerce-checkout',
            contentSelector: this.elements.shippingAddressContainer.selector,
            template: (data) => {
                const valueOfSelect = (selectSelector, key) => {
                    const selectElement = document.querySelector(selectSelector);
                    const option = selectElement.querySelector(`option[value="${key}"]`);
                    return option ? option.textContent : key;
                }

                if (data.isEditing()) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h3>Shipping details <a href="javascript:void(0)" data-ppcp-axo-save-shipping-address style="margin-left: 20px;">Save</a></h3>
                        </div>
                    `;
                }
                if (data.isEmpty()) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h3>Shipping details <a href="javascript:void(0)" data-ppcp-axo-change-shipping-address style="margin-left: 20px;">Edit</a></h3>
                            <div>Please fill in your shipping details.</div>
                        </div>
                    `;
                }
                return `
                    <div style="margin-bottom: 20px;">
                        <h3>Shipping details <a href="javascript:void(0)" data-ppcp-axo-change-shipping-address style="margin-left: 20px;">Edit</a></h3>
                        <div>${data.value('company')}</div>
                        <div>${data.value('firstName')} ${data.value('lastName')}</div>
                        <div>${data.value('street1')}</div>
                        <div>${data.value('street2')}</div>
                        <div>${data.value('postCode')} ${data.value('city')}</div>
                        <div>${valueOfSelect('#shipping_state', data.value('stateCode'))}</div>
                        <div>${valueOfSelect('#shipping_country', data.value('countryCode'))}</div>
                    </div>
                `;
            },
            fields: {
                firstName: {
                    'key': 'firstName',
                    'selector': '#shipping_first_name_field',
                    'valuePath': 'shipping.name.firstName',
                },
                lastName: {
                    'selector': '#shipping_last_name_field',
                    'valuePath': 'shipping.name.lastName',
                },
                street1: {
                    'selector': '#shipping_address_1_field',
                    'valuePath': 'shipping.address.addressLine1',
                },
                street2: {
                    'selector': '#shipping_address_2_field',
                    'valuePath': null
                },
                postCode: {
                    'selector': '#shipping_postcode_field',
                    'valuePath': 'shipping.address.postalCode',
                },
                city: {
                    'selector': '#shipping_city_field',
                    'valuePath': 'shipping.address.adminArea2',
                },
                stateCode: {
                    'selector': '#shipping_state_field',
                    'valuePath': 'shipping.address.adminArea1',
                },
                countryCode: {
                    'selector': '#shipping_country_field',
                    'valuePath': 'shipping.address.countryCode',
                },
                company: {
                    'selector': '#shipping_company_field',
                    'valuePath': null,
                },
                shipDifferentAddress: {
                    'selector': '#ship-to-different-address',
                    'valuePath': null,
                }
            }
        });

        this.billingFormFields = new FormFieldGroup({
            baseSelector: '.woocommerce-checkout',
            contentSelector: this.elements.billingAddressContainer.selector,
            template: (data) => {
                const valueOfSelect = (selectSelector, key) => {
                    const selectElement = document.querySelector(selectSelector);
                    const option = selectElement.querySelector(`option[value="${key}"]`);
                    return option ? option.textContent : key;
                }

                if (data.isEditing()) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h4><a href="javascript:void(0)" data-ppcp-axo-save-billing-address>Save</a></h4>
                        </div>
                    `;
                }
                if (data.isEmpty()) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <div>Please fill in your billing details.</div>
                            <h4><a href="javascript:void(0)" data-ppcp-axo-change-billing-address>Edit</a></h4>
                        </div>
                    `;
                }
                return `
                    <div style="margin-bottom: 20px;">
                        <h4>Billing address</h4>
                        <div>${data.value('company')}</div>
                        <div>${data.value('firstName')} ${data.value('lastName')}</div>
                        <div>${data.value('street1')}</div>
                        <div>${data.value('street2')}</div>
                        <div>${data.value('postCode')} ${data.value('city')}</div>
                        <div>${valueOfSelect('#billing_state', data.value('stateCode'))}</div>
                        <div>${valueOfSelect('#billing_country', data.value('countryCode'))}</div>
                        <div>${data.value('phone')}</div>
                        <h4><a href="javascript:void(0)" data-ppcp-axo-change-billing-address>Edit</a></h4>
                    </div>
                `;
            },
            fields: {
                firstName: {
                    'selector': '#billing_first_name_field',
                    'valuePath': 'billing.name.firstName',
                },
                lastName: {
                    'selector': '#billing_last_name_field',
                    'valuePath': 'billing.name.lastName',
                },
                street1: {
                    'selector': '#billing_address_1_field',
                    'valuePath': 'billing.address.addressLine1',
                },
                street2: {
                    'selector': '#billing_address_2_field',
                    'valuePath': null
                },
                postCode: {
                    'selector': '#billing_postcode_field',
                    'valuePath': 'billing.address.postalCode',
                },
                city: {
                    'selector': '#billing_city_field',
                    'valuePath': 'billing.address.adminArea2',
                },
                stateCode: {
                    'selector': '#billing_state_field',
                    'valuePath': 'billing.address.adminArea1',
                },
                countryCode: {
                    'selector': '#billing_country_field',
                    'valuePath': 'billing.address.countryCode',
                },
                company: {
                    'selector': '#billing_company_field',
                    'valuePath': null,
                },
                phone: {
                    'selector': '#billing_phone_field',
                    'valuePath': null,
                },
            }
        });

        this.cardFormFields = new FormFieldGroup({
            baseSelector: '.ppcp-axo-payment-container',
            contentSelector: this.elements.paymentContainer.selector,
            template: (data) => {
                const selectOtherPaymentMethod = () => {
                    if (!this.hideGatewaySelection) {
                        return '';
                    }
                    return `<p style="margin-top: 40px; text-align: center;"><a href="javascript:void(0)" data-ppcp-axo-show-gateway-selection>Select other payment method</a></p>`;
                };

                if (data.isEmpty()) {
                    return `
                        <div style="margin-bottom: 20px; text-align: center;">
                            <div>Please fill in your card details.</div>
                            <h4><a href="javascript:void(0)" data-ppcp-axo-change-card>Edit</a></h4>
                            ${selectOtherPaymentMethod()}
                        </div>
                    `;
                }
                return `
                    <div style="margin-bottom: 20px;">
                        <h3>Card Details <a href="javascript:void(0)" data-ppcp-axo-change-card>Edit</a></h3>
                        <div>${data.value('name')}</div>
                        <div>${data.value('brand')}</div>
                        <div>${data.value('lastDigits') ? '************' + data.value('lastDigits'): ''}</div>
                        <div>${data.value('expiry')}</div>
                        ${selectOtherPaymentMethod()}
                    </div>
                `;
            },
            fields: {
                brand: {
                    'valuePath': 'card.paymentSource.card.brand',
                },
                expiry: {
                    'valuePath': 'card.paymentSource.card.expiry',
                },
                lastDigits: {
                    'valuePath': 'card.paymentSource.card.lastDigits',
                },
                name: {
                    'valuePath': 'card.paymentSource.card.name',
                },
            }
        });

    }

    registerEventHandlers() {

        // Listen to Gateway Radio button changes.
        this.$(document).on('change', this.elements.gatewayRadioButton.selector, (ev) => {
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
        this.$(document).on('click', this.elements.submitButtonContainer.buttonSelector, () => {
            this.onClickSubmitButton();
            return false;
        });

        // Click change shipping address link.
        this.$(document).on('click', '*[data-ppcp-axo-change-shipping-address]', async () => {

            if (this.isConnectProfile) {
                console.log('profile', this.fastlane.profile);

                //this.shippingFormFields.deactivate();

                const { selectionChanged, selectedAddress } = await this.fastlane.profile.showShippingAddressSelector();

                console.log('selectedAddress', selectedAddress);

                if (selectionChanged) {
                    this.setShipping(selectedAddress);
                    this.shippingFormFields.activate();
                }
            } else {
                let checkbox = document.querySelector('#ship-to-different-address-checkbox');
                if (checkbox && !checkbox.checked) {
                    jQuery(checkbox).trigger('click');
                }
                this.shippingFormFields.deactivate();
            }

        });

        this.$(document).on('click', '*[data-ppcp-axo-save-shipping-address]', async () => {
            this.shippingFormFields.activate();
        });

        // Click change billing address link.
        this.$(document).on('click', '*[data-ppcp-axo-change-billing-address]', async () => {
            if (this.isConnectProfile) {
                this.$('*[data-ppcp-axo-change-card]').trigger('click');
            } else {
                this.billingFormFields.deactivate();
            }
        });

        this.$(document).on('click', '*[data-ppcp-axo-save-billing-address]', async () => {
            this.billingFormFields.activate();
        });

        // Click change card link.
        this.$(document).on('click', '*[data-ppcp-axo-change-card]', async () => {
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
        this.$(document).on('click', '*[data-ppcp-axo-show-gateway-selection]', async () => {
            this.hideGatewaySelection = false;
            this.$('.wc_payment_methods label').show();
            this.cardFormFields.refresh();
        });

    }

    showAxo() {
        this.initPlacements();
        this.initFastlane();

        this.shippingFormFields.activate();
        this.billingFormFields.activate();

        show(this.elements.emailWidgetContainer.selector);
        show(this.elements.watermarkContainer.selector);
        show(this.elements.paymentContainer.selector);
        show(this.elements.submitButtonContainer.selector);
        hide(this.elements.defaultSubmitButton.selector);

        if (this.useEmailWidget()) {
            hide(this.elements.fieldBillingEmail.selector);
        }
    }

    hideAxo() {
        this.shippingFormFields.deactivate();
        this.billingFormFields.deactivate();

        hide(this.elements.emailWidgetContainer.selector);
        hide(this.elements.watermarkContainer.selector);
        hide(this.elements.paymentContainer.selector);
        hide(this.elements.submitButtonContainer.selector);
        show(this.elements.defaultSubmitButton.selector);

        if (this.useEmailWidget()) {
            show(this.elements.fieldBillingEmail.selector);
        }
    }

    initPlacements() {
        let emailRow = document.querySelector(this.elements.fieldBillingEmail.selector);

        const bc = this.elements.billingAddressContainer;
        const sc = this.elements.shippingAddressContainer;
        const ec = this.elements.emailWidgetContainer;

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
        this.emailInput = document.querySelector(this.elements.fieldBillingEmail.selector + ' input');
        this.emailInput.insertAdjacentHTML('afterend', `
            <div class="${this.elements.watermarkContainer.className}" id="${this.elements.watermarkContainer.id}"></div>
        `);

        const gatewayPaymentContainer = document.querySelector('.payment_method_ppcp-axo-gateway');
        gatewayPaymentContainer.insertAdjacentHTML('beforeend', `
            <div id="${this.elements.paymentContainer.id}" class="${this.elements.paymentContainer.className} hidden"></div>
        `);
    }

    triggerGatewayChange() {
        this.$(this.elements.gatewayRadioButton.selector).trigger('change');
    }

    renderWatermark() {
        this.fastlane.FastlaneWatermarkComponent({
            includeAdditionalInfo: true
        }).render(this.elements.watermarkContainer.selector);
    }

    watchEmail() {

        if (this.useEmailWidget()) {

            // TODO

        } else {

            this.emailInput = document.querySelector(this.elements.fieldBillingEmail.selector + ' input');
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
        this.hideGatewaySelection = false;

        if (!this.emailInput.checkValidity()) {
            log('The email address is not valid.');
            return;
        }

        const lookupResponse = await this.fastlane.identity.lookupCustomerByEmail(this.emailInput.value);

        show(this.elements.paymentContainer.selector);

        if (lookupResponse.customerContextId) {
            // Email is associated with a Connect profile or a PayPal member.
            // Authenticate the customer to get access to their profile.
            log('Email is associated with a Connect profile or a PayPal member');

            // TODO : enter hideOtherGateways mode

            const authResponse = await this.fastlane.identity.triggerAuthenticationFlow(lookupResponse.customerContextId);

            log('AuthResponse', authResponse);

            if (authResponse.authenticationState === 'succeeded') {
                log(JSON.stringify(authResponse));

                // document.querySelector(this.elements.paymentContainer.selector).innerHTML =
                //     '<a href="javascript:void(0)" data-ppcp-axo-change-card>Change card</a>';

                // Add addresses
                this.setShipping(authResponse.profileData.shippingAddress);
                // TODO : set billing
                this.setCard(authResponse.profileData.card);

                this.isConnectProfile = true;
                this.hideGatewaySelection = true;
                this.$('.wc_payment_methods label').hide();

                this.shippingFormFields.activate();
                this.billingFormFields.activate();
                this.cardFormFields.activate();

            } else {
                // authentication failed or canceled by the customer
                log("Authentication Failed")
            }

        } else {
            // No profile found with this email address.
            // This is a guest customer.
            log('No profile found with this email address.');

            this.cardComponent = await this.fastlane
                .FastlaneCardComponent(MockData.cardComponent())
                .render(this.elements.paymentContainer.selector);
        }
    }

    setShipping(shipping) {
        this.data.shipping = shipping;
        this.shippingFormFields.setData(this.data);
    }

    setBilling(billing) {
        this.data.billing = billing;
        this.billingFormFields.setData(this.data);
    }

    setCard(card) {
        this.data.card = card;
        this.cardFormFields.setData(this.data);
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
        document.querySelector(this.elements.defaultSubmitButton.selector).click();
    }

    useEmailWidget() {
        return this.axoConfig?.widgets?.email === 'use_widget';
    }

}

export default AxoManager;
