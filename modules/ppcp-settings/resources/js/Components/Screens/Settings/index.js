import Container from '@ppcp-settings/Components/ReusableComponents/Container';
import HelpSection from '@ppcp-settings/Components/ReusableComponents/HelpSection';
import SettingsNavigation from './Components/Navigation';
import AgenticBetaBanner from './Components/AgenticBetaBanner';
import { getSettingsTabs } from './Tabs';
import { __ } from '@wordpress/i18n';

const SettingsScreen = ( { activePanel, setActivePanel } ) => {
	const tabs = getSettingsTabs();
	const { Component } = tabs.find( ( tab ) => tab.name === activePanel );
	const isAgenticBetaBannerEligible = window.ppcpSettings?.isAgenticBetaBannerEligible;

	return (
		<>
			<SettingsNavigation
				tabs={ tabs }
				activePanel={ activePanel }
				setActivePanel={ setActivePanel }
			/>
			{ isAgenticBetaBannerEligible && (
				<AgenticBetaBanner
					id="ppcp-agentic-beta-banner"
					className="ppcp-r-settings-banner"
					title={ __(
						'Be among the first: Join the PayPal Store Sync Beta',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						"AI-powered shopping agents are changing how customers discover and buy. We're looking for a small group of active US-based WooCommerce merchants to help shape this experience — get early access, direct input into the roadmap, and dedicated support from the PayPal & Syde team.",
						'woocommerce-paypal-payments'
					) }
					actionProps={ {
						buttons: [
							{
								type: 'secondary',
								text: __(
									'Apply for Early Access',
									'woocommerce-paypal-payments'
								),
								url: 'https://example.com',
							},
							{
								type: 'tertiary',
								text: __(
									'Remind me later',
									'woocommerce-paypal-payments'
								),
							},
						],
					} }
				/>
			) }
			<Container page="settings">
				{ Component }
				<HelpSection />
			</Container>
		</>
	);
};

export default SettingsScreen;
