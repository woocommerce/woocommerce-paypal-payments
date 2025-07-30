import { getSteps, getCurrentStep } from './Steps';
import { OnboardingHooks, CommonHooks } from '../../../data';
import OnboardingNavigation from './Components/Navigation';
import Container from '../../ReusableComponents/Container';

const OnboardingScreen = () => {
	const { step, setStep, flags } = OnboardingHooks.useSteps();
	const { ownBrandOnly } = CommonHooks.useWooSettings();
	const { isCasualSeller } = OnboardingHooks.useBusiness();

	const shouldSkipPaymentMethods = flags?.shouldSkipPaymentMethods || false;
	const canUseCasualSelling = flags?.canUseCasualSelling || false;

	// Determine if payment methods screen should be skipped
	const skipPaymentMethodsStep =
		shouldSkipPaymentMethods || ( ownBrandOnly && isCasualSeller );

	// Pass all conditions as arguments
	const Steps = getSteps( flags, {
		skipBusinessStep: ! canUseCasualSelling,
		skipPaymentMethodsStep,
	} );

	const currentStep = getCurrentStep( step, Steps );

	if ( ! currentStep?.StepComponent ) {
		console.error( 'Invalid Onboarding State', {
			step,
			flags,
			Steps,
			currentStep,
		} );
	}

	const handleNext = () => setStep( currentStep.nextStep, 'user' );
	const handlePrev = () => setStep( currentStep.prevStep, 'user' );

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
