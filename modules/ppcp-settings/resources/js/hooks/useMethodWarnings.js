/**
 * Custom hook to evaluate payment method warnings based on reactive store data.
 *
 * Warning messages can be either:
 * - A plain string: always displayed (supports HTML content).
 * - An object with { message, visibleWhen }: displayed only when the visibleWhen
 *   condition evaluates to true. Reactively shown or hidden as store data changes.
 *
 * The visibleWhen object follows the same pattern as setting dependencies:
 * - store:     Which store to read from ('payment' or 'settings').
 * - field:     The persistent field name to evaluate (single-field conditions).
 * - fields:    Map of field names to labels (for 'any_empty' condition).
 * - condition: The evaluation rule ('not_empty', 'empty', 'equals', 'not_equals', 'any_empty').
 * - value:     (Optional) Expected value for 'equals'/'not_equals' conditions.
 *
 * The 'any_empty' condition checks multiple fields and builds a dynamic message
 * by replacing %s in the message template with a comma-separated list of labels
 * for fields that are currently empty.
 */
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { _x, sprintf } from '@wordpress/i18n';

const STORE_MAP = {
	payment: 'wc/paypal/payment',
	settings: 'wc/paypal/settings',
};

const isEmpty = ( fieldValue ) =>
	typeof fieldValue === 'string'
		? fieldValue.trim().length === 0
		: ! fieldValue;

const CONDITION_EVALUATORS = {
	not_empty: ( fieldValue ) => ! isEmpty( fieldValue ),
	empty: ( fieldValue ) => isEmpty( fieldValue ),
	equals: ( fieldValue, expected ) => fieldValue === expected,
	not_equals: ( fieldValue, expected ) => fieldValue !== expected,
};

/**
 * Joins a list of labels into a human-readable string.
 *
 * @param {string[]} labels Array of label strings.
 * @return {string} Joined string (e.g. "A, B, and C").
 */
const joinLabels = ( labels ) => {
	if ( labels.length <= 1 ) {
		return labels[ 0 ] || '';
	}
	if ( labels.length === 2 ) {
		return labels.join(
			' ' +
				/* translators: joins two items, e.g. "Brand name and Logo URL" */
				_x(
					'and',
					'joining two items',
					'woocommerce-paypal-payments'
				) +
				' '
		);
	}
	const last = labels[ labels.length - 1 ];
	const rest = labels.slice( 0, -1 );
	return (
		rest.join( ', ' ) +
		', ' +
		/* translators: before the last item in a list, e.g. "A, B, and C" */
		_x( 'and', 'before last list item', 'woocommerce-paypal-payments' ) +
		' ' +
		last
	);
};

/**
 * Evaluates an 'any_empty' condition against store data.
 *
 * @param {Object} visibleWhen The visibleWhen config with fields map.
 * @param {Object} store       The persistent store data for the referenced store.
 * @return {string|null} The built label string if any fields are empty, or null.
 */
const evaluateAnyEmpty = ( visibleWhen, store ) => {
	const { fields } = visibleWhen;
	if ( ! fields ) {
		return null;
	}

	const emptyLabels = Object.entries( fields )
		.filter( ( [ fieldName ] ) => isEmpty( store?.[ fieldName ] ) )
		.map( ( [ , label ] ) => label );

	return emptyLabels.length > 0 ? joinLabels( emptyLabels ) : null;
};

/**
 * Evaluates payment method warnings based on reactive store data.
 *
 * @param {Array} methods Array of payment method objects.
 * @return {Array} Methods with warnings evaluated and normalized to { key: string }.
 */
const useMethodWarnings = ( methods ) => {
	const referencedStores = useMemo( () => {
		const stores = new Set();
		methods?.forEach( ( method ) => {
			if ( ! method?.warningMessages ) {
				return;
			}
			Object.values( method.warningMessages ).forEach( ( warning ) => {
				if (
					typeof warning === 'object' &&
					warning?.visibleWhen?.store
				) {
					stores.add( warning.visibleWhen.store );
				}
			} );
		} );
		return [ ...stores ];
	}, [ methods ] );

	const storeData = useSelect(
		( select ) => {
			const data = {};
			referencedStores.forEach( ( storeKey ) => {
				const storeName = STORE_MAP[ storeKey ];
				if ( ! storeName ) {
					return;
				}
				const store = select( storeName );
				if ( store?.persistentData ) {
					data[ storeKey ] = store.persistentData();
				}
			} );
			return data;
		},
		[ referencedStores ]
	);

	return useMemo(
		() =>
			methods?.map( ( method ) => {
				if ( ! method?.warningMessages ) {
					return method;
				}

				const evaluatedWarnings = {};
				Object.entries( method.warningMessages ).forEach(
					( [ key, warning ] ) => {
						if ( typeof warning === 'string' ) {
							if ( warning ) {
								evaluatedWarnings[ key ] = warning;
							}
							return;
						}

						const { message, visibleWhen } = warning;
						if ( ! visibleWhen ) {
							evaluatedWarnings[ key ] = message;
							return;
						}

						const { store, condition } = visibleWhen;

						// Multi-field condition: visible when any field is empty.
						if ( condition === 'any_empty' ) {
							const labelText = evaluateAnyEmpty(
								visibleWhen,
								storeData[ store ]
							);
							if ( labelText ) {
								evaluatedWarnings[ key ] = sprintf(
									message,
									labelText
								);
							}
							return;
						}

						// Single-field conditions.
						const { field, value } = visibleWhen;
						const evaluator = CONDITION_EVALUATORS[ condition ];
						const fieldValue = storeData[ store ]?.[ field ];

						if ( evaluator && evaluator( fieldValue, value ) ) {
							evaluatedWarnings[ key ] = message;
						}
					}
				);

				return { ...method, warningMessages: evaluatedWarnings };
			} ) || [],
		[ methods, storeData ]
	);
};

export default useMethodWarnings;
