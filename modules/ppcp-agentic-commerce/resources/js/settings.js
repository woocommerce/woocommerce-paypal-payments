import { __ } from '@wordpress/i18n';
import {
	registerSetting,
	createExtensionStore,
	SLOTS,
} from '@settings/extensions';
import SettingsBlock from '@settings/Components/ReusableComponents/SettingsBlock';
import { ControlToggleButton } from '@settings/Components/ReusableComponents/Controls/index.js';

const useSettings = createExtensionStore( {
	name: 'agentic-settings',
	defaults: {
		active: false,
	},
} );

const AgenticSettings = () => {
	const { active, setActive } = useSettings();

	return (
		<SettingsBlock
			title={ __( 'Agentic Commerce', 'woocommerce-paypal-payments' ) }
		>
			<ControlToggleButton
				label={ __(
					'Agentic Features',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Allow the PayPal AI agent to shop on this store - payments are collected by this plugin, while the customer never visits your website.',
					'woocommerce-paypal-payments'
				) }
				value={ active }
				onChange={ setActive }
			/>
		</SettingsBlock>
	);
};

registerSetting(
	SLOTS.PAYPAL_SETTINGS_END,
	'agentic-settings',
	AgenticSettings,
	10
);
