const EMPTY_OBJ = {};

const getOnboardingState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.onboarding || EMPTY_OBJ;
};

export const getOnboardingDetails = ( state ) => {
	return getOnboardingState( state ).data || EMPTY_OBJ;
};

export const getOnboardingStep = ( state ) => {
	return getOnboardingDetails( state ).step || 0;
};
