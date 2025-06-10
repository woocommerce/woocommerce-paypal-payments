import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import {
	ControlRadioGroup,
	ControlToggleButton,
	ControlTextInput,
	ControlSelect,
} from '../../../../../ReusableComponents/Controls';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import Accordion from '../../../../../ReusableComponents/AccordionSection';
import { SettingsHooks } from '../../../../../../data';
import SoftDescriptorInput from '../../../../../ReusableComponents/Controls/SoftdescriptorInput';

const PaypalSettings = () => {
	const {
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		subtotalAdjustment,
		setSubtotalAdjustment,
		brandName,
		setBrandName,
		softDescriptor,
		setSoftDescriptor,
		landingPage,
		setLandingPage,
		buttonLanguage,
		setButtonLanguage,
	} = SettingsHooks.useSettings();
	const siteData = useSelect( ( select ) => select( 'core' ).getSite(), [] );
	const siteTitle = siteData?.title;
	const buttonLanguageChoices = window.ppcpSettings.buttonLanguageChoices;
	return (
		<Accordion
			className="ppcp--paypal-settings"
			title={ __( 'PayPal Settings', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Modify the PayPal checkout experience.',
				'woocommerce-paypal-payments'
			) }
		>
			<SettingsBlock
				title={ __(
					'Subtotal mismatch fallback',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Due to differences in how WooCommerce and PayPal calculates taxes, some transactions may fail due to a rounding error. This settings determines the fallback behavior.',
					'woocommerce-paypal-payments'
				) }
			>
				<ControlRadioGroup
					options={ subtotalAdjustmentChoices }
					value={ subtotalAdjustment }
					onChange={ setSubtotalAdjustment }
				/>
			</SettingsBlock>

			<SettingsBlock>
				<ControlToggleButton
					label={ __(
						'Instant payments only',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'If enabled, PayPal will not allow buyers to use funding sources that take additional time to complete, such as eChecks.',
						'woocommerce-paypal-payments'
					) }
					value={ savePaypalAndVenmo }
					onChange={ setSavePaypalAndVenmo }
				/>
			</SettingsBlock>

			<SettingsBlock
				title={ __( 'Brand name', 'woocommerce-paypal-payments' ) }
				description={ __(
					'What business name to show to your buyers during checkout and on receipts.',
					'woocommerce-paypal-payments'
				) }
			>
				<ControlTextInput
					value={ brandName }
					onChange={ setBrandName }
					placeholder={
						siteTitle ||
						__( 'Brand name', 'woocommerce-paypal-payments' )
					}
				/>
			</SettingsBlock>

			<SettingsBlock
				title={ __( 'Soft Descriptor', 'woocommerce-paypal-payments' ) }
				description={ __(
					"The dynamic text used to construct the statement descriptor that appears on a payer's card statement. Applies to PayPal and Credit Card transactions. Max value of 22 characters.",
					'woocommerce-paypal-payments'
				) }
			>
				<SoftDescriptorInput
					value={ softDescriptor }
					onChange={ setSoftDescriptor }
					placeholder={ __(
						'Soft Descriptor',
						'woocommerce-paypal-payments'
					) }
				/>
			</SettingsBlock>

			<SettingsBlock
				title={ __(
					'PayPal landing page',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Determine which experience a buyer sees when they click the PayPal button.',
					'woocommerce-paypal-payments'
				) }
			>
				<ControlRadioGroup
					options={ landingPageChoices }
					value={ landingPage }
					onChange={ setLandingPage }
				/>
			</SettingsBlock>

			<SettingsBlock
				title={ __( 'Button Language', 'woocommerce-paypal-payments' ) }
				description={ __(
					"If left blank, PayPal and other buttons will present in the user's detected language. Enter a language here to force all buttons to display in that language.",
					'woocommerce-paypal-payments'
				) }
			>
				<ControlSelect
					options={ buttonLanguageChoices }
					value={ buttonLanguage }
					onChange={ setButtonLanguage }
					placeholder={ __(
						'Browser language',
						'woocommerce-paypal-payments'
					) }
				/>
			</SettingsBlock>
		</Accordion>
	);
};

const subtotalAdjustmentChoices = [
	{
		value: 'correction',
		label: __( 'Add a correction', 'woocommerce-paypal-payments' ),
		description: __(
			'Adds an additional line item with the missing amount.',
			'woocommerce-paypal-payments'
		),
	},
	{
		value: 'no_details',
		label: __( 'Do not send line items', 'woocommerce-paypal-payments' ),
		description: __(
			'Resubmit the transaction without line item details.',
			'woocommerce-paypal-payments'
		),
	},
];

const landingPageChoices = [
	{
		value: 'any',
		label: __( 'No preference', 'woocommerce-paypal-payments' ),
		description: __(
			'Shows the buyer the PayPal login for a recognized PayPal buyer.',
			'woocommerce-paypal-payments'
		),
	},
	{
		value: 'login',
		label: __( 'Login page', 'woocommerce-paypal-payments' ),
		description: __(
			'Always show the buyer the PayPal login screen.',
			'woocommerce-paypal-payments'
		),
	},
	{
		value: 'guest_checkout',
		label: __( 'Guest checkout page', 'woocommerce-paypal-payments' ),
		description: __(
			'Always show the buyer the guest checkout fields first.',
			'woocommerce-paypal-payments'
		),
	},
];

export default PaypalSettings;
