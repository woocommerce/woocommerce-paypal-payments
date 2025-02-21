import { subscribe, select, dispatch } from '@wordpress/data';

const TODO_TRIGGERS = {
	'ppcp-applepay': 'enable_apple_pay',
	'ppcp-googlepay': 'enable_google_pay',
	'ppcp-axo-gateway': 'enable_fastlane',
	'ppcp-card-button-gateway': 'enable_credit_debit_cards',
};

/**
 * Initialize todo synchronization
 */
export const initTodoSync = () => {
	let previousPaymentState = null;
	let isProcessing = false;

	subscribe( () => {
		if ( isProcessing ) {
			return;
		}

		isProcessing = true;

		try {
			const paymentState = select( 'wc/paypal/payment' ).persistentData();
			const completedTodos =
				select( 'wc/paypal/todos' ).getCompletedTodos();

			// Skip if states haven't been initialized yet
			if ( ! paymentState || ! previousPaymentState ) {
				previousPaymentState = paymentState;
				isProcessing = false;
				return;
			}

			// Track which todos should be marked as completed
			let newCompletedTodos = [ ...( completedTodos || [] ) ];

			Object.entries( TODO_TRIGGERS ).forEach(
				( [ paymentMethod, todoId ] ) => {
					const wasEnabled =
						previousPaymentState[ paymentMethod ]?.enabled;
					const isEnabled = paymentState[ paymentMethod ]?.enabled;

					if ( wasEnabled !== isEnabled ) {
						if ( isEnabled ) {
							// Add to completed todos if not already there
							if ( ! newCompletedTodos.includes( todoId ) ) {
								newCompletedTodos.push( todoId );
							}
						} else {
							// Remove from completed todos
							newCompletedTodos = newCompletedTodos.filter(
								( id ) => id !== todoId
							);
						}
					}
				}
			);

			// Only dispatch if there are changes
			if (
				newCompletedTodos.length !== completedTodos.length ||
				newCompletedTodos.some(
					( id ) => ! completedTodos.includes( id )
				)
			) {
				dispatch( 'wc/paypal/todos' ).setCompletedTodos(
					newCompletedTodos
				);
			}

			previousPaymentState = { ...paymentState };
		} catch ( error ) {
			console.error( 'Error in todo sync:', error );
		} finally {
			isProcessing = false;
		}
	} );
};
