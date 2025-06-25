/**
 * Custom hook to handle payment-method-based dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Gets the display name for a parent payment method
 *
 * @param {string} methodId   - ID of the payment method
 * @param {Object} methodsMap - Map of all payment methods by ID
 * @return {string} The display name of the method
 */
const getMethodName = ( methodId, methodsMap ) => {
	const method = methodsMap[ methodId ];
	return method ? method.itemTitle || method.title || '' : '';
};

/**
 * Finds disabled parent dependencies for a method
 *
 * @param {Object} method     - The payment method to check
 * @param {Object} methodsMap - Map of all payment methods by ID
 * @return {Array} List of disabled parent IDs, empty if none
 */
const findDisabledParents = ( method, methodsMap ) => {
	const dependencies = method.depends_on_payment_methods;

	if ( ! dependencies || ! Array.isArray( dependencies ) ) {
		return [];
	}

	return dependencies.filter( ( parentId ) => {
		const parent = methodsMap[ parentId ];
		return parent && ! parent.enabled;
	} );
};

/**
 * Checks if method should be disabled due to value dependencies
 *
 * @param {Object} method     - The payment method to check
 * @param {Object} methodsMap - Map of all payment methods by ID
 * @return {Object|null} Value dependency info if should be disabled, null otherwise
 */
const checkValueDependencies = ( method, methodsMap ) => {
	const valueDependencies = method.depends_on_payment_methods_values;

	if ( ! valueDependencies ) {
		return null;
	}

	// Check each dependency against the actual state of the dependent method.
	for ( const [ dependentId, requiredValue ] of Object.entries(
		valueDependencies
	) ) {
		const dependent = methodsMap[ dependentId ];

		if ( ! dependent ) {
			continue;
		}

		// Example: card-button-gateway depends on credit-card-gateway being FALSE.
		// So if credit-card-gateway is TRUE (enabled), card-button-gateway should be disabled.

		// If the dependency requires a method to be false but it's enabled (or vice versa).
		if (
			typeof requiredValue === 'boolean' &&
			dependent.enabled !== requiredValue
		) {
			// This dependency is violated - the dependent method is in the wrong state.
			return {
				dependentId,
				dependentName: getMethodName( dependentId, methodsMap ),
				requiredValue,
			};
		}
	}

	return null;
};

/**
 * Hook to evaluate all payment method dependencies
 *
 * @param {Array}  methods    - List of payment methods
 * @param {Object} methodsMap - Map of payment methods by ID
 * @return {Object} Dependency state object keyed by method ID
 */
const usePaymentDependencyState = ( methods, methodsMap ) => {
	return useSelect( () => {
		const result = {};

		if ( methods && methodsMap && Object.keys( methodsMap ).length > 0 ) {
			methods.forEach( ( method ) => {
				if ( method && method.id ) {
					// Check regular parent-child dependencies first.
					const disabledParents = findDisabledParents(
						method,
						methodsMap
					);

					if ( disabledParents.length > 0 ) {
						const parentId = disabledParents[ 0 ];
						result[ method.id ] = {
							type: 'parent',
							isDisabled: true,
							parentId,
							parentName: getMethodName( parentId, methodsMap ),
						};
						return; // Skip other checks if already disabled.
					}

					// Check value dependencies.
					const valueDependency = checkValueDependencies(
						method,
						methodsMap
					);

					if ( valueDependency ) {
						result[ method.id ] = {
							type: 'value',
							isDisabled: true,
							dependentId: valueDependency.dependentId,
							dependentName: valueDependency.dependentName,
							requiredValue: valueDependency.requiredValue,
						};
					}
				}
			} );
		}
		return result;
	}, [ methods, methodsMap ] );
};

export default usePaymentDependencyState;
