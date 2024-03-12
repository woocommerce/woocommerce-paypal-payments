import FormFieldGroup from "../Components/FormFieldGroup";

class CardView {

    constructor(selector, elements, manager) {
        this.el = elements;
        this.manager = manager;

        this.cardFormFields = new FormFieldGroup({
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
                            <div>Please fill in your card details.</div>
                            <h4><a href="javascript:void(0)" ${this.el.changeCardLink.attributes}>Edit</a></h4>
                            ${selectOtherPaymentMethod()}
                        </div>
                    `;
                }
                return `
                    <div style="margin-bottom: 20px;">
                        <h3>Card Details <a href="javascript:void(0)" ${this.el.changeCardLink.attributes}>Edit</a></h3>
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

    activate() {
        this.cardFormFields.activate();
    }

    deactivate() {
        this.cardFormFields.deactivate();
    }

    refresh() {
        this.cardFormFields.refresh();
    }

    setData(data) {
        this.cardFormFields.setData(data);
    }

}

export default CardView;
