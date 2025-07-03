import { __ } from '@wordpress/i18n';

import { StylingHooks } from '../../../../../../data';
import { CheckboxStylingSection } from '../Layout';

const SectionTagline = ( { location } ) => {
	const { isAvailable, tagline, setTagline } =
		StylingHooks.useTaglineProps( location );

	if ( ! isAvailable ) {
		return null;
	}

	const checkbox = {
		value: 'active',
		label: __(
			'Show tagline below buttons',
			'woocommerce-paypal-payments'
		),
	};

	return (
		<CheckboxStylingSection
			name="tagline"
			separatorAndGap={ false }
			options={ [ checkbox ] }
			value={ tagline }
			onChange={ setTagline }
		/>
	);
};

export default SectionTagline;
