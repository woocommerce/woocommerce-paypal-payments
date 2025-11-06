import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { registerSetting, SLOTS } from '@settings/extensions';
import SettingsBlock from '@settings/Components/ReusableComponents/SettingsBlock';
import { ControlToggleButton } from '@settings/Components/ReusableComponents/Controls/index.js';

const AgenticSettings = () => {
	const [ active, setActive ] = useState( true );

	return (
		<SettingsBlock
			title={ __( 'Agentic Commerce', 'woocommerce-paypal-payments' ) }
		>
			<ControlToggleButton
				label={ __(
					'Enable Agentic Features',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Enable this to enable agentic shopping on this shop.',
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
