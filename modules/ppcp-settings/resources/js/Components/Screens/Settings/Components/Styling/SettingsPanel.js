import {
	LocationSelector,
	PaymentMethods,
	ButtonLayout,
	ButtonShape,
	ButtonLabel,
	ButtonColor,
} from './Content';
import { StylingHooks } from '../../../../../data';

const SettingsPanel = ( { location, setLocation } ) => {
	const { isActive } = StylingHooks.useLocationProps( location );

	const LocationDetails = () => {
		if ( ! isActive ) {
			return null;
		}

		return (
			<>
				<PaymentMethods location={ location } />
				<ButtonLayout location={ location } />
				<ButtonShape location={ location } />
				<ButtonLabel location={ location } />
				<ButtonColor location={ location } />
			</>
		);
	};

	return (
		<div className="settings-panel">
			<LocationSelector
				location={ location }
				setLocation={ setLocation }
			/>
			<LocationDetails />
		</div>
	);
};

export default SettingsPanel;
