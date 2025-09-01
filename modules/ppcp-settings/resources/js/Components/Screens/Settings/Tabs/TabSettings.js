import ConnectionStatus from '../Components/Settings/ConnectionStatus';
import CommonSettings from '../Components/Settings/CommonSettings';
import ExpertSettings from '../Components/Settings/ExpertSettings';
import SpinnerOverlay from '../../../ReusableComponents/SpinnerOverlay';
import { CommonHooks, SettingsHooks } from '../../../../data';

const TabSettings = () => {
	const { ownBrandOnly } = CommonHooks.useWooSettings();
	const { isReady } = SettingsHooks.useStore();
	const { features } = CommonHooks.useMerchantInfo();

	if ( ! isReady ) {
		return <SpinnerOverlay asModal={ true } />;
	}

	return (
		<div className="ppcp-r-settings">
			<ConnectionStatus />
			<CommonSettings ownBradOnly={ ownBrandOnly } />
			<ExpertSettings
				ownBradOnly={ ownBrandOnly }
				hasContactModule={ features?.contact_module?.enabled }
			/>
		</div>
	);
};

export default TabSettings;
