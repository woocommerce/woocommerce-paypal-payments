import { __ } from '@wordpress/i18n';

import { StylingHooks } from '../../../../../../data';
import { SelectStylingSection } from '../Layout';

const SectionButtonColor = ( { location } ) => {
	const { color, setColor, choices } = StylingHooks.useColorProps( location );

	return (
		<SelectStylingSection
			title={ __( 'Button Color', 'woocommerce-paypal-payments' ) }
			className="button-color"
			options={ choices }
			value={ color }
			onChange={ setColor }
		/>
	);
};

export default SectionButtonColor;
