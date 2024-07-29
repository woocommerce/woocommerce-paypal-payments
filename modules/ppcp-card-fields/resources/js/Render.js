import { cardFieldStyles } from './CardFieldsHelper';

let fieldsRendered = false;

export function renderFields( cardFields ) {
	if ( fieldsRendered === true ) {
		return;
	}
	fieldsRendered = true;

	const nameField = document.getElementById(
		'ppcp-credit-card-gateway-card-name'
	);
	if ( nameField ) {
		const styles = cardFieldStyles( nameField );
		cardFields
			.NameField( { style: { input: styles } } )
			.render( nameField.parentNode );
		nameField.hidden = true;
	}

	const numberField = document.getElementById(
		'ppcp-credit-card-gateway-card-number'
	);
	if ( numberField ) {
		const styles = cardFieldStyles( numberField );
		cardFields
			.NumberField( { style: { input: styles } } )
			.render( numberField.parentNode );
		numberField.hidden = true;
	}

	const expiryField = document.getElementById(
		'ppcp-credit-card-gateway-card-expiry'
	);
	if ( expiryField ) {
		const styles = cardFieldStyles( expiryField );
		cardFields
			.ExpiryField( { style: { input: styles } } )
			.render( expiryField.parentNode );
		expiryField.hidden = true;
	}

	const cvvField = document.getElementById(
		'ppcp-credit-card-gateway-card-cvc'
	);
	if ( cvvField ) {
		const styles = cardFieldStyles( cvvField );
		cardFields
			.CVVField( { style: { input: styles } } )
			.render( cvvField.parentNode );
		cvvField.hidden = true;
	}
}
