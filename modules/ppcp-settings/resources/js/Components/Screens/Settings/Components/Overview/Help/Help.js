import { __ } from '@wordpress/i18n';
import { FeatureSettingsBlock } from '../../../../../ReusableComponents/SettingsBlocks';
import {
	Content,
	ContentWrapper,
} from '../../../../../ReusableComponents/Elements';
import SettingsCard from '../../../../../ReusableComponents/SettingsCard';

const Help = () => {
	return (
		<SettingsCard
			className="ppcp-r-tab-overview-help"
			title={ __( 'Help Center', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Access detailed guides and responsive support to streamline setup and enhance your experience.',
				'woocommerce-paypal-payments'
			) }
			contentContainer={ false }
		>
			<ContentWrapper>
				<Content>
					<FeatureSettingsBlock
						title={ __(
							'Documentation',
							'woocommerce-paypal-payments'
						) }
						description={ __(
							'Find detailed guides and resources to help you set up, manage, and optimize your PayPal integration.',
							'woocommerce-paypal-payments'
						) }
						actionProps={ {
							buttons: [
								{
									type: 'tertiary',
									text: __(
										'View full documentation',
										'woocommerce-paypal-payments'
									),
									url: 'https://woocommerce.com/document/woocommerce-paypal-payments/',
								},
							],
						} }
					/>
				</Content>

				<Content>
					<FeatureSettingsBlock
						title={ __( 'Support', 'woocommerce-paypal-payments' ) }
						description={ __(
							'Need help? Access troubleshooting tips or contact our support team for personalized assistance.',
							'woocommerce-paypal-payments'
						) }
						actionProps={ {
							buttons: [
								{
									type: 'tertiary',
									text: __(
										'View support options',
										'woocommerce-paypal-payments'
									),
									url: 'https://woocommerce.com/document/woocommerce-paypal-payments/#get-help ',
								},
							],
						} }
					/>
				</Content>
			</ContentWrapper>
		</SettingsCard>
	);
};

export default Help;
