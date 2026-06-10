import { __ } from '@wordpress/i18n';
import SettingsCard from '@ppcp-settings/Components/ReusableComponents/SettingsCard';
import {
	Content,
	ContentWrapper,
} from '@ppcp-settings/Components/ReusableComponents/Elements';
import Troubleshooting from './Blocks/Troubleshooting';
import PaypalSettings from './Blocks/PaypalSettings';
import OtherSettings from './Blocks/OtherSettings';
import { useRegisteredSettings, SLOTS } from '@ppcp-settings/extensions';
import BlueprintExportImport from './Blocks/BlueprintExportImport';
import data from '../../../../../utils/data';

const ExpertSettings = ( { ownBradOnly, hasContactModule } ) => {
	// Get registered settings for expert settings
	const footerSettings = useRegisteredSettings( SLOTS.EXPERT_SETTINGS_END );
	const { blueprint } = data();

	return (
		<SettingsCard
			icon="icon-settings-expert.svg"
			className="ppcp-r-settings-card ppcp-r-settings-card--expert-settings"
			title={ __( 'Expert Settings', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Fine-tune your PayPal experience with advanced options.',
				'woocommerce-paypal-payments'
			) }
			actionProps={ {
				key: 'payNowExperience',
			} }
			contentContainer={ false }
		>
			<ContentWrapper>
				{ /*<Content>
					<ConnectionDetails />
				</Content>*/ }

				<Content>
					<Troubleshooting />
				</Content>

				<Content>
					<PaypalSettings hasContactModule={ hasContactModule } />
				</Content>

				{ ownBradOnly || (
					// The "other settings" accordion is only relevant in white-label mode.
					<Content>
						<OtherSettings />
					</Content>
				) }

				{ blueprint?.isActive && (
					<Content>
						<BlueprintExportImport />
					</Content>
				) }

				{ /* Extension point */ }
				{ footerSettings.map( ( { component: Component, id } ) => (
					<Content key={ id }>
						<Component />
					</Content>
				) ) }
			</ContentWrapper>
		</SettingsCard>
	);
};

export default ExpertSettings;
