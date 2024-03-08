import FormFieldGroup from "../Helper/FormFieldGroup";

class BillingView {

    constructor(selector) {
        this.billingFormFields = new FormFieldGroup({
            baseSelector: '.woocommerce-checkout',
            contentSelector: selector,
            template: (data) => {
                const valueOfSelect = (selectSelector, key) => {
                    const selectElement = document.querySelector(selectSelector);
                    const option = selectElement.querySelector(`option[value="${key}"]`);
                    return option ? option.textContent : key;
                }

                if (data.isEditing()) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h4><a href="javascript:void(0)" data-ppcp-axo-save-billing-address>Save</a></h4>
                        </div>
                    `;
                }
                if (data.isEmpty()) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <div>Please fill in your billing details.</div>
                            <h4><a href="javascript:void(0)" data-ppcp-axo-change-billing-address>Edit</a></h4>
                        </div>
                    `;
                }
                return `
                    <div style="margin-bottom: 20px;">
                        <h4>Billing address</h4>
                        <div>${data.value('company')}</div>
                        <div>${data.value('firstName')} ${data.value('lastName')}</div>
                        <div>${data.value('street1')}</div>
                        <div>${data.value('street2')}</div>
                        <div>${data.value('postCode')} ${data.value('city')}</div>
                        <div>${valueOfSelect('#billing_state', data.value('stateCode'))}</div>
                        <div>${valueOfSelect('#billing_country', data.value('countryCode'))}</div>
                        <div>${data.value('phone')}</div>
                        <h4><a href="javascript:void(0)" data-ppcp-axo-change-billing-address>Edit</a></h4>
                    </div>
                `;
            },
            fields: {
                firstName: {
                    'selector': '#billing_first_name_field',
                    'valuePath': 'billing.name.firstName',
                },
                lastName: {
                    'selector': '#billing_last_name_field',
                    'valuePath': 'billing.name.lastName',
                },
                street1: {
                    'selector': '#billing_address_1_field',
                    'valuePath': 'billing.address.addressLine1',
                },
                street2: {
                    'selector': '#billing_address_2_field',
                    'valuePath': null
                },
                postCode: {
                    'selector': '#billing_postcode_field',
                    'valuePath': 'billing.address.postalCode',
                },
                city: {
                    'selector': '#billing_city_field',
                    'valuePath': 'billing.address.adminArea2',
                },
                stateCode: {
                    'selector': '#billing_state_field',
                    'valuePath': 'billing.address.adminArea1',
                },
                countryCode: {
                    'selector': '#billing_country_field',
                    'valuePath': 'billing.address.countryCode',
                },
                company: {
                    'selector': '#billing_company_field',
                    'valuePath': null,
                },
                phone: {
                    'selector': '#billing_phone_field',
                    'valuePath': null,
                },
            }
        });
    }

    activate() {
        this.billingFormFields.activate();
    }

    deactivate() {
        this.billingFormFields.deactivate();
    }

    setData(data) {
        this.billingFormFields.setData(data);
    }

}

export default BillingView;
