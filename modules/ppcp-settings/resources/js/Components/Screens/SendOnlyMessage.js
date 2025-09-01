import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import Container from '../ReusableComponents/Container';
import SettingsCard from '../ReusableComponents/SettingsCard';
import SettingsNavigation from './Settings/Components/Navigation';

const SendOnlyMessage = () => {
	const settingsPageUrl = '/wp-admin/admin.php?page=wc-settings';

	return (
		<>
			<SettingsNavigation canSave={ false } />
			<Container page="settings">
				<SettingsCard
					title={ __(
						'"Send-only" Country',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'Sellers in your country are unable to receive payments via PayPal',
						'woocommerce-paypal-payments'
					) }
				>
					<p>
						{ __(
							'Your current WooCommerce store location is in a "send-only" country, according to PayPal\'s policies',
							'woocommerce-paypal-payments'
						) }
					</p>
					<p>
						{ __(
							'Since receiving payments is essential for using the PayPal Payments extension, you are unable to connect your PayPal account while operating from a "send-only" country.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<p
						dangerouslySetInnerHTML={ {
							__html: sprintf(
								/* translators: 1: URL to the WooCommerce store location settings */
								__(
									'To activate PayPal, please <a href="%1$s">update your WooCommerce store location</a> to a supported region and connect a PayPal account eligible for receiving payments.',
									'woocommerce-paypal-payments'
								),
								settingsPageUrl
							),
						} }
					/>

					<div>
						<Button
							href={ settingsPageUrl }
							variant="primary"
							className="small-button"
						>
							{ __(
								'Go to WooCommerce settings',
								'woocommerce-paypal-payments'
							) }
						</Button>
					</div>
				</SettingsCard>
			</Container>
		</>
	);
};

export default SendOnlyMessage;
