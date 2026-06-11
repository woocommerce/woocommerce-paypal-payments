import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSelectTab, TAB_IDS } from '@ppcp-settings/utils/tabSelector';

/**
 * Transforms camelCase section IDs to kebab-case with ppcp prefix
 *
 * @param {string} sectionId - The original section ID in camelCase
 * @return {string} The transformed section ID in kebab-case with ppcp prefix
 */
const transformSectionId = ( sectionId ) => {
	if ( ! sectionId ) {
		return sectionId;
	}

	// Convert camelCase to kebab-case.
	// This regex finds capital letters and replaces them with "-lowercase".
	const kebabCase = sectionId.replace( /([A-Z])/g, '-$1' ).toLowerCase();

	// Add ppcp- prefix if it doesn't already have it.
	const prefixed = kebabCase.startsWith( 'ppcp-' )
		? kebabCase
		: `ppcp-${ kebabCase }`;

	return prefixed;
};

/**
 * Creates a setting link element
 *
 * @param {Object} props             - Component props
 * @param {string} props.settingName - Display name for the setting
 * @param {string} props.sectionId   - Section ID to scroll to
 * @return {JSX.Element} The formatted link element
 */
const SettingLink = ( { settingName, sectionId } ) => {
	const selectTab = useSelectTab();

	return (
		<strong>
			<button
				type="button"
				className="ppcp--link-button"
				onClick={ () => {
					if ( sectionId ) {
						selectTab(
							TAB_IDS.SETTINGS,
							transformSectionId( sectionId ),
							true
						);
					}
				} }
			>
				{ settingName }
			</button>
		</strong>
	);
};

/**
 * Component to display a setting dependency message
 *
 * @param {Object} props               - Component props
 * @param {string} props.settingId     - ID of the required setting
 * @param {*}      props.requiredValue - Required value for the setting
 * @return {JSX.Element} The formatted message
 */
const SettingDependencyMessage = ( { settingId, requiredValue } ) => {
	// Setting names mapping.
	const settingNames = {
		savePaypalAndVenmo: 'Save PayPal and Venmo',
	};

	// Get a human-friendly setting name.
	const settingName = settingNames[ settingId ] || settingId;

	const settingLink = (
		<SettingLink settingName={ settingName } sectionId={ settingId } />
	);

	const templates = {
		true: __(
			'This payment method requires <settingLink /> to be enabled.',
			'woocommerce-paypal-payments'
		),
		false: __(
			'This payment method requires <settingLink /> to be disabled.',
			'woocommerce-paypal-payments'
		),
	};

	return typeof requiredValue === 'boolean'
		? createInterpolateElement( templates[ requiredValue ], {
				settingLink,
		  } )
		: createInterpolateElement(
				sprintf(
					/* translators: %s: required setting value */
					__(
						'This payment method requires <settingLink /> to be set to "%s".',
						'woocommerce-paypal-payments'
					),
					requiredValue
				),
				{ settingLink }
		  );
};

export default SettingDependencyMessage;
