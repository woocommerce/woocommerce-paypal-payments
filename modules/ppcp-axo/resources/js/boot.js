
let initialized = false;

function log(message) {
    console.log('Log: ', message);

    // let div = document.createElement('div');
    // div.style.cssText = 'margin-bottom: 4px;';
    // div.textContent = message;
    // document.getElementById('log-container').appendChild(div);
}

const init = () => {
    if (initialized) {
        return;
    }
    initialized = true;

    (async function () {
        // sets the SDK to run in sandbox mode (Remove for production)
        window.localStorage.setItem('axoEnv', 'sandbox');

        //specify global styles here
        const styles = {
            root: {
                backgroundColorPrimary: "#ffffff"
            }
        }

        const locale = 'en_us';

        // instantiates the Connect module
        const connect = await window.paypal.Connect({
            locale,
            styles
        });

        console.log('connect', connect);

        // Specifying the locale if necessary
        connect.setLocale('en_us');

        const {
            identity,
            profile,
            ConnectCardComponent,
            ConnectWatermarkComponent
        } = connect;

        console.log(
            '[identity, profile, ConnectCardComponent]',
            identity,
            profile,
            ConnectCardComponent
        );

        //--------
        const emailInput = document.querySelector('#billing_email_field').querySelector('input');

        jQuery(emailInput).after(`
            <div id="watermark-container" style="max-width: 200px; margin-top: 10px;"></div>
        `);

        jQuery('.payment_method_ppcp-axo-gateway').append(`
            <div id="payment-container"></div>
        `);

        const connectWatermarkComponent = ConnectWatermarkComponent({
            includeAdditionalInfo: true
        }).render('#watermark-container');

        //--------

        const emailUpdated = async () => {
            log('Email changed: ' + emailInput.value);

            if (!emailInput.checkValidity()) {
                log('The email address is not valid.');
                return;
            }

            const { customerContextId } = await identity.lookupCustomerByEmail(emailInput.value);

            if (customerContextId) {
                // Email is associated with a Connect profile or aPayPal member
                // Authenticate the customer to get access to their profile
                log('Email is associated with a Connect profile or a PayPal member');
            } else {
                // No profile found with this email address.
                // This is a guest customer.
                log('No profile found with this email address.');

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

                const connectCardComponent = await connect
                    .ConnectCardComponent({fields})
                    .render("#payment-container");

                const submitButton = document.getElementById('submit-button');

                // event listener when the customer clicks to place the order
                submitButton.addEventListener("click", async ()=> {
                    const { nonce } = await connectCardComponent.tokenize({
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
                    });

                    // Send the nonce and previously captured device data to server to complete checkout
                    log('nonce: ' + nonce);

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
                    //         log(JSON.stringify(data));
                    //     })
                    //     .catch(error => {
                    //         console.error('There has been a problem with your fetch operation:', error);
                    //     });

                });
            }
        }

        emailInput.addEventListener("change", async ()=> {
            emailUpdated();
        });

        if (emailInput.value) {
            emailUpdated();
        }

    })();

}

const bootstrap = () => {


    jQuery(document).on('change', '#payment_method_ppcp-axo-gateway', (ev) => {
        if(ev.target.checked) {
            let emailRow = document.querySelector('#billing_email_field');
            jQuery(emailRow.parentNode).prepend(emailRow);
            emailRow.querySelector('input').focus();

            init();
        } else {
            console.log('Checkbox is not checked.');
            // Additional actions when the checkbox is unchecked
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

}

document.addEventListener(
    'DOMContentLoaded',
    () => {
        if (!typeof (PayPalCommerceGateway)) {
            console.error('PayPal button could not be configured.');
            return;
        }

        bootstrap();
    },
);
