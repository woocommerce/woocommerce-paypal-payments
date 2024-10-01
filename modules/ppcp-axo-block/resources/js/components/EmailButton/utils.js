import { createElement, createRoot } from '@wordpress/element';
import { log } from '../../../../../ppcp-axo/resources/js/Helper/Debug';
import { STORE_NAME } from '../../stores/axoStore';
import EmailButton from './EmailButton';

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
		log(
			'Email input element not found. Functionality not added.',
			'warn'
		);
		return;
	}

	const handleEmailSubmit = async () => {
		const isEmailSubmitted = wp.data
			.select( STORE_NAME )
			.getIsEmailSubmitted();

		if ( isEmailSubmitted || ! input.value ) {
			return;
		}

		wp.data.dispatch( STORE_NAME ).setIsEmailSubmitted( true );
		renderButton();

		try {
			await onEmailSubmit( input.value );
		} catch ( error ) {
			log( `Error during email submission: ${ error }`, 'error' );
		} finally {
			wp.data.dispatch( STORE_NAME ).setIsEmailSubmitted( false );
			renderButton();
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
				createElement( EmailButton, {
					handleSubmit: handleEmailSubmit,
				} )
			);
		}
	};

	renderButton();

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
