import { subscribe, select } from '@wordpress/data';

// Store name
const PAYMENT_STORE = 'wc/paypal/payment';

// Track original states of dependent methods
const originalStates = {};

/**
 * Initialize payment method dependency synchronization
 */
export const initPaymentDependencySync = () => {
	let previousPaymentState = null;
	let isProcessing = false;

	const unsubscribe = subscribe( () => {
		if ( isProcessing ) {
			return;
		}

		isProcessing = true;

		try {
			const paymentHooks = select( PAYMENT_STORE );
			if ( ! paymentHooks ) {
				isProcessing = false;
				return;
			}

			const methods = paymentHooks.persistentData();
			if ( ! methods ) {
				isProcessing = false;
				return;
			}

			if ( ! previousPaymentState ) {
				previousPaymentState = { ...methods };
				isProcessing = false;
				return;
			}

			const changedMethods = Object.keys( methods )
				.filter(
					( key ) =>
						key !== '__meta' &&
						methods[ key ] &&
						previousPaymentState[ key ]
				)
				.filter(
					( methodId ) =>
						methods[ methodId ].enabled !==
						previousPaymentState[ methodId ].enabled
				);

			if ( changedMethods.length > 0 ) {
				changedMethods.forEach( ( changedId ) => {
					const isNowEnabled = methods[ changedId ].enabled;

					const dependents = Object.entries( methods )
						.filter(
							( [ key, method ] ) =>
								key !== '__meta' &&
								method &&
								method.depends_on_payment_methods &&
								method.depends_on_payment_methods.includes(
									changedId
								)
						)
						.map( ( [ key ] ) => key );

					if ( dependents.length > 0 ) {
						if ( ! isNowEnabled ) {
							handleDisableDependents( dependents, methods );
						} else {
							handleRestoreDependents( dependents, methods );
						}
					}
				} );
			}

			previousPaymentState = { ...methods };
		} catch ( error ) {
			// Keep error handling without the console.error
		} finally {
			isProcessing = false;
		}
	} );

	return unsubscribe;
};

const handleDisableDependents = ( dependentIds, methods ) => {
	dependentIds.forEach( ( methodId ) => {
		if ( methods[ methodId ] ) {
			if ( ! ( methodId in originalStates ) ) {
				originalStates[ methodId ] = methods[ methodId ].enabled;
			}
			methods[ methodId ].enabled = false;
			methods[ methodId ].isDisabled = true;
		}
	} );
};

const handleRestoreDependents = ( dependentIds, methods ) => {
	dependentIds.forEach( ( methodId ) => {
		if (
			methods[ methodId ] &&
			methodId in originalStates &&
			checkAllDependenciesSatisfied( methodId, methods )
		) {
			methods[ methodId ].enabled = originalStates[ methodId ];
			methods[ methodId ].isDisabled = false;
			delete originalStates[ methodId ];
		}
	} );
};

const checkAllDependenciesSatisfied = ( methodId, methods ) => {
	const method = methods[ methodId ];
	if ( ! method || ! method.depends_on_payment_methods ) {
		return true;
	}

	return ! method.depends_on_payment_methods.some( ( parentId ) => {
		const parent = methods[ parentId ];
		return ! parent || parent.enabled === false;
	} );
};
