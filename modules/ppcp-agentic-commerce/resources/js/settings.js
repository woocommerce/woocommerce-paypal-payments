import { __ } from '@wordpress/i18n';
import { registerSetting, SLOTS } from '@settings/extensions';
import SettingsBlock from '@settings/Components/ReusableComponents/SettingsBlock';
import { ControlToggleButton } from '@settings/Components/ReusableComponents/Controls/index.js';
import { createExtensionStore } from '@settings/extensions';

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
					'Enable this to allow the PayPal AI agent to shop on this store.',
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
