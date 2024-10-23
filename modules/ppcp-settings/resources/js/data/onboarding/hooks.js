import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useOnboardingDetails = () => {
	const { setOnboardingStep, persist } = useDispatch( STORE_NAME );

	const onboardingStep = useSelect( ( select ) => {
		return select( STORE_NAME ).getOnboardingStep();
	}, [] );

	const isSaving = useSelect( ( select ) => {
		return select( STORE_NAME ).isSaving();
	}, [] );

	return {
		onboardingStep,
		isSaving,
		setOnboardingStep: async ( step ) => {
			setOnboardingStep( step );
			await persist();
		},
	};
};
