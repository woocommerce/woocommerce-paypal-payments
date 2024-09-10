let emailInput = null;
let currentHandler = null;

const getEmailInput = () => {
	if ( ! emailInput ) {
		emailInput = document.getElementById( 'email' );
	}
	return emailInput;
};

export const setupEmailEvent = ( onEmailSubmit ) => {
	const input = getEmailInput();
	if ( ! input ) {
		console.warn(
			'Email input element not found. Event listener not added.'
		);
		return;
	}

	if ( currentHandler ) {
		console.warn(
			'Email event listener already exists. Removing old listener before adding new one.'
		);
		removeEmailEvent();
	}

	const handleEmailInput = async ( event ) => {
		const email = event.target.value;
		if ( email ) {
			await onEmailSubmit( email );
		}
	};

	input.addEventListener( 'keyup', handleEmailInput );
	currentHandler = handleEmailInput;
	console.log( 'Email event listener added' );
};

export const removeEmailEvent = () => {
	const input = getEmailInput();
	if ( input && currentHandler ) {
		input.removeEventListener( 'keyup', currentHandler );
		currentHandler = null;
		console.log( 'Email event listener removed' );
	} else {
		console.log(
			'Could not remove email event listener. Input:',
			input,
			'Handler:',
			currentHandler
		);
	}
};

export const isEmailEventSetup = () => {
	return !! currentHandler;
};
