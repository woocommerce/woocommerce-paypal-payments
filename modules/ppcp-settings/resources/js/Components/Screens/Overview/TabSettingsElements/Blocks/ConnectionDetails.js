import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import {
	ControlTextInput,
	ControlRadioGroup,
} from '../../../../ReusableComponents/Controls';
import Accordion from '../../../../ReusableComponents/AccordionSection';
import SettingsBlock from '../../../../ReusableComponents/SettingsBlock';

const ConnectionDetails = ( { settings, updateFormValue } ) => {
	const isSandbox = settings.sandboxConnected;

	const modeConfig = isSandbox
		? productionData( { settings, updateFormValue } )
		: sandboxData( { settings, updateFormValue } );

	const modeKey = isSandbox ? 'productionMode' : 'sandboxMode';

	return (
		<Accordion
			title={ modeConfig.title }
			description={ modeConfig.description }
		>
			<SettingsBlock
				title={ modeConfig.connectTitle }
				description={ modeConfig.connectDescription }
			>
				<ControlRadioGroup
					options={ modeConfig.options }
					value={ settings[ modeKey ] }
					onChange={ updateFormValue }
				/>
			</SettingsBlock>
		</Accordion>
	);
};

export default ConnectionDetails;

// Helper logic, refactor this when possible.

/**
 * Generates options for the environment mode settings.
 *
 * @param {Object}   config          - Configuration for the mode.
 * @param {Object}   settings        - Current settings.
 * @param {Function} updateFormValue - Callback to update settings.
 * @return {Array} Options array.
 */
const generateOptions = ( config, settings, updateFormValue ) => [
	{
		id: `${ config.mode }_mode`,
		value: `${ config.mode }_mode`,
		label: config.labelTitle,
		description: config.labelDescription,
		additionalContent: (
			<Button
				variant="primary"
				onClick={ () => {
					updateFormValue( `${ config.mode }Connected`, true );
					if ( config.mode === 'production' ) {
						global.ppcpSettings.startOnboarding();
					}
				} }
			>
				{ config.buttonText }
			</Button>
		),
	},
	{
		id: 'manual_connect',
		value: 'manual_connect',
		label: __( 'Manual Connect', 'woocommerce-paypal-payments' ),
		description: sprintf(
			__(
				'For advanced users: Connect a custom PayPal REST app for full control over your integration. For more information on creating a PayPal REST application, <a target="_blank" href="%s">click here</a>.',
				'woocommerce-paypal-payments'
			),
			'#'
		),
		additionalContent: (
			<>
				<ControlTextInput
					title={ config.clientIdTitle }
					// Input field props.
					value={ settings[ `${ config.mode }ClientId` ] }
					onChange={ updateFormValue }
					placeholder={ __(
						'Enter Client ID',
						'woocommerce-paypal-payments'
					) }
				/>
				<ControlTextInput
					title={ config.secretKeyTitle }
					// Input field props.
					value={ settings[ `${ config.mode }SecretKey` ] }
					onChange={ updateFormValue }
					placeholder={ __(
						'Enter Secret Key',
						'woocommerce-paypal-payments'
					) }
				/>
				<Button
					variant="primary"
					onClick={ () =>
						updateFormValue(
							`${ config.mode }ManuallyConnected`,
							true
						)
					}
				>
					{ __( 'Connect Account', 'woocommerce-paypal-payments' ) }
				</Button>
			</>
		),
	},
];

/**
 * Generates data for a given mode (sandbox or production).
 *
 * @param {Object}   config          - Configuration for the mode.
 * @param {Object}   settings        - Current settings.
 * @param {Function} updateFormValue - Callback to update settings.
 * @return {Object} Mode configuration.
 */
const generateModeData = ( config, settings, updateFormValue ) => ( {
	title: config.title,
	description: config.description,
	connectTitle: __(
		`Connect ${ config.label } Account`, // TODO: Avoid variables inside __() translation literal.
		'woocommerce-paypal-payments'
	),
	connectDescription: config.connectDescription,
	options: generateOptions( config, settings, updateFormValue ),
} );

const sandboxData = ( { settings = {}, updateFormValue = () => {} } ) =>
	generateModeData(
		{
			mode: 'sandbox',
			label: 'Sandbox',
			title: __( 'Sandbox', 'woocommerce-paypal-payments' ),
			description: __(
				"Test your site in PayPal's Sandbox environment.",
				'woocommerce-paypal-payments'
			),
			connectDescription: __(
				'Connect a PayPal Sandbox account in order to test your website. Transactions made will not result in actual money movement. Do not fulfil orders completed in Sandbox mode.',
				'woocommerce-paypal-payments'
			),
			labelTitle: __( 'Sandbox Mode', 'woocommerce-paypal-payments' ),
			labelDescription: __(
				'Activate Sandbox mode to safely test PayPal with sample data. Once your store is ready to go live, you can easily switch to your production account.',
				'woocommerce-paypal-payments'
			),
			buttonText: __(
				'Connect Sandbox Account',
				'woocommerce-paypal-payments'
			),
			clientIdTitle: __(
				'Sandbox Client ID',
				'woocommerce-paypal-payments'
			),
			secretKeyTitle: __(
				'Sandbox Secret Key',
				'woocommerce-paypal-payments'
			),
		},
		settings,
		updateFormValue
	);

const productionData = ( { settings = {}, updateFormValue = () => {} } ) =>
	generateModeData(
		{
			mode: 'production',
			label: 'Live',
			title: __( 'Live Payments', 'woocommerce-paypal-payments' ),
			description: __(
				'Your site is currently configured in Sandbox mode to test payments. When you are ready, launch your site and receive live payments via PayPal.',
				'woocommerce-paypal-payments'
			),
			connectDescription: __(
				'Connect a live PayPal account to launch your site and receive live payments via PayPal. PayPal will guide you through the setup process.',
				'woocommerce-paypal-payments'
			),
			labelTitle: __( 'Production Mode', 'woocommerce-paypal-payments' ),
			labelDescription: __(
				'Activate Production mode to connect your live account and receive live payments via PayPal. Stay connected in Sandbox mode to continue testing payments before going live.',
				'woocommerce-paypal-payments'
			),
			buttonText: __(
				'Set up and connect live PayPal Account',
				'woocommerce-paypal-payments'
			),
			clientIdTitle: __(
				'Live Account Client ID',
				'woocommerce-paypal-payments'
			),
			secretKeyTitle: __(
				'Live Account Secret Key',
				'woocommerce-paypal-payments'
			),
		},
		settings,
		updateFormValue
	);
