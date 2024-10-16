import { useMemo } from '@wordpress/element';

const DEFAULT_ALLOWED_CARDS = [ 'VISA', 'MASTERCARD', 'AMEX', 'DISCOVER' ];

/**
 * Custom hook to determine the allowed card options based on configuration.
 *
 * @param {Object} axoConfig - The AXO configuration object.
 * @return {Array} The final list of allowed card options.
 */
const useCardOptions = ( axoConfig ) => {
	const merchantCountry = axoConfig.merchant_country || 'US';

	return useMemo( () => {
		const allowedCards = new Set(
			axoConfig.allowed_cards?.[ merchantCountry ] ||
				DEFAULT_ALLOWED_CARDS
		);

		// Create a Set of disabled cards, converting each to uppercase
		const disabledCards = new Set(
			( axoConfig.disable_cards || [] ).map( ( card ) =>
				card.toUpperCase()
			)
		);

		// Filter out disabled cards from the allowed cards
		const finalCardOptions = [ ...allowedCards ].filter(
			( card ) => ! disabledCards.has( card )
		);

		return finalCardOptions;
	}, [ axoConfig.allowed_cards, axoConfig.disable_cards, merchantCountry ] );
};

export default useCardOptions;
