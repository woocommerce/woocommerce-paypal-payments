import Fastlane from "./Entity/Fastlane";
import MockData from "./Helper/MockData";
import {log} from "./Helper/Debug";
import {hide, show} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

class AxoManager {

    constructor(axoConfig, ppcpConfig) {
        this.axoConfig = axoConfig;
        this.ppcpConfig = ppcpConfig;

        this.initialized = false;
        this.fastlane = new Fastlane();
        this.$ = jQuery;

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
            fieldBillingEmail: {
                selector: '#billing_email_field'
            },
            submitButtonContainer: {
                selector: '#ppcp-axo-submit-button-container'
            },
        }

        this.styles = {
            root: {
                backgroundColorPrimary: '#ffffff'
            }
        }

        this.locale = 'en_us';

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

        this.$(document).on('click', this.elements.submitButtonContainer.selector + ' button', () => {
            this.onClickSubmitButton();
            return false;
        });

    }

    showAxo() {
        this.initEmail();
        this.init();

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
        hide(this.elements.emailWidgetContainer.selector);
        hide(this.elements.watermarkContainer.selector);
        hide(this.elements.paymentContainer.selector);
        hide(this.elements.submitButtonContainer.selector);
        show(this.elements.defaultSubmitButton.selector);

        if (this.useEmailWidget()) {
            show(this.elements.fieldBillingEmail.selector);
        }
    }

    initEmail() {
        let emailRow = document.querySelector(this.elements.fieldBillingEmail.selector);

        if (this.useEmailWidget()) {

            // Display email widget.
            if (!document.querySelector(this.elements.emailWidgetContainer.selector)) {
                emailRow.parentNode.insertAdjacentHTML('afterbegin', `
                    <div id="${this.elements.emailWidgetContainer.id}" class="${this.elements.emailWidgetContainer.className}">
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

    async init() {
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

        if (!this.emailInput.checkValidity()) {
            log('The email address is not valid.');
            return;
        }

        const { customerContextId } = await this.fastlane.identity.lookupCustomerByEmail(this.emailInput.value);

        if (customerContextId) {
            // Email is associated with a Connect profile or a PayPal member.
            // Authenticate the customer to get access to their profile.
            log('Email is associated with a Connect profile or a PayPal member');
        } else {
            // No profile found with this email address.
            // This is a guest customer.
            log('No profile found with this email address.');

            document.querySelector(this.elements.paymentContainer.selector)?.classList.remove('hidden');

            this.cardComponent = await this.fastlane
                .FastlaneCardComponent(MockData.cardComponent())
                .render(this.elements.paymentContainer.selector);
        }
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

        // fetch('submit.php', {
        //     method: 'POST',
        //     headers: {
        //         'Content-Type': 'application/json',
        //     },
        //     body: JSON.stringify({
        //         nonce: nonce
        //     }),
        // })
        //     .then(response => {
        //         if (!response.ok) {
        //             throw new Error('Network response was not ok');
        //         }
        //         return response.json();
        //     })
        //     .then(data => {
        //         log('Submit response', data);
        //         log(JSON.stringify(data));
        //     })
        //     .catch(error => {
        //         console.error('There has been a problem with your fetch operation:', error);
        //     });

        // Submit form.
        document.querySelector(this.elements.defaultSubmitButton.selector).click();
    }

    useEmailWidget() {
        return this.axoConfig?.widgets?.email === 'use_widget';
    }

}

export default AxoManager;
