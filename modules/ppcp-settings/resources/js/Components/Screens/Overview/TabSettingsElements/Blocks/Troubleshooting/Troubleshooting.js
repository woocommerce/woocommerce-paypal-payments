import { __ } from '@wordpress/i18n';
import {
	AccordionSettingsBlock,
	Description,
	Header,
	Title,
	ToggleSettingsBlock,
} from '../../../../../ReusableComponents/SettingsBlocks';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlocks/SettingsBlock';

import SimulationBlock from './SimulationBlock';
import ResubscribeBlock from './ResubscribeBlock';
import HooksTableBlock from './HooksTableBlock';

const Troubleshooting = ( { updateFormValue, settings } ) => {
	return (
		<AccordionSettingsBlock
			className="ppcp-r-settings-block--troubleshooting"
			title={ __( 'Troubleshooting', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Access tools to help debug and resolve issues.',
				'woocommerce-paypal-payments'
			) }
			actionProps={ {
				callback: updateFormValue,
				key: 'payNowExperience',
				value: settings.payNowExperience,
			} }
		>
			<ToggleSettingsBlock
				title={ __( 'Logging', 'woocommerce-paypal-payments' ) }
				description={ __(
					'Log additional debugging information in the WooCommerce logs that can assist technical staff to determine issues.',
					'woocommerce-paypal-payments'
				) }
				actionProps={ {
					callback: updateFormValue,
					key: 'logging',
					value: settings.logging,
				} }
			/>
			<SettingsBlock>
				<Header>
					<Title>
						{ __( 'Webhooks', 'woocommerce-paypal-payments' ) }
					</Title>
					<Description>
						{ __(
							'The following PayPal webhooks are subscribed. More information about the webhooks is available in the',
							'woocommerce-paypal-payments'
						) }{ ' ' }
						<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#webhook-status">
							{ __(
								'Webhook Status documentation',
								'woocommerce-paypal-payments'
							) }
						</a>
						.
					</Description>
				</Header>
				<HooksTableBlock />
				<ResubscribeBlock />
				<SimulationBlock />
			</SettingsBlock>
		</AccordionSettingsBlock>
	);
};

export default Troubleshooting;
