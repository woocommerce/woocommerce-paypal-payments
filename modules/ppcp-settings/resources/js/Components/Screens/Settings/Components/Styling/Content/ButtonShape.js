import { __ } from '@wordpress/i18n';

import { StylingHooks } from '../../../../../../data';
import { RadiobuttonStylingSection } from '../Layout';

const SectionButtonShape = ( { location } ) => {
	const { shape, setShape, choices } = StylingHooks.useShapeProps( location );

	return (
		<RadiobuttonStylingSection
			title={ __( 'Shape', 'woocommerce-paypal-payments' ) }
			className="button-shape"
			options={ choices }
			selected={ shape }
			onChange={ setShape }
		/>
	);
};

export default SectionButtonShape;
