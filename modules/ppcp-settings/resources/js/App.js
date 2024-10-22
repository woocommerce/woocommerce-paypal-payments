import * as Store from './data';

const StoreTest = () => {
	const { isSaving, onboardingStep, setOnboardingStep } =
		Store.useOnboardingDetails();

	return (
		<div>
			<hr />
			<div>Onboarding Step: { onboardingStep }</div>
			<div>{ isSaving ? 'Saving...' : 'Not Saving' }</div>

			<div>
				<button
					type={ 'button' }
					onClick={ () => setOnboardingStep( onboardingStep - 1 ) }
					disabled={ onboardingStep < 1 }
				>
					Prev
				</button>
				<button
					type={ 'button' }
					onClick={ () => setOnboardingStep( onboardingStep + 1 ) }
					disabled={ onboardingStep > 3 }
				>
					Next
				</button>
			</div>
		</div>
	);
};

export function App() {
	return (
		<div className="red">
			App
			<StoreTest />
		</div>
	);
}
