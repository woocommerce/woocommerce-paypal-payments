import { __ } from '@wordpress/i18n';

import { StylingHooks } from '../../../../../../data';
import { RadiobuttonStylingSection } from '../Layout';
import { Tagline } from './index';

const SectionButtonLayout = ( { location } ) => {
	const { isAvailable, layout, setLayout, choices } =
		StylingHooks.useLayoutProps( location );

	if ( ! isAvailable ) {
		return null;
	}

	return (
		<>
			<RadiobuttonStylingSection
				className="button-layout"
				title={ __( 'Button Layout', 'woocommerce-paypal-payments' ) }
				options={ choices }
				selected={ layout }
				onChange={ setLayout }
			/>
			<Tagline location={ location } />
		</>
	);
};

export default SectionButtonLayout;
