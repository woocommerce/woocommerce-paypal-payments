const EMPTY_OBJ = {};

const getOnboardingState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.onboarding || EMPTY_OBJ;
};

export const getOnboardingData = ( state ) => {
	return getOnboardingState( state ).data || EMPTY_OBJ;
};

export const isSaving = ( state ) => {
	return getOnboardingState( state ).isSaving || false;
};

export const getOnboardingStep = ( state ) => {
	return getOnboardingData( state ).step || 0;
};
