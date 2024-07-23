import { cardFieldStyles } from '../../../ppcp-button/resources/js/modules/Helper/CardFieldsHelper';

class RenderCardFields {
	constructor( cardFields ) {
		this.cardFields = cardFields;
	}

	render() {
		const nameField = document.getElementById(
			'ppcp-credit-card-gateway-card-name'
		);
		if ( nameField ) {
			const styles = cardFieldStyles( nameField );
			this.cardFields
				.NameField( { style: { input: styles } } )
				.render( nameField.parentNode );
			nameField.hidden = true;
		}

		const numberField = document.getElementById(
			'ppcp-credit-card-gateway-card-number'
		);
		if ( numberField ) {
			const styles = cardFieldStyles( numberField );
			this.cardFields
				.NumberField( { style: { input: styles } } )
				.render( numberField.parentNode );
			numberField.hidden = true;
		}

		const expiryField = document.getElementById(
			'ppcp-credit-card-gateway-card-expiry'
		);
		if ( expiryField ) {
			const styles = cardFieldStyles( expiryField );
			this.cardFields
				.ExpiryField( { style: { input: styles } } )
				.render( expiryField.parentNode );
			expiryField.hidden = true;
		}

		const cvvField = document.getElementById(
			'ppcp-credit-card-gateway-card-cvc'
		);
		if ( cvvField ) {
			const styles = cardFieldStyles( cvvField );
			this.cardFields
				.CVVField( { style: { input: styles } } )
				.render( cvvField.parentNode );
			cvvField.hidden = true;
		}
	}
}

export default RenderCardFields;
