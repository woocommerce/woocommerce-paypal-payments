import Fastlane from "./Entity/Fastlane";
import MockData from "./Helper/MockData";
import {log} from "./Helper/Debug";
import {hide, show} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

class AxoManager {

    constructor(jQuery) {
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
                id: 'axo-payment-container',
                selector: '#axo-payment-container',
                className: 'axo-payment-container'
            },
            watermarkContainer: {
                id: 'axo-watermark-container',
                selector: '#axo-watermark-container',
                className: 'axo-watermark-container'
            },
            submitButtonContainer: {
                selector: '#axo-submit-button-container'
            },
            fieldBillingEmail: {
                selector: '#billing_email_field'
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
        this.moveEmail();
        this.init();

        show(this.elements.watermarkContainer.selector);
        show(this.elements.paymentContainer.selector);
        show(this.elements.submitButtonContainer.selector);
        hide(this.elements.defaultSubmitButton.selector);
    }

    hideAxo() {
        hide(this.elements.watermarkContainer.selector);
        hide(this.elements.paymentContainer.selector);
        hide(this.elements.submitButtonContainer.selector);
        show(this.elements.defaultSubmitButton.selector);
    }

    moveEmail() {
        // Move email row to first place.
        let emailRow = document.querySelector(this.elements.fieldBillingEmail.selector);
        emailRow.parentNode.prepend(emailRow);
        emailRow.querySelector('input').focus();
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
            <div class="${this.elements.paymentContainer.className}" id="${this.elements.paymentContainer.id}"></div>
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
        this.emailInput = document.querySelector(this.elements.fieldBillingEmail.selector + ' input');
        this.emailInput.addEventListener('change', async ()=> {
            this.onChangeEmail();
        });

        if (this.emailInput.value) {
            this.onChangeEmail();
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

            this.connectCardComponent = await this.fastlane
                .FastlaneCardComponent(MockData.cardComponent())
                .render(this.elements.paymentContainer.selector);
        }
    }

    onClickSubmitButton() {
        try {
            this.connectCardComponent.tokenize(MockData.cardComponentTokenize()).then((response) => {
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
    }

}

export default AxoManager;
