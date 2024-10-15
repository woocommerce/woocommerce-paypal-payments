import { cardFieldStyles } from './CardFieldsHelper';
import { hide } from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

function renderField( cardField, inputField ) {
	if ( ! inputField || inputField.hidden || ! cardField ) {
		return;
	}

	// Insert the PayPal card field after the original input field.
	const styles = cardFieldStyles( inputField );
    const fieldOptions = {style: { input: styles },};

    if ( inputField.getAttribute( 'placeholder' ) ) {
        fieldOptions.placeholder = inputField.getAttribute( 'placeholder' );
    }

    cardField( fieldOptions ).render( inputField.parentNode );

    // Hide the original input field.
    hide( inputField, true );
    inputField.hidden = true;
}

export function renderFields( cardFields ) {
	renderField(
		cardFields.NameField,
		document.getElementById( 'ppcp-credit-card-gateway-card-name' )
	);
	renderField(
		cardFields.NumberField,
		document.getElementById( 'ppcp-credit-card-gateway-card-number' )
	);
	renderField(
		cardFields.ExpiryField,
		document.getElementById( 'ppcp-credit-card-gateway-card-expiry' )
	);
	renderField(
		cardFields.CVVField,
		document.getElementById( 'ppcp-credit-card-gateway-card-cvc' )
	);
}
