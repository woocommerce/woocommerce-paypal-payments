import DomElement from './DomElement';

class DomElementCollection {
	constructor() {
		this.gatewayRadioButton = new DomElement( {
			selector: '#payment_method_ppcp-axo-gateway',
		} );

		this.gatewayDescription = new DomElement( {
			selector: '.payment_box.payment_method_ppcp-axo-gateway',
		} );

		this.defaultSubmitButton = new DomElement( {
			selector: '#place_order',
		} );

		this.paymentContainer = new DomElement( {
			id: 'ppcp-axo-payment-container',
			selector: '#ppcp-axo-payment-container',
			className: 'ppcp-axo-payment-container',
		} );

		this.watermarkContainer = new DomElement( {
			id: 'ppcp-axo-watermark-container',
			selector: '#ppcp-axo-watermark-container',
			className:
				'ppcp-axo-watermark-container ppcp-axo-watermark-loading loader',
		} );

		this.customerDetails = new DomElement( {
			selector: '#customer_details > *:not(#ppcp-axo-customer-details)',
		} );

		this.axoCustomerDetails = new DomElement( {
			id: 'ppcp-axo-customer-details',
			selector: '#ppcp-axo-customer-details',
			className: 'ppcp-axo-customer-details',
			anchorSelector: '#customer_details',
		} );

		this.emailWidgetContainer = new DomElement( {
			id: 'ppcp-axo-email-widget',
			selector: '#ppcp-axo-email-widget',
			className: 'ppcp-axo-email-widget',
		} );

		this.shippingAddressContainer = new DomElement( {
			id: 'ppcp-axo-shipping-address-container',
			selector: '#ppcp-axo-shipping-address-container',
			className: 'ppcp-axo-shipping-address-container',
		} );

		this.billingAddressContainer = new DomElement( {
			id: 'ppcp-axo-billing-address-container',
			selector: '#ppcp-axo-billing-address-container',
			className: 'ppcp-axo-billing-address-container',
		} );

		this.fieldBillingEmail = new DomElement( {
			selector: '#billing_email_field',
		} );

		this.billingEmailFieldWrapper = new DomElement( {
			id: 'ppcp-axo-billing-email-field-wrapper',
			selector: '#ppcp-axo-billing-email-field-wrapper',
		} );

		this.billingEmailSubmitButton = new DomElement( {
			id: 'ppcp-axo-billing-email-submit-button',
			selector: '#ppcp-axo-billing-email-submit-button',
			className:
				'ppcp-axo-billing-email-submit-button-hidden button alt wp-element-button wc-block-components-button',
		} );

		this.billingEmailSubmitButtonSpinner = new DomElement( {
			id: 'ppcp-axo-billing-email-submit-button-spinner',
			selector: '#ppcp-axo-billing-email-submit-button-spinner',
			className: 'loader ppcp-axo-overlay',
		} );

		this.submitButtonContainer = new DomElement( {
			selector: '#ppcp-axo-submit-button-container',
		} );

		this.submitButton = new DomElement( {
			selector: '#ppcp-axo-submit-button-container button',
		} );

		this.changeShippingAddressLink = new DomElement( {
			selector: '*[data-ppcp-axo-change-shipping-address]',
			attributes: 'data-ppcp-axo-change-shipping-address',
		} );

		this.changeBillingAddressLink = new DomElement( {
			selector: '*[data-ppcp-axo-change-billing-address]',
			attributes: 'data-ppcp-axo-change-billing-address',
		} );

		this.changeCardLink = new DomElement( {
			selector: '*[data-ppcp-axo-change-card]',
			attributes: 'data-ppcp-axo-change-card',
		} );

		this.showGatewaySelectionLink = new DomElement( {
			selector: '*[data-ppcp-axo-show-gateway-selection]',
			attributes: 'data-ppcp-axo-show-gateway-selection',
		} );

		this.axoNonceInput = new DomElement( {
			id: 'ppcp-axo-nonce',
			selector: '#ppcp-axo-nonce',
		} );
	}
}

export default DomElementCollection;
