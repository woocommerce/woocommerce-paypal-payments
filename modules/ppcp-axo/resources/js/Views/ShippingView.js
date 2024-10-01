import FormFieldGroup from '../Components/FormFieldGroup';

class ShippingView {
	constructor( selector, elements, states ) {
		this.el = elements;
		this.states = states;
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
                                <h3>Shipping</h3>
                                <a href="javascript:void(0)" ${ this.el.changeShippingAddressLink.attributes }>Edit</a>
                            </div>
                            <div>Please fill in your shipping details.</div>
                        </div>
                    `;
				}
				const countryCode = data.value( 'countryCode' );
				const stateCode = data.value( 'stateCode' );
				const stateName =
					this.states[ countryCode ] &&
					this.states[ countryCode ][ stateCode ]
						? this.states[ countryCode ][ stateCode ]
						: stateCode;

				if ( this.hasEmptyValues( data, stateName ) ) {
					return `
                        <div style="margin-bottom: 20px;">
                            <div class="axo-checkout-header-section">
                                <h3>Shipping</h3>
                                <a href="javascript:void(0)" ${ this.el.changeShippingAddressLink.attributes }>Edit</a>
                            </div>
                            <div>Please fill in your shipping details.</div>
                        </div>
                    `;
				}

				return `
                    <div style="margin-bottom: 20px;">
                        <div class="axo-checkout-header-section">
                            <h3>Shipping</h3>
                            <a href="javascript:void(0)" ${
								this.el.changeShippingAddressLink.attributes
							}>Edit</a>
                        </div>
                        <div>${ data.value( 'email' ) }</div>
                        <div>${ data.value( 'company' ) }</div>
                        <div>${ data.value( 'firstName' ) } ${ data.value(
							'lastName'
						) }</div>
                        <div>${ data.value( 'street1' ) }</div>
                        <div>${ data.value( 'street2' ) }</div>
                        <div>${ data.value(
							'city'
						) }, ${ stateName } ${ data.value( 'postCode' ) }</div>
                        <div>${ valueOfSelect(
							'#billing_country',
							countryCode
						) }</div>
                        <div>${ data.value( 'phone' ) }</div>
                    </div>
                `;
			},
			fields: {
				email: {
					valuePath: 'email',
				},
				firstName: {
					key: 'firstName',
					selector: '#shipping_first_name_field',
					valuePath: 'shipping.name.firstName',
					inputName: 'shipping_first_name',
				},
				lastName: {
					selector: '#shipping_last_name_field',
					valuePath: 'shipping.name.lastName',
					inputName: 'shipping_last_name',
				},
				street1: {
					selector: '#shipping_address_1_field',
					valuePath: 'shipping.address.addressLine1',
					inputName: 'shipping_address_1',
				},
				street2: {
					selector: '#shipping_address_2_field',
					valuePath: null,
					inputName: 'shipping_address_2',
				},
				postCode: {
					selector: '#shipping_postcode_field',
					valuePath: 'shipping.address.postalCode',
					inputName: 'shipping_postcode',
				},
				city: {
					selector: '#shipping_city_field',
					valuePath: 'shipping.address.adminArea2',
					inputName: 'shipping_city',
				},
				stateCode: {
					selector: '#shipping_state_field',
					valuePath: 'shipping.address.adminArea1',
					inputName: 'shipping_state',
				},
				countryCode: {
					selector: '#shipping_country_field',
					valuePath: 'shipping.address.countryCode',
					inputName: 'shipping_country',
				},
				company: {
					selector: '#shipping_company_field',
					valuePath: null,
					inputName: 'shipping_company',
				},
				shipDifferentAddress: {
					selector: '#ship-to-different-address',
					valuePath: null,
					inputName: 'ship_to_different_address',
					// Used by Woo to ensure correct location for taxes & shipping cost.
					valueCallback: () => true,
				},
				phone: {
					//'selector': '#billing_phone_field', // There is no shipping phone field.
					valueCallback( data ) {
						let phone = '';
						const cc = data?.shipping?.phoneNumber?.countryCode;
						const number =
							data?.shipping?.phoneNumber?.nationalNumber;

						if ( cc ) {
							phone = `+${ cc } `;
						}
						phone += number;
						return phone;
					},
				},
			},
		} );
	}

	hasEmptyValues( data, stateName ) {
		return (
			! data.value( 'email' ) ||
			! data.value( 'firstName' ) ||
			! data.value( 'lastName' ) ||
			! data.value( 'street1' ) ||
			! data.value( 'city' ) ||
			! stateName
		);
	}

	isActive() {
		return this.group.active;
	}

	activate() {
		this.group.activate();
		this.group.syncDataToForm();
	}

	deactivate() {
		this.group.deactivate();
	}

	refresh() {
		this.group.refresh();
	}

	setData( data ) {
		this.group.setData( data );
		this.group.syncDataToForm();
	}

	toSubmitData( data ) {
		return this.group.toSubmitData( data );
	}
}

export default ShippingView;
