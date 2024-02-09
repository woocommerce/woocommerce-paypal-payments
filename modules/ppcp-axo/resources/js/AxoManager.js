import Fastlane from "./Entity/Fastlane";

class AxoManager {

    constructor() {
        this.initialized = false;
        this.fastlane = new Fastlane();


        jQuery(document).on('change', '#payment_method_ppcp-axo-gateway', (ev) => {
            if(ev.target.checked) {
                let emailRow = document.querySelector('#billing_email_field');
                jQuery(emailRow.parentNode).prepend(emailRow);
                emailRow.querySelector('input').focus();

                this.init();

                // show stuff
                jQuery('#place_order').hide();
                jQuery('#axo-submit-button-container').show();
                jQuery('#payment-container').show();
            } else {
                console.log('Checkbox is not checked.');

                // hide stuff
                jQuery('#place_order').show();
                jQuery('#axo-submit-button-container').hide();
                jQuery('#payment-container').hide();
            }
        });

        let gatewaysRenderedInterval = setInterval(() => {
            console.log('not rendered');
            if (document.querySelector('.wc_payment_methods')) {
                console.log('YES rendered');
                clearInterval(gatewaysRenderedInterval);
                jQuery('#payment_method_ppcp-axo-gateway').trigger('change');
            }
        }, 100);

        jQuery(document.body).on('updated_checkout payment_method_selected', () => {
            jQuery('#payment_method_ppcp-axo-gateway').trigger('change');
        });

        jQuery(document).on('click', '.ppcp-axo-order-button', () => {
            try {

                this.connectCardComponent.tokenize({
                    name: {
                        fullName: "John Doe"
                    },
                    billingAddress: {
                        addressLine1: "2211 North 1st St",
                        adminArea1: "San Jose",
                        adminArea2: "CA",
                        postalCode: "95131",
                        countryCode: "US"
                    }
                }).then((response) => {

                    // Send the nonce and previously captured device data to server to complete checkout
                    this.log('nonce: ' + response.nonce);
                    alert('nonce: ' + response.nonce);

                    // fetch('submit.php', {
                    //     method: 'POST',
                    //     headers: {
                    //         'Content-Type': 'application/json',
                    //     },
                    //     body: JSON.stringify({
                    //         nonce: nonce
                    //     }),
                    // })
                    //
                    //     .then(response => {
                    //         if (!response.ok) {
                    //             throw new Error('Network response was not ok');
                    //         }
                    //         return response.json();
                    //     })
                    //     .then(data => {
                    //         console.log('Submit response', data);
                    //         this.log(JSON.stringify(data));
                    //     })
                    //     .catch(error => {
                    //         console.error('There has been a problem with your fetch operation:', error);
                    //     });

                });

            } catch (e) {
                console.log('Error tokenizing.');
            }

            return false;
        });

    }

    init() {
        if (this.initialized) {
            return;
        }
        this.initialized = true;

        this.initManager();
    }

    async initManager() {
        window.localStorage.setItem('axoEnv', 'sandbox');

        const styles = {
            root: {
                backgroundColorPrimary: "#ffffff"
            }
        }

        const locale = 'en_us';

        await this.fastlane.connect({
            locale,
            styles
        });

        this.fastlane.setLocale('en_us');

        this.emailInput = document.querySelector('#billing_email_field input');

        jQuery(this.emailInput).after(`
            <div id="watermark-container" style="max-width: 200px; margin-top: 10px;"></div>
        `);

        jQuery('.payment_method_ppcp-axo-gateway').append(`
            <div id="payment-container"></div>
        `);

        this.fastlane.FastlaneWatermarkComponent({
            includeAdditionalInfo: true
        }).render('#watermark-container');

        this.emailInput.addEventListener("change", async ()=> {
            this.emailUpdated();
        });

        if (this.emailInput.value) {
            this.emailUpdated();
        }
    }

    async emailUpdated () {
        this.log('Email changed: ' + this.emailInput.value);

        if (!this.emailInput.checkValidity()) {
            this.log('The email address is not valid.');
            return;
        }

        const { customerContextId } = await this.fastlane.identity.lookupCustomerByEmail(this.emailInput.value);

        if (customerContextId) {
            // Email is associated with a Connect profile or aPayPal member
            // Authenticate the customer to get access to their profile
            this.log('Email is associated with a Connect profile or a PayPal member');
        } else {
            // No profile found with this email address.
            // This is a guest customer.
            this.log('No profile found with this email address.');

            const fields = {
                phoneNumber: {
                    prefill: "1234567890"
                },

                cardholderName: {} // optionally pass this to show the card holder name
            };

            jQuery("#payment-container").css({
                'padding': '1rem 0',
                'background-color': '#ffffff',
            });

            this.connectCardComponent = await this.fastlane
                .FastlaneCardComponent({fields})
                .render("#payment-container");
        }
    }

    log(message) {
        console.log('[AXO] ', message);
    }

}

export default AxoManager;
