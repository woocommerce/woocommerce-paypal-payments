import Onboarding from './Onboarding/Onboarding';
import { useState } from '@wordpress/element';
import Dashboard from './Dashboard/Dashboard';

const Settings = () => {
	const [ onboarded, setOnboarded ] = useState( true );

	return <>{ onboarded ? <Onboarding /> : <Dashboard /> }</>;
};

export default Settings;
