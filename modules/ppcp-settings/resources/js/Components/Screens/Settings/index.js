import { useEffect } from '@wordpress/element';
import Container from '@ppcp-settings/Components/ReusableComponents/Container';
import HelpSection from '@ppcp-settings/Components/ReusableComponents/HelpSection';
import SettingsNavigation from './Components/Navigation';
import { getSettingsTabs } from './Tabs';
import { useNavigation } from '@ppcp-settings/hooks/useNavigation';
import { PaymentHooks, SettingsHooks } from '@ppcp-settings/data';

const SettingsScreen = ( { activePanel, setActivePanel } ) => {
	const tabs = getSettingsTabs();
	const { Component } = tabs.find( ( tab ) => tab.name === activePanel );
	const { handleHighlightFromUrl } = useNavigation();
	const { isReady: isPaymentStoreReady } = PaymentHooks.useStore();
	const { isReady: isSettingsStoreReady } = SettingsHooks.useStore();

	useEffect( () => {
		if ( isPaymentStoreReady && isSettingsStoreReady ) {
			handleHighlightFromUrl();
		}
	}, [ handleHighlightFromUrl, isPaymentStoreReady, isSettingsStoreReady ] );

	return (
		<>
			<SettingsNavigation
				tabs={ tabs }
				activePanel={ activePanel }
				setActivePanel={ setActivePanel }
			/>
			<Container page="settings">
				{ Component }
				<HelpSection />
			</Container>
		</>
	);
};

export default SettingsScreen;
