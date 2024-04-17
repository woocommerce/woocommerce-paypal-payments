import FormFieldGroup from "../Components/FormFieldGroup";

class CardView {

    constructor(selector, elements, manager) {
        this.el = elements;
        this.manager = manager;

        this.group = new FormFieldGroup({
            baseSelector: '.ppcp-axo-payment-container',
            contentSelector: selector,
            template: (data) => {
                const selectOtherPaymentMethod = () => {
                    if (!this.manager.hideGatewaySelection) {
                        return '';
                    }
                    return `<p style="margin-top: 40px; text-align: center;"><a href="javascript:void(0)" ${this.el.showGatewaySelectionLink.attributes}>Select other payment method</a></p>`;
                };

                if (data.isEmpty()) {
                    return `
                        <div style="margin-bottom: 20px; text-align: center;">
                            <div style="border:2px solid #cccccc; border-radius: 10px; padding: 26px 20px; margin-bottom: 20px; background-color:#f6f6f6">
                                <div>Please fill in your card details.</div>
                            </div>
                            <h4><a href="javascript:void(0)" ${this.el.changeCardLink.attributes}>Add card details</a></h4>
                            ${selectOtherPaymentMethod()}
                        </div>
                    `;
                }

                const expiry = data.value('expiry').split('-');

                const cardIcons = {
                    'VISA':        'visa-dark.svg',
                    'MASTER_CARD': 'mastercard-dark.svg',
                    'AMEX':        'amex.svg',
                    'DISCOVER':    'discover.svg',
                };

                return `
                    <div style="margin-bottom: 20px;">
                        <h3>Card Details <a href="javascript:void(0)" ${this.el.changeCardLink.attributes} style="margin-left: 20px;">Edit</a></h3>
                        <div style="border:2px solid #cccccc; border-radius: 10px; padding: 16px 20px; background-color:#f6f6f6">
                            <div style="float: right;">
                                <img
                                    class="ppcp-card-icon"
                                    title="${data.value('brand')}"
                                    src="/wp-content/plugins/woocommerce-paypal-payments/modules/ppcp-wc-gateway/assets/images/${cardIcons[data.value('brand')]}"
                                    alt="${data.value('brand')}"
                                >
                            </div>
                            <div style="font-family: monospace; font-size: 1rem; margin-top: 10px;">${data.value('lastDigits') ? '**** **** **** ' + data.value('lastDigits'): ''}</div>
                            <div>${expiry[1]}/${expiry[0]}</div>
                            <div style="text-transform: uppercase">${data.value('name')}</div>
                        </div>
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

    activate() {
        this.group.activate();
    }

    deactivate() {
        this.group.deactivate();
    }

    refresh() {
        this.group.refresh();
    }

    setData(data) {
        this.group.setData(data);
    }

    toSubmitData(data) {
        const name = this.group.dataValue('name');
        const { firstName, lastName } = this.splitName(name);

        data['billing_first_name'] = firstName;
        data['billing_last_name'] = lastName ? lastName : firstName;

        return this.group.toSubmitData(data);
    }

    splitName(fullName) {
        let nameParts = fullName.trim().split(' ');
        let firstName = nameParts[0];
        let lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';

        return { firstName, lastName };
    }

}

export default CardView;
