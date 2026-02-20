import { __ } from '@wordpress/i18n';
import {
	registerSetting,
	createExtensionStore,
	SLOTS,
} from '@ppcp-settings/extensions';
import SettingsBlock from '@ppcp-settings/Components/ReusableComponents/SettingsBlock';
import { ControlToggleButton } from '@ppcp-settings/Components/ReusableComponents/Controls/index.js';

const MODULE_ID = 'store-sync';
const useSettings = createExtensionStore( {
	name: MODULE_ID,
	defaults: {
		active: false,
	},
} );

const StoreSyncSettings = () => {
	const { active, setActive } = useSettings();

	return (
		<SettingsBlock
			title={ __( 'Store Sync', 'woocommerce-paypal-payments' ) }
		>
			<ControlToggleButton
				label={ __(
					'Enable Store Sync',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Allow PayPal AI agents to shop on this store - payments are collected by this plugin, while the customer never visits this website.',
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
	MODULE_ID,
	StoreSyncSettings,
	10
);
