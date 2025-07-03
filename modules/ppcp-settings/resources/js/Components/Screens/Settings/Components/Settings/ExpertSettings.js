import { __ } from '@wordpress/i18n';
import SettingsCard from '../../../../ReusableComponents/SettingsCard';
import {
	Content,
	ContentWrapper,
} from '../../../../ReusableComponents/Elements';
import Troubleshooting from './Blocks/Troubleshooting';
import PaypalSettings from './Blocks/PaypalSettings';
import OtherSettings from './Blocks/OtherSettings';

const ExpertSettings = ( { ownBradOnly, hasContactModule } ) => {
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
			</ContentWrapper>
		</SettingsCard>
	);
};

export default ExpertSettings;
