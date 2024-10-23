import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useOnboardingDetails = () => {
	const {
		setOnboardingStep,
		setSandboxMode,
		setManualConnectionMode,
		persist,
	} = useDispatch( STORE_NAME );

	const onboardingStep = useSelect( ( select ) => {
		return select( STORE_NAME ).getOnboardingStep();
	}, [] );

	const isSaving = useSelect( ( select ) => {
		return select( STORE_NAME ).isSaving();
	}, [] );

	const setDetailAndPersist = async ( setter, value ) => {
		setter( value );
		await persist();
	};

	return {
		onboardingStep,
		isSaving,
		setOnboardingStep: ( step ) =>
			setDetailAndPersist( setOnboardingStep, step ),
		setSandboxMode: ( state ) =>
			setDetailAndPersist( setSandboxMode, state ),
		setManualConnectionMode: ( state ) =>
			setDetailAndPersist( setManualConnectionMode, state ),
	};
};
