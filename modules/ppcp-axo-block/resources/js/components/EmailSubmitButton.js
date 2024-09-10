import { createElement, createRoot } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../stores/axoStore';

const EmailSubmitButton = ( { onEmailSubmit } ) => {
	const { isGuest, isAxoActive } = useSelect( ( select ) => ( {
		isGuest: select( STORE_NAME ).getIsGuest(),
		isAxoActive: select( STORE_NAME ).getIsAxoActive(),
	} ) );

	const handleSubmit = () => {
		const emailInput = document.getElementById( 'email' );
		if ( emailInput && emailInput.value ) {
			onEmailSubmit( emailInput.value );
		}
	};

	if ( ! isGuest || ! isAxoActive ) {
		return null;
	}

	return (
		<button
			type="button"
			onClick={ handleSubmit }
			className="wc-block-components-button wp-element-button"
		>
			Submit
		</button>
	);
};

// Setup and removal functions
let submitButtonReference = {
	container: null,
	root: null,
};

export const setupEmailSubmitButton = ( onEmailSubmit ) => {
	const emailInput = document.getElementById( 'email' );

	if ( emailInput ) {
		if ( ! submitButtonReference.container ) {
			submitButtonReference.container = document.createElement( 'div' );
			submitButtonReference.container.setAttribute(
				'class',
				'wc-block-axo-email-submit-button-container'
			);

			emailInput.parentNode.insertBefore(
				submitButtonReference.container,
				emailInput.nextSibling
			);

			submitButtonReference.root = createRoot(
				submitButtonReference.container
			);
		}

		if ( submitButtonReference.root ) {
			const renderButton = () => {
				submitButtonReference.root.render(
					createElement( EmailSubmitButton, { onEmailSubmit } )
				);
			};

			renderButton(); // Initial render

			// Subscribe to state changes
			const unsubscribe = wp.data.subscribe( () => {
				renderButton();
			} );

			// Store the unsubscribe function for cleanup
			submitButtonReference.unsubscribe = unsubscribe;
		} else {
			console.warn( 'Submit button root not found' );
		}
	} else {
		console.warn( 'Email input not found' );
	}
};

export const removeEmailSubmitButton = () => {
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

export default EmailSubmitButton;
