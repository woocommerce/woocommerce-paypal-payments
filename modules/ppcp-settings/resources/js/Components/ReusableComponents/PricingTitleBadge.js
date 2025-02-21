import { __, sprintf } from '@wordpress/i18n';

import { CommonHooks } from '../../data';
import { countryPriceInfo } from '../../utils/countryPriceInfo';
import { formatPrice } from '../../utils/formatPrice';
import TitleBadge, { TITLE_BADGE_INFO } from './TitleBadge';

const getFixedAmount = ( currency, priceList, itemFixedAmount ) => {
	if ( priceList[ currency ] ) {
		return formatPrice( itemFixedAmount, currency );
	}

	const [ defaultCurrency, defaultPrice ] = Object.entries( priceList )[ 0 ];
	const sum = defaultPrice + itemFixedAmount;
	return formatPrice( sum, defaultCurrency );
};

const PricingTitleBadge = ( { item } ) => {
	const { storeCountry, storeCurrency } = CommonHooks.useWooSettings();
	const infos = countryPriceInfo[ storeCountry ];
	const itemKey = item.split( ' ' )[ 0 ]; // Extract the first word, fastlane has more than one
	if ( ! infos || ! infos[ itemKey ] ) {
		return null;
	}

	const percentage =
		typeof infos[ itemKey ] === 'number'
			? infos[ itemKey ].toFixed( 2 )
			: infos[ itemKey ].percentage.toFixed( 2 );
	const itemFixedAmount =
		infos[ itemKey ].fixedFee ?? infos.fixedFee[ storeCurrency ] ?? 0;
	const fixedAmount = getFixedAmount(
		storeCurrency,
		infos.fixedFee,
		itemFixedAmount
	);

	const label = sprintf(
		__( 'from %1$s%% + %2$s', 'woocommerce-paypal-payments' ),
		percentage,
		fixedAmount
	);

	return (
		<TitleBadge
			type={ TITLE_BADGE_INFO }
			text={ `${ label }<sup>1</sup>` }
		/>
	);
};

export default PricingTitleBadge;
