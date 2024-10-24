import Container from '../../ReusableComponents/Container.js';
import StepWelcome from './StepWelcome.js';
import StepBusiness from './StepBusiness';
import { useState } from '@wordpress/element';

const Onboarding = () => {
	const [ step, setStep ] = useState( 0 );

	return (
		<Container>
			<div className="ppcp-r-card">
				<Stepper currentStep={ step } setStep={ setStep } />
			</div>
		</Container>
	);
};

const Stepper = ( { currentStep, setStep } ) => {
	const stepperOrder = {
		0: StepWelcome,
		1: StepBusiness,
	};

	const Component = stepperOrder[ currentStep ];

	return (
		<>
			<Component setStep={ setStep } currentStep={ currentStep } />
		</>
	);
};

export default Onboarding;
