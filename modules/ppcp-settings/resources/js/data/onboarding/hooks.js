import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useOnboardingDetails = () => {
	const {
		setOnboardingStep,
		setSandboxMode,
		setManualConnectionMode,
		persist,
	} = useDispatch( STORE_NAME );

	const isSaving = useSelect( ( select ) => {
		return select( STORE_NAME ).isSaving();
	}, [] );

	const onboardingStep = useSelect( ( select ) => {
		return select( STORE_NAME ).getOnboardingData().step || 0;
	}, [] );

	const isSandboxMode = useSelect( ( select ) => {
		return select( STORE_NAME ).getOnboardingData().useSandbox;
	}, [] );

	const isManualConnectionMode = useSelect( ( select ) => {
		return select( STORE_NAME ).getOnboardingData().useManualConnection;
	}, [] );

	const setDetailAndPersist = async ( setter, value ) => {
		setter( value );
		await persist();
	};

	return {
		onboardingStep,
		isSaving,
		isSandboxMode,
		isManualConnectionMode,
		setOnboardingStep: ( step ) =>
			setDetailAndPersist( setOnboardingStep, step ),
		setSandboxMode: ( state ) =>
			setDetailAndPersist( setSandboxMode, state ),
		setManualConnectionMode: ( state ) =>
			setDetailAndPersist( setManualConnectionMode, state ),
	};
};
