import onApprove from './onApproveForPayNow.js';

class CheckoutActionHandler {

    constructor(config, errorHandler) {
        this.config = config;
        this.errorHandler = errorHandler;
    }

    configuration() {

        const createOrder = (data, actions) => {
            const payer = {
                email_address:(document.querySelector('#billing_email')) ? document.querySelector('#billing_email').value : "",
                name : {
                    surname: (document.querySelector('#billing_last_name')) ? document.querySelector('#billing_last_name').value : "",
                    given_name: (document.querySelector('#billing_first_name')) ? document.querySelector('#billing_first_name').value : ""
                },
                address : {
                    country_code : (document.querySelector('#billing_country')) ? document.querySelector('#billing_country').value : "",
                    address_line_1 : (document.querySelector('#billing_address_1')) ? document.querySelector('#billing_address_1').value : "",
                    address_line_2 : (document.querySelector('#billing_address_2')) ? document.querySelector('#billing_address_2').value : "",
                    admin_area_1 : (document.querySelector('#billing_city')) ? document.querySelector('#billing_city').value : "",
                    admin_area_2 : (document.querySelector('#billing_state')) ? document.querySelector('#billing_state').value : "",
                    postal_code : (document.querySelector('#billing_postcode')) ? document.querySelector('#billing_postcode').value : ""
                },
                phone : {
                    phone_type:"HOME",
                    phone_number:{
                        national_number : (document.querySelector('#billing_phone')) ? document.querySelector('#billing_phone').value : ""
                    }
                }
            };
            return fetch(this.config.ajax.create_order.endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    nonce: this.config.ajax.create_order.nonce,
                    payer
                })
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data.success) {
                    throw Error(data.data);
                }
                return data.data.id;
            });
        }
        return {
            createOrder,
            onApprove:onApprove(this),
            onError: (error) => {
                this.errorHandler.message(error);
            }
        }
    }
}

export default CheckoutActionHandler;
