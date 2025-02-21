import Container from '../../ReusableComponents/Container';
import SettingsNavigation from './Components/Navigation';
import { getSettingsTabs } from './Tabs';

const SettingsScreen = ( { activePanel, setActivePanel } ) => {
	const tabs = getSettingsTabs();
	const { Component } = tabs.find( ( tab ) => tab.name === activePanel );

	return (
		<>
			<SettingsNavigation
				tabs={ tabs }
				activePanel={ activePanel }
				setActivePanel={ setActivePanel }
			/>
			<Container page="settings">{ Component }</Container>
		</>
	);
};

export default SettingsScreen;
