// EmailSubmissionManager.js

import { createElement, createRoot } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';

let emailInput = null;
let submitButtonReference = {
	container: null,
	root: null,
	unsubscribe: null,
};
let isLoading = false;

const getEmailInput = () => {
	if ( ! emailInput ) {
		emailInput = document.getElementById( 'email' );
	}
	return emailInput;
};

const EmailSubmitButton = ( { handleSubmit } ) => {
	const { isGuest, isAxoActive } = useSelect( ( select ) => ( {
		isGuest: select( STORE_NAME ).getIsGuest(),
		isAxoActive: select( STORE_NAME ).getIsAxoActive(),
	} ) );

	if ( ! isGuest || ! isAxoActive ) {
		return null;
	}

	return (
		<button
			type="button"
			onClick={ handleSubmit }
			className={ `wc-block-components-button wp-element-button ${
				isLoading ? 'is-loading' : ''
			}` }
			disabled={ isLoading }
		>
			<span
				className="wc-block-components-button__text"
				style={ { visibility: isLoading ? 'hidden' : 'visible' } }
			>
				Submit
			</span>
			{ isLoading && (
				<span
					className="wc-block-components-spinner"
					aria-hidden="true"
					style={ {
						position: 'absolute',
						top: '50%',
						left: '50%',
						transform: 'translate(-50%, -50%)',
					} }
				/>
			) }
		</button>
	);
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
		if ( isLoading || ! input.value ) {
			return;
		}

		isLoading = true;
		renderButton(); // Re-render button to show loading state

		try {
			await onEmailSubmit( input.value );
		} catch ( error ) {
			console.error( 'Error during email submission:', error );
			// Here you might want to show an error message to the user
		} finally {
			isLoading = false;
			renderButton(); // Re-render button to remove loading state
		}
	};

	const keydownHandler = ( event ) => {
		if ( event.key === 'Enter' ) {
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
	if ( input ) {
		input.removeEventListener( 'keydown', input.onkeydown );
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
};

export const isEmailFunctionalitySetup = () => {
	return !! submitButtonReference.root;
};

export default EmailSubmitButton;
