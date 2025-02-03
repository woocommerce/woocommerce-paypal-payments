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
			const todosState = select( 'wc/paypal/todos' ).getTodos();

			// Skip if states haven't been initialized yet
			if ( ! paymentState || ! todosState || ! previousPaymentState ) {
				previousPaymentState = paymentState;
				return;
			}

			Object.entries( TODO_TRIGGERS ).forEach(
				( [ paymentMethod, todoId ] ) => {
					const wasEnabled =
						previousPaymentState[ paymentMethod ]?.enabled;
					const isEnabled = paymentState[ paymentMethod ]?.enabled;

					if ( wasEnabled !== isEnabled ) {
						const todoToUpdate = todosState.find(
							( todo ) => todo.id === todoId
						);

						if ( todoToUpdate ) {
							const updatedTodos = todosState.map( ( todo ) =>
								todo.id === todoId
									? { ...todo, isCompleted: isEnabled }
									: todo
							);

							dispatch( 'wc/paypal/todos' ).setTodos(
								updatedTodos
							);
						}
					}
				}
			);

			previousPaymentState = paymentState;
		} catch ( error ) {
			console.error( 'Error in todo sync:', error );
		} finally {
			isProcessing = false;
		}
	} );
};
