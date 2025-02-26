import { useSelect } from '@wordpress/data';

/**
 * Gets the display name for a parent payment method
 *
 * @param {string} parentId   - ID of the parent payment method
 * @param {Object} methodsMap - Map of all payment methods by ID
 * @return {string} The display name to use for the parent method
 */
const getParentMethodName = ( parentId, methodsMap ) => {
	const parentMethod = methodsMap[ parentId ];
	return parentMethod
		? parentMethod.itemTitle || parentMethod.title || ''
		: '';
};

/**
 * Finds disabled parent dependencies for a method
 *
 * @param {Object} method     - The payment method to check
 * @param {Object} methodsMap - Map of all payment methods by ID
 * @return {Array} List of disabled parent IDs, empty if none
 */
const findDisabledParents = ( method, methodsMap ) => {
	if ( ! method.depends_on?.length && ! method._disabledByDependency ) {
		return [];
	}

	const parents = method.depends_on || [];

	return parents.filter( ( parentId ) => {
		const parent = methodsMap[ parentId ];
		return parent && ! parent.enabled;
	} );
};

/**
 * Custom hook to handle payment method dependencies
 *
 * @param {Array}  methods    - List of payment methods
 * @param {Object} methodsMap - Map of payment methods by ID
 * @return {Object} Dependency state object with methods that should be disabled
 */
const usePaymentDependencyState = ( methods, methodsMap ) => {
	return useSelect(
		( select ) => {
			const paymentStore = select( 'wc/paypal/payment' );
			if ( ! paymentStore ) {
				return {};
			}

			const result = {};

			methods.forEach( ( method ) => {
				const disabledParents = findDisabledParents(
					method,
					methodsMap
				);

				if ( disabledParents.length > 0 ) {
					const parentId = disabledParents[ 0 ];
					const parentName = getParentMethodName(
						parentId,
						methodsMap
					);

					result[ method.id ] = {
						isDisabled: true,
						parentId,
						parentName,
					};
				}
			} );

			return result;
		},
		[ methods, methodsMap ]
	);
};

export default usePaymentDependencyState;
