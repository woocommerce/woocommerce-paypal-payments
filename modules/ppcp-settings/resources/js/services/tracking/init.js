/**
 * Initialization: Set up and register tracking funnels.
 *
 * Handles the registration of tracking funnels and provides initialization utilities.
 * Must be called before store initialization to ensure proper tracking setup.
 *
 * @file
 */

import { registerFunnel } from './registry';
import {
	FUNNEL_ID as ONBOARDING_FUNNEL_ID,
	config as onboardingConfig,
} from './funnels/onboarding';

let initialized = false;

export function initializeTrackingFunnels() {
	if ( initialized ) {
		return;
	}

	registerFunnel( ONBOARDING_FUNNEL_ID, onboardingConfig );

	initialized = true;
}

export function isTrackingInitialized() {
	return initialized;
}

initializeTrackingFunnels();

export { ONBOARDING_FUNNEL_ID };
