import { cardFieldStyles } from './CardFieldsHelper';
import { hide } from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

function renderField( cardField, inputField ) {
	if ( ! inputField || inputField.hidden || ! cardField ) {
		return;
	}

	// Insert the PayPal card field after the original input field.
	const styles = cardFieldStyles( inputField );
	cardField( { style: { input: styles } } ).render( inputField.parentNode );

	// Hide the original input field.
	hide( inputField, true );
	inputField.hidden = true;
}

export function renderFields( cardFields ) {
	const nameField = document.getElementById(
		'ppcp-credit-card-gateway-card-name'
	);
	if ( nameField && nameField.hidden !== true ) {
		const styles = cardFieldStyles( nameField );
		cardFields
			.NameField( { style: { input: styles } } )
			.render( nameField.parentNode );
		hide( nameField, true );
		nameField.hidden = true;
	}

	const numberField = document.getElementById(
		'ppcp-credit-card-gateway-card-number'
	);
	if ( numberField && numberField.hidden !== true ) {
		const styles = cardFieldStyles( numberField );
		cardFields
			.NumberField( { style: { input: styles } } )
			.render( numberField.parentNode );
		hide( numberField, true );
		numberField.hidden = true;
	}

	const expiryField = document.getElementById(
		'ppcp-credit-card-gateway-card-expiry'
	);
	if ( expiryField && expiryField.hidden !== true ) {
		const styles = cardFieldStyles( expiryField );
		cardFields
			.ExpiryField( { style: { input: styles } } )
			.render( expiryField.parentNode );
		hide( expiryField, true );
		expiryField.hidden = true;
	}

	const cvvField = document.getElementById(
		'ppcp-credit-card-gateway-card-cvc'
	);
	if ( cvvField && cvvField.hidden !== true ) {
		const styles = cardFieldStyles( cvvField );
		cardFields
			.CVVField( { style: { input: styles } } )
			.render( cvvField.parentNode );
		hide( cvvField, true );
		cvvField.hidden = true;
	}
}
