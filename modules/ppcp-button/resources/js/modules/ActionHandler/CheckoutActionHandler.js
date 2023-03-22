import 'formdata-polyfill';
import onApprove from '../OnApproveHandler/onApproveForPayNow.js';
import {payerData} from "../Helper/PayerData";
import {getCurrentPaymentMethod} from "../Helper/CheckoutMethodState";

class CheckoutActionHandler {

    constructor(config, errorHandler, spinner) {
        this.config = config;
        this.errorHandler = errorHandler;
        this.spinner = spinner;
    }

    configuration() {
        const spinner = this.spinner;
        const createOrder = (data, actions) => {
            const payer = payerData();
            const bnCode = typeof this.config.bn_codes[this.config.context] !== 'undefined' ?
                this.config.bn_codes[this.config.context] : '';

            const errorHandler = this.errorHandler;

            const formSelector = this.config.context === 'checkout' ? 'form.checkout' : 'form#order_review';
            const formData = new FormData(document.querySelector(formSelector));
            // will not handle fields with multiple values (checkboxes, <select multiple>), but we do not care about this here
            const formJsonObj = Object.fromEntries(formData.entries());

            const createaccount = jQuery('#createaccount').is(":checked") ? true : false;

            const paymentMethod = getCurrentPaymentMethod();
            const fundingSource = window.ppcpFundingSource;

            return fetch(this.config.ajax.create_order.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: this.config.ajax.create_order.nonce,
                    payer,
                    bn_code:bnCode,
                    context:this.config.context,
                    order_id:this.config.order_id,
                    payment_method: paymentMethod,
                    funding_source: fundingSource,
                    form: formJsonObj,
                    createaccount: createaccount
                })
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data.success) {
                    spinner.unblock();
                    //handle both messages sent from Woocommerce (data.messages) and this plugin (data.data.message)
                    if (typeof(data.messages) !== 'undefined' )
                    {
                        const domParser = new DOMParser();
                        errorHandler.appendPreparedErrorMessageElement(
                            domParser.parseFromString(data.messages, 'text/html')
                                .querySelector('ul')
                        );
                    } else {
                        errorHandler.clear();
                        if (data.data.errors?.length > 0) {
                            errorHandler.messages(data.data.errors);
                        } else if (data.data.details?.length > 0) {
                            errorHandler.message(data.data.details.map(d => `${d.issue} ${d.description}`).join('<br/>'));
                        } else {
                            errorHandler.message(data.data.message);
                        }
                    }

                    throw {type: 'create-order-error', data: data.data};
                }
                const input = document.createElement('input');
                input.setAttribute('type', 'hidden');
                input.setAttribute('name', 'ppcp-resume-order');
                input.setAttribute('value', data.data.purchase_units[0].custom_id);
                document.querySelector(formSelector).appendChild(input);
                return data.data.id;
            });
        }
        return {
            createOrder,
            onApprove:onApprove(this, this.errorHandler, this.spinner),
            onCancel: () => {
                spinner.unblock();
            },
            onError: (err) => {
                console.error(err);
                spinner.unblock();

                if (err && err.type === 'create-order-error') {
                    return;
                }

                this.errorHandler.genericError();
            }
        }
    }
}

export default CheckoutActionHandler;
