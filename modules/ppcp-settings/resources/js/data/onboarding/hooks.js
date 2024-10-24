import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useOnboardingDetails = () => {
	const {
		setOnboardingStep,
		setSandboxMode,
		setManualConnectionMode,
		persist,
		setClientId,
		setClientSecret,
	} = useDispatch( STORE_NAME );

	// Transient accessors.
	const isSaving = useSelect( ( select ) => {
		return select( STORE_NAME ).getTransientData().isSaving;
	}, [] );

	// Persistent accessors.
	const clientId = useSelect( ( select ) => {
		return select( STORE_NAME ).getPersistentData().clientId;
	}, [] );

	const clientSecret = useSelect( ( select ) => {
		return select( STORE_NAME ).getPersistentData().clientSecret;
	}, [] );

	const onboardingStep = useSelect( ( select ) => {
		return select( STORE_NAME ).getPersistentData().step || 0;
	}, [] );

	const isSandboxMode = useSelect( ( select ) => {
		return select( STORE_NAME ).getPersistentData().useSandbox;
	}, [] );

	const isManualConnectionMode = useSelect( ( select ) => {
		return select( STORE_NAME ).getPersistentData().useManualConnection;
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
		clientId,
		setClientId: ( value ) => setDetailAndPersist( setClientId, value ),
		clientSecret,
		setClientSecret: ( value ) =>
			setDetailAndPersist( setClientSecret, value ),
		setOnboardingStep: ( step ) =>
			setDetailAndPersist( setOnboardingStep, step ),
		setSandboxMode: ( state ) =>
			setDetailAndPersist( setSandboxMode, state ),
		setManualConnectionMode: ( state ) =>
			setDetailAndPersist( setManualConnectionMode, state ),
	};
};
