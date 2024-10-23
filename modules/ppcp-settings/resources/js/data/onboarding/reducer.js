import ACTION_TYPES from './action-types';

const defaultState = {
	isSaving: false,
	data: {
		step: 0,
		useSandbox: false,
		useManualConnection: false,
	},
};

export const onboardingReducer = (
	state = defaultState,
	{ type, ...action }
) => {
	switch ( type ) {
		case ACTION_TYPES.SET_ONBOARDING_DETAILS:
			return {
				...state,
				data: action.payload,
			};

		case ACTION_TYPES.SET_IS_SAVING_ONBOARDING_DETAILS:
			return {
				...state,
				isSaving: action.isSaving,
			};

		case ACTION_TYPES.SET_ONBOARDING_STEP:
			return {
				...state,
				data: {
					...( state.data || {} ),
					step: action.step,
				},
			};

		case ACTION_TYPES.SET_SANDBOX_MODE:
			return {
				...state,
				data: {
					...( state.data || {} ),
					useSandbox: action.useSandbox,
				},
			};

		case ACTION_TYPES.SET_MANUAL_CONNECTION_MODE:
			return {
				...state,
				data: {
					...( state.data || {} ),
					useManualConnection: action.useManualConnection,
				},
			};

		default:
	}

	return state;
};

export default onboardingReducer;
