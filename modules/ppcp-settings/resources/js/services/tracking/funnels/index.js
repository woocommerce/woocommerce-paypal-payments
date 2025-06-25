/**
 * Funnel Registry: Centralized exports and registration for all tracking funnels.
 *
 * Provides a single entry point for registering and managing tracking funnels.
 * Includes funnel mapping, bulk registration, and individual funnel access.
 *
 * @file
 */

import { registerFunnel } from '../registry';
import * as onboardingFunnel from './onboarding';

// Map of all available funnels.
export const FUNNELS = {
	[ onboardingFunnel.FUNNEL_ID ]: onboardingFunnel,
};

/**
 * Register all available funnels.
 */
export function registerAllFunnels() {
	Object.values( FUNNELS ).forEach( ( funnel ) => {
		registerFunnel( funnel.FUNNEL_ID, funnel.config );
	} );
}

/**
 * Register a specific funnel by ID.
 *
 * @param {string} funnelId - The funnel identifier.
 * @return {Object|null} Funnel registration or null if not found.
 */
export function registerFunnelById( funnelId ) {
	const funnel = FUNNELS[ funnelId ];
	if ( ! funnel ) {
		console.error( `[Tracking] Funnel ${ funnelId } not found` );
		return null;
	}

	return registerFunnel( funnel.FUNNEL_ID, funnel.config );
}
