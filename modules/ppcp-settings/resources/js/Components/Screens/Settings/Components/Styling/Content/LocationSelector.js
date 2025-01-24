import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { help } from '@wordpress/icons';

import { StylingHooks } from '../../../../../../data';
import {
	SelectStylingSection,
	StylingSection,
	CheckboxStylingSection,
} from '../Layout';

const LocationSelector = ( { location, setLocation } ) => {
	const { choices, details, isActive, setActive } =
		StylingHooks.useLocationProps( location );

	const activateCheckbox = {
		value: 'active',
		label: __(
			'Enable payment methods in this location',
			'woocommerce-paypal-payments'
		),
	};

	return (
		<>
			<StylingSection
				className="header-section"
				bigTitle={ true }
				title={ __( 'Button Styling', 'wooocommerce-paypal-payments' ) }
				description={ __(
					'Customize the appearance of the PayPal smart buttons on your website and choose which payment buttons to display.',
					'woocommerce-paypal-payments'
				) }
			/>
			<SelectStylingSection
				className="location-selector"
				title={ __( 'Location', 'woocommerce-paypal-payments' ) }
				separatorAndGap={ false }
				options={ choices }
				value={ location }
				onChange={ setLocation }
			>
				{ details.link && (
					<Button
						icon={ help }
						href={ details.link }
						target="_blank"
					/>
				) }
			</SelectStylingSection>
			<CheckboxStylingSection
				className="location-activation"
				separatorAndGap={ false }
				options={ [ activateCheckbox ] }
				value={ isActive }
				onChange={ setActive }
			/>
		</>
	);
};

export default LocationSelector;
