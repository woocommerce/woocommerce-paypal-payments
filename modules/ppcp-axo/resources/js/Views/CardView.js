import FormFieldGroup from '../Components/FormFieldGroup';

class CardView {
	constructor( selector, elements, manager ) {
		this.el = elements;
		this.manager = manager;

		this.group = new FormFieldGroup( {
			baseSelector: '.ppcp-axo-payment-container',
			contentSelector: selector,
			template: ( data ) => {
				const selectOtherPaymentMethod = () => {
					if ( ! this.manager.hideGatewaySelection ) {
						return '';
					}
					return `<p style="margin-top: 40px; text-align: center;"><a href="javascript:void(0)" ${ this.el.showGatewaySelectionLink.attributes }>Select other payment method</a></p>`;
				};

				if ( data.isEmpty() ) {
					return `
                        <div style="margin-bottom: 20px; text-align: center;">
                            ${ selectOtherPaymentMethod() }
                        </div>
                    `;
				}

				const expiry = data.value( 'expiry' ).split( '-' );

				const cardIcons = {
					VISA: 'visa-light.svg',
					MASTERCARD: 'mastercard-light.svg',
					AMEX: 'amex-light.svg',
					DISCOVER: 'discover-light.svg',
					DINERS: 'dinersclub-light.svg',
					JCB: 'jcb-light.svg',
					UNIONPAY: 'unionpay-light.svg',
				};

				return `
                    <div class="axo-checkout-wrapper">
                        <div class="axo-checkout-header-section">
                            <h3>Card Details</h3>
                            <a href="javascript:void(0)" ${
								this.el.changeCardLink.attributes
							}>Edit</a>
                        </div>
                        <div class="axo-checkout-card-preview styled-card">
                            <div class="ppcp-card-icon-wrapper">
                                <img
                                    class="ppcp-card-icon"
                                    title="${ data.value( 'brand' ) }"
                                    src="${
										window.wc_ppcp_axo.icons_directory
									}${ cardIcons[ data.value( 'brand' ) ] }"
                                    alt="${ data.value( 'brand' ) }"
                                >
                            </div>
                            <div class="axo-card-number">${
								data.value( 'lastDigits' )
									? '**** **** **** ' +
									  data.value( 'lastDigits' )
									: ''
							}</div>
                            <div class="axo-card-expiry">${ expiry[ 1 ] }/${
								expiry[ 0 ]
							}</div>
                            <div class="axo-card-owner">${ data.value(
								'name'
							) }</div>
                        </div>
                        ${ selectOtherPaymentMethod() }
                    </div>
                `;
			},
			fields: {
				brand: {
					valuePath: 'card.paymentSource.card.brand',
				},
				expiry: {
					valuePath: 'card.paymentSource.card.expiry',
				},
				lastDigits: {
					valuePath: 'card.paymentSource.card.lastDigits',
				},
				name: {
					valuePath: 'card.paymentSource.card.name',
				},
			},
		} );
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

	toSubmitData( data ) {
		const name = this.group.dataValue( 'name' );
		const { firstName, lastName } = this.splitName( name );

		data.billing_first_name = firstName;
		data.billing_last_name = lastName ? lastName : firstName;

		return this.group.toSubmitData( data );
	}

	splitName( fullName ) {
		const nameParts = fullName.trim().split( ' ' );
		const firstName = nameParts[ 0 ];
		const lastName =
			nameParts.length > 1 ? nameParts[ nameParts.length - 1 ] : '';

		return { firstName, lastName };
	}
}

export default CardView;
