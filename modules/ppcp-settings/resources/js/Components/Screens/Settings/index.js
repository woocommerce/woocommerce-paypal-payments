import Container from '@ppcp-settings/Components/ReusableComponents/Container';
import HelpSection from '@ppcp-settings/Components/ReusableComponents/HelpSection';
import SettingsNavigation from './Components/Navigation';
import AgenticBetaBanner from './Components/AgenticBetaBanner';
import { getSettingsTabs } from './Tabs';

const SettingsScreen = ( { activePanel, setActivePanel } ) => {
	const tabs = getSettingsTabs();
	const { Component } = tabs.find( ( tab ) => tab.name === activePanel );
	const isAgenticBetaBannerEligible =
		window.ppcpSettings?.isAgenticBetaBannerEligible;

	return (
		<>
			<SettingsNavigation
				tabs={ tabs }
				activePanel={ activePanel }
				setActivePanel={ setActivePanel }
			/>
			{ isAgenticBetaBannerEligible && <AgenticBetaBanner /> }
			<Container page="settings">
				{ Component }
				<HelpSection />
			</Container>
		</>
	);
};

export default SettingsScreen;
