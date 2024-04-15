import FormFieldGroup from "../Components/FormFieldGroup";

class ShippingView {

    constructor(selector, elements) {
        this.el = elements;

        this.group = new FormFieldGroup({
            baseSelector: '.woocommerce-checkout',
            contentSelector: selector,
            template: (data) => {
                const valueOfSelect = (selectSelector, key) => {
                    if (!key) {
                        return '';
                    }
                    const selectElement = document.querySelector(selectSelector);

                    if (!selectElement) {
                        return key;
                    }

                    const option = selectElement.querySelector(`option[value="${key}"]`);
                    return option ? option.textContent : key;
                }

                if (data.isEmpty()) {
                    return `
                        <div style="margin-bottom: 20px;">
                            <h3>Shipping <a href="javascript:void(0)" ${this.el.changeShippingAddressLink.attributes} style="margin-left: 20px;">Edit</a></h3>
                            <div>Please fill in your shipping details.</div>
                        </div>
                    `;
                }
                return `
                    <div style="margin-bottom: 20px;">
                        <h3>Shipping <a href="javascript:void(0)" ${this.el.changeShippingAddressLink.attributes} style="margin-left: 20px;">Edit</a></h3>
                        <div>${data.value('company')}</div>
                        <div>${data.value('firstName')} ${data.value('lastName')}</div>
                        <div>${data.value('street1')}</div>
                        <div>${data.value('street2')}</div>
                        <div>${data.value('postCode')} ${data.value('city')}</div>
                        <div>${valueOfSelect('#shipping_state', data.value('stateCode'))}</div>
                        <div>${valueOfSelect('#shipping_country', data.value('countryCode'))}</div>
                        <div>${data.value('phone')}</div>
                    </div>
                `;
            },
            fields: {
                firstName: {
                    'key': 'firstName',
                    'selector': '#shipping_first_name_field',
                    'valuePath': 'shipping.name.firstName',
                },
                lastName: {
                    'selector': '#shipping_last_name_field',
                    'valuePath': 'shipping.name.lastName',
                },
                street1: {
                    'selector': '#shipping_address_1_field',
                    'valuePath': 'shipping.address.addressLine1',
                },
                street2: {
                    'selector': '#shipping_address_2_field',
                    'valuePath': null
                },
                postCode: {
                    'selector': '#shipping_postcode_field',
                    'valuePath': 'shipping.address.postalCode',
                },
                city: {
                    'selector': '#shipping_city_field',
                    'valuePath': 'shipping.address.adminArea2',
                },
                stateCode: {
                    'selector': '#shipping_state_field',
                    'valuePath': 'shipping.address.adminArea1',
                },
                countryCode: {
                    'selector': '#shipping_country_field',
                    'valuePath': 'shipping.address.countryCode',
                },
                company: {
                    'selector': '#shipping_company_field',
                    'valuePath': null,
                },
                shipDifferentAddress: {
                    'selector': '#ship-to-different-address',
                    'valuePath': null,
                },
                phone: {
                    //'selector': '#billing_phone_field', // There is no shipping phone field.
                    'valueCallback': function (data) {
                        let phone = '';
                        const cc = data?.shipping?.phoneNumber?.countryCode;
                        const number = data?.shipping?.phoneNumber?.nationalNumber;

                        if (cc) {
                            phone = `+${cc} `;
                        }
                        phone += number;
                        return phone;
                    }
                }
            }
        });
    }

    isActive() {
        return this.group.active;
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
        return this.group.toSubmitData(data);
    }

}

export default ShippingView;
