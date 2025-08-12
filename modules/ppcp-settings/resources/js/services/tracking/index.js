/**
 * Tracking: Main entry point for the tracking system.
 *
 * Exports all tracking services, registry functions, adapters, and utilities.
 * Provides a centralized interface for funnel tracking and analytics integration.
 *
 * @file
 */

export { FunnelTrackingService } from './services/funnel-tracking';

export {
	registerFunnel,
	addStoreToFunnel,
	initializeTracking,
	getRegisteredFunnels,
	getTrackingInstances,
	getTrackingInstance,
	validateFunnelConfig,
	getMultiFunnelStores,
	getFunnelsForStore,
	getTrackingStatus,
} from './registry';

export { subscriptionManager } from './subscription-manager';
export { FUNNELS, registerAllFunnels, registerFunnelById } from './funnels';
export { WooCommerceTracksAdapter, ConsoleLoggerAdapter } from './adapters';
export * from './utils/field-config-helpers';
export * from './utils';
export { initializeTrackingFunnels, ONBOARDING_FUNNEL_ID } from './init';
