import Separator from '../../../ReusableComponents/Separator';
import SandboxConnectionForm from './SandboxConnectionForm';
import ManualConnectionForm from './ManualConnectionForm';

const AdvancedOptionsForm = () => {
	return (
		<>
			<SandboxConnectionForm />
			<Separator withLine={ false } />
			<ManualConnectionForm />
		</>
	);
};

export default AdvancedOptionsForm;
