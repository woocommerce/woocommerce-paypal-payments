import { createElement, createRoot } from '@wordpress/element';
import { STORE_NAME } from '../stores/axoStore';
import { EmailSubmitButton } from '../components/EmailSubmitButton';

let emailInput = null;
let submitButtonReference = {
	container: null,
	root: null,
	unsubscribe: null,
};
let keydownHandler = null;

const getEmailInput = () => {
	if ( ! emailInput ) {
		emailInput = document.getElementById( 'email' );
	}
	return emailInput;
};

export const setupEmailFunctionality = ( onEmailSubmit ) => {
	const input = getEmailInput();
	if ( ! input ) {
		console.warn(
			'Email input element not found. Functionality not added.'
		);
		return;
	}

	const handleEmailSubmit = async () => {
		const isEmailSubmitted = wp.data
			.select( STORE_NAME )
			.isEmailSubmitted();

		if ( isEmailSubmitted || ! input.value ) {
			return;
		}

		wp.data.dispatch( STORE_NAME ).setIsEmailSubmitted( true );
		renderButton();

		try {
			await onEmailSubmit( input.value );
		} catch ( error ) {
			console.error( 'Error during email submission:', error );
			// Here you might want to show an error message to the user
		} finally {
			wp.data.dispatch( STORE_NAME ).setIsEmailSubmitted( false );
			renderButton(); // Re-render button to remove loading state
		}
	};

	keydownHandler = ( event ) => {
		const isAxoActive = wp.data.select( STORE_NAME ).getIsAxoActive();
		if ( event.key === 'Enter' && isAxoActive ) {
			event.preventDefault();
			handleEmailSubmit();
		}
	};

	input.addEventListener( 'keydown', keydownHandler );

	// Set up submit button
	if ( ! submitButtonReference.container ) {
		submitButtonReference.container = document.createElement( 'div' );
		submitButtonReference.container.setAttribute(
			'class',
			'wc-block-axo-email-submit-button-container'
		);

		input.parentNode.insertBefore(
			submitButtonReference.container,
			input.nextSibling
		);

		submitButtonReference.root = createRoot(
			submitButtonReference.container
		);
	}

	const renderButton = () => {
		if ( submitButtonReference.root ) {
			submitButtonReference.root.render(
				createElement( EmailSubmitButton, {
					handleSubmit: handleEmailSubmit,
				} )
			);
		} else {
			console.warn( 'Submit button root not found' );
		}
	};

	renderButton(); // Initial render

	// Subscribe to state changes
	submitButtonReference.unsubscribe = wp.data.subscribe( () => {
		renderButton();
	} );
};

export const removeEmailFunctionality = () => {
	const input = getEmailInput();
	if ( input && keydownHandler ) {
		input.removeEventListener( 'keydown', keydownHandler );
	}

	if ( submitButtonReference.root ) {
		submitButtonReference.root.unmount();
	}
	if ( submitButtonReference.unsubscribe ) {
		submitButtonReference.unsubscribe();
	}
	if (
		submitButtonReference.container &&
		submitButtonReference.container.parentNode
	) {
		submitButtonReference.container.parentNode.removeChild(
			submitButtonReference.container
		);
	}
	submitButtonReference = { container: null, root: null, unsubscribe: null };
	keydownHandler = null;
};

export const isEmailFunctionalitySetup = () => {
	return !! submitButtonReference.root;
};

export default EmailSubmitButton;
