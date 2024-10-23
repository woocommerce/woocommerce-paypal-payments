import Container from '../../reusable-components/container';
import StepWelcome from './step-welcome.js';
import StepBusiness from './step-business';

const Onboarding = () => {
	return (
		<Container>
			<div className="ppcp-r-card">
				<StepBusiness />
				<StepWelcome />
			</div>
		</Container>
	);
};

export default Onboarding;
