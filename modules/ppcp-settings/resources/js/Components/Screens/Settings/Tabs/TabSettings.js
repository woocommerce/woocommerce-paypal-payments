import ConnectionStatus from '../Components/Settings/ConnectionStatus';
import CommonSettings from '../Components/Settings/CommonSettings';
import ExpertSettings from '../Components/Settings/ExpertSettings';
import SpinnerOverlay from '../../../ReusableComponents/SpinnerOverlay';
import { SettingsHooks } from '../../../../data';

const TabSettings = () => {
	const { isReady } = SettingsHooks.useStore();

	if ( ! isReady ) {
		return <SpinnerOverlay asModal={ true } />;
	}

	return (
		<div className="ppcp-r-settings">
			<ConnectionStatus />
			<CommonSettings />
			<ExpertSettings />
		</div>
	);
};

export default TabSettings;
