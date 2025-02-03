import { __, sprintf } from '@wordpress/i18n';

import { ControlToggleButton } from '../../../../../ReusableComponents/Controls';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import Accordion from '../../../../../ReusableComponents/AccordionSection';

import SimulationBlock from './SimulationBlock';
import ResubscribeBlock from './ResubscribeBlock';
import HooksListBlock from './HooksListBlock';
import { SettingsHooks } from '../../../../../../data';

const Troubleshooting = () => {
	const { logging, setLogging } = SettingsHooks.useSettings();

	return (
		<Accordion
			className="ppcp--troubleshooting"
			title={ __( 'Troubleshooting', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Access tools to help debug and resolve issues.',
				'woocommerce-paypal-payments'
			) }
		>
			<SettingsBlock>
				<ControlToggleButton
					label={ __( 'Logging', 'woocommerce-paypal-payments' ) }
					description={ __(
						'Log additional debugging information in the WooCommerce logs that can assist technical staff to determine issues.',
						'woocommerce-paypal-payments'
					) }
					value={ logging }
					onChange={ setLogging }
				/>
			</SettingsBlock>

			<SettingsBlock
				title={ __( 'Webhooks', 'woocommerce-paypal-payments' ) }
				description={ sprintf(
					__(
						'The following PayPal webhooks are subscribed. More information about the webhooks is available in the <a href="%s">Webhook Status documentation</a>.',
						'woocommerce-paypal-payments'
					),
					'https://woocommerce.com/document/woocommerce-paypal-payments/#webhook-status'
				) }
			>
				<HooksListBlock />
				<ResubscribeBlock />
				<SimulationBlock />
			</SettingsBlock>
		</Accordion>
	);
};

export default Troubleshooting;
