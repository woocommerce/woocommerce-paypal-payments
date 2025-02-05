import Container from '../../ReusableComponents/Container';
import { OnboardingHooks } from '../../../data';

import { getSteps, getCurrentStep } from './Steps';
import OnboardingNavigation from './Components/Navigation';

const OnboardingScreen = () => {
	const { step, setStep, flags } = OnboardingHooks.useSteps();
	const { isCasualSeller } = OnboardingHooks.useBusiness();

	const Steps = getSteps( flags, isCasualSeller );
	const currentStep = getCurrentStep( step, Steps );

	const handleNext = () => setStep( currentStep.nextStep );
	const handlePrev = () => setStep( currentStep.prevStep );

	return (
		<>
			<OnboardingNavigation
				stepDetails={ currentStep }
				onNext={ handleNext }
				onPrev={ handlePrev }
			/>

			<Container page="onboarding">
				<div className="ppcp-r-card">
					<currentStep.StepComponent
						setStep={ setStep }
						currentStep={ step }
						stepperOrder={ Steps }
					/>
				</div>
			</Container>
		</>
	);
};

export default OnboardingScreen;
