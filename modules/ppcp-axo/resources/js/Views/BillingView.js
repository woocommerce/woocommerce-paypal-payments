import FormFieldGroup from '../Components/FormFieldGroup';

class BillingView {
	constructor( selector, elements ) {
		this.el = elements;

		this.group = new FormFieldGroup( {
			baseSelector: '.woocommerce-checkout',
			contentSelector: selector,
			template: ( data ) => {
				const valueOfSelect = ( selectSelector, key ) => {
					if ( ! key ) {
						return '';
					}
					const selectElement =
						document.querySelector( selectSelector );

					if ( ! selectElement ) {
						return key;
					}

					const option = selectElement.querySelector(
						`option[value="${ key }"]`
					);
					return option ? option.textContent : key;
				};

				if ( data.isEmpty() ) {
					return `
                        <div style="margin-bottom: 20px;">
                            <div class="axo-checkout-header-section">
                                <h3>Billing</h3>
                                <a href="javascript:void(0)" ${ this.el.changeBillingAddressLink.attributes }>Edit</a>
                            </div>
                            <div>Please fill in your billing details.</div>
                        </div>
                    `;
				}
				return '';
			},
			fields: {
				email: {
					valuePath: 'email',
				},
				firstName: {
					selector: '#billing_first_name_field',
					valuePath: null,
				},
				lastName: {
					selector: '#billing_last_name_field',
					valuePath: null,
				},
				street1: {
					selector: '#billing_address_1_field',
					valuePath: 'billing.address.addressLine1',
				},
				street2: {
					selector: '#billing_address_2_field',
					valuePath: null,
				},
				postCode: {
					selector: '#billing_postcode_field',
					valuePath: 'billing.address.postalCode',
				},
				city: {
					selector: '#billing_city_field',
					valuePath: 'billing.address.adminArea2',
				},
				stateCode: {
					selector: '#billing_state_field',
					valuePath: 'billing.address.adminArea1',
				},
				countryCode: {
					selector: '#billing_country_field',
					valuePath: 'billing.address.countryCode',
				},
				company: {
					selector: '#billing_company_field',
					valuePath: null,
				},
				phone: {
					selector: '#billing_phone_field',
					valuePath: 'billing.phoneNumber',
				},
			},
		} );
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

	setData( data ) {
		this.group.setData( data );
	}

	inputValue( name ) {
		return this.group.inputValue( name );
	}

	fullName() {
		return `${ this.inputValue( 'firstName' ) } ${ this.inputValue(
			'lastName'
		) }`.trim();
	}

	toSubmitData( data ) {
		return this.group.toSubmitData( data );
	}
}

export default BillingView;
