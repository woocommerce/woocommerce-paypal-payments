import DomElement from "./DomElement";

class DomElementCollection {

    constructor() {
        this.gatewayRadioButton = new DomElement({
            selector: '#payment_method_ppcp-axo-gateway',
        });

        this.defaultSubmitButton = new DomElement({
            selector: '#place_order',
        });

        this.paymentContainer = new DomElement({
            id: 'ppcp-axo-payment-container',
            selector: '#ppcp-axo-payment-container',
            className: 'ppcp-axo-payment-container'
        });

        this.watermarkContainer = new DomElement({
            id: 'ppcp-axo-watermark-container',
            selector: '#ppcp-axo-watermark-container',
            className: 'ppcp-axo-watermark-container'
        });

        this.allFields = new DomElement({
            selector: '#customer_details .form-row, #customer_details .woocommerce-shipping-fields'
        });

        this.emailWidgetContainer = new DomElement({
            id: 'ppcp-axo-email-widget',
            selector: '#ppcp-axo-email-widget',
            className: 'ppcp-axo-email-widget'
        });

        this.shippingAddressContainer = new DomElement({
            id: 'ppcp-axo-shipping-address-container',
            selector: '#ppcp-axo-shipping-address-container',
            className: 'ppcp-axo-shipping-address-container',
            anchorSelector: '.woocommerce-shipping-fields'
        });

        this.billingAddressContainer = new DomElement({
            id: 'ppcp-axo-billing-address-container',
            selector: '#ppcp-axo-billing-address-container',
            className: 'ppcp-axo-billing-address-container',
            anchorSelector: '.woocommerce-billing-fields__field-wrapper'
        });

        this.fieldBillingEmail = new DomElement({
            selector: '#billing_email_field'
        });

        this.submitButtonContainer = new DomElement({
            selector: '#ppcp-axo-submit-button-container',
        });

        this.submitButton = new DomElement({
            selector: '#ppcp-axo-submit-button-container button'
        });

        this.changeShippingAddressLink = new DomElement({
            selector: '*[data-ppcp-axo-change-shipping-address]',
            attributes: 'data-ppcp-axo-change-shipping-address',
        });

        this.changeBillingAddressLink = new DomElement({
            selector: '*[data-ppcp-axo-change-billing-address]',
            attributes: 'data-ppcp-axo-change-billing-address',
        });

        this.changeCardLink = new DomElement({
            selector: '*[data-ppcp-axo-change-card]',
            attributes: 'data-ppcp-axo-change-card',
        });

        this.showGatewaySelectionLink = new DomElement({
            selector: '*[data-ppcp-axo-show-gateway-selection]',
            attributes: 'data-ppcp-axo-show-gateway-selection',
        });

    }
}

export default DomElementCollection;
