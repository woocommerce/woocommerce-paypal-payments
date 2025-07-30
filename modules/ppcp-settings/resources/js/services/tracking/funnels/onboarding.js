/**
 * Onboarding Funnel: Complete tracking configuration for onboarding flow.
 *
 * Defines events, translations, and field configurations for PayPal onboarding tracking.
 * Includes step progression tracking, user selections, and completion events.
 *
 * @file
 */

import {
	FunnelConfigBuilder,
	createFieldTrackingConfig,
	createSystemFieldTrackingConfig,
	createTransientFieldTrackingConfig,
	createBooleanFieldTrackingConfig,
	createArrayFieldTrackingConfig,
} from '../utils/field-config-helpers';

export const FUNNEL_ID = 'ppcp_onboarding';

// Event names specific to this funnel.
export const EVENTS = {
	welcome_view: 'ppcp_onboarding_welcome_view',
	account_type_view: 'ppcp_onboarding_account_type_view',
	products_view: 'ppcp_onboarding_products_view',
	payment_options_view: 'ppcp_onboarding_payment_options_view',
	complete_view: 'ppcp_onboarding_complete_view',
	account_type_select: 'ppcp_onboarding_account_type_business_type_select',
	products_select: 'ppcp_onboarding_products_products_select',
	payment_options_select:
		'ppcp_onboarding_payment_options_payment_method_select',
	sandbox_mode_select: 'ppcp_onboarding_sandbox_mode_select',
	manual_connection_select: 'ppcp_onboarding_manual_connection_select',
	complete_connect_click: 'ppcp_onboarding_complete_connect_click',
};

// Step metadata.
export const STEP_INFO = {
	0: { name: 'welcome', viewEvent: EVENTS.welcome_view },
	1: { name: 'account_type', viewEvent: EVENTS.account_type_view },
	2: { name: 'products', viewEvent: EVENTS.products_view },
	3: { name: 'payment_options', viewEvent: EVENTS.payment_options_view },
	4: { name: 'complete', viewEvent: EVENTS.complete_view },
};

// Translation functions specific to this funnel.
export const TRANSLATIONS = {
	step: ( oldStep, newStep, metadata, trackingService ) => {
		const stepInfo = STEP_INFO[ newStep ];
		if ( ! stepInfo ) {
			return;
		}

		const eventData = {
			step_number: newStep,
			step_name: stepInfo.name,
			...trackingService.getCommonProperties( metadata ),
		};

		trackingService.sendToAdapters( stepInfo.viewEvent, eventData );
	},

	isCasualSeller: ( oldValue, newValue, metadata, trackingService ) => {
		if ( newValue === null ) {
			return;
		}

		const accountType = newValue === true ? 'personal' : 'business';
		const eventData = {
			selected_value: accountType,
			step_number: metadata.currentStep,
			step_name: metadata.stepName,
			...trackingService.getCommonProperties( metadata ),
		};

		trackingService.sendToAdapters( EVENTS.account_type_select, eventData );
	},

	products: ( oldValue, newValue, metadata, trackingService ) => {
		if ( ! Array.isArray( newValue ) ) {
			return;
		}

		const eventData = {
			selected_products: newValue.join( ',' ),
			products_count: newValue.length,
			previous_products: Array.isArray( oldValue )
				? oldValue.join( ',' )
				: 'none',
			step_number: metadata.currentStep,
			step_name: metadata.stepName,
			...trackingService.getCommonProperties( metadata ),
		};

		trackingService.sendToAdapters( EVENTS.products_select, eventData );
	},

	areOptionalPaymentMethodsEnabled: (
		oldValue,
		newValue,
		metadata,
		trackingService
	) => {
		if ( newValue === null ) {
			return;
		}

		const paymentOption = newValue ? 'expanded' : 'no_cards';
		const eventData = {
			selected_value: paymentOption,
			step_number: metadata.currentStep,
			step_name: metadata.stepName,
			...trackingService.getCommonProperties( metadata ),
		};

		trackingService.sendToAdapters(
			EVENTS.payment_options_select,
			eventData
		);
	},

	completed: ( oldValue, newValue, metadata, trackingService ) => {
		if ( newValue === true ) {
			const eventData = {
				step_number: metadata.currentStep,
				step_name: metadata.stepName,
				total_duration_ms:
					Date.now() - trackingService.sessionStartTime,
				final_account_type: metadata?.isCasualSeller
					? 'personal'
					: 'business',
				final_products: Array.isArray( metadata?.products )
					? metadata.products.join( ',' )
					: '',
				final_payment_options:
					metadata?.areOptionalPaymentMethodsEnabled
						? 'expanded'
						: 'no_cards',
				final_sandbox_mode: metadata?.useSandbox
					? 'enabled'
					: 'disabled',
				...trackingService.getCommonProperties( metadata ),
			};

			trackingService.sendToAdapters(
				EVENTS.complete_connect_click,
				eventData
			);
		}
	},

	connectionButtonClicked: (
		oldValue,
		newValue,
		metadata,
		trackingService
	) => {
		if ( newValue === true && oldValue === false ) {
			const eventData = {
				step_number: metadata.currentStep,
				step_name: metadata.stepName,
				...trackingService.getCommonProperties( metadata ),
			};

			trackingService.sendToAdapters(
				EVENTS.complete_connect_click,
				eventData
			);
		}
	},

	useSandbox: ( oldValue, newValue, metadata, trackingService ) => {
		if ( newValue === null ) {
			return;
		}

		const sandboxMode = newValue === true ? 'enabled' : 'disabled';
		const eventData = {
			selected_value: sandboxMode,
			...trackingService.getCommonProperties( metadata ),
		};

		trackingService.sendToAdapters( EVENTS.sandbox_mode_select, eventData );
	},

	useManualConnection: ( oldValue, newValue, metadata, trackingService ) => {
		if ( newValue === null ) {
			return;
		}

		const manualMode = newValue === true ? 'enabled' : 'disabled';
		const eventData = {
			selected_value: manualMode,
			...trackingService.getCommonProperties( metadata ),
		};

		trackingService.sendToAdapters(
			EVENTS.manual_connection_select,
			eventData
		);
	},
};

// Field tracking configurations using generic helpers.
const createStepTrackingConfig = () => {
	return createSystemFieldTrackingConfig( 'step', 'persistent', {
		transform: ( value ) => ( {
			step_number: value,
			step_name: STEP_INFO[ value ]?.name || `step_${ value }`,
		} ),
	} );
};

const createAccountTypeTrackingConfig = () => {
	return createFieldTrackingConfig( 'isCasualSeller', 'persistent', {
		transform: ( value ) => ( {
			selected_value: value === true ? 'personal' : 'business',
		} ),
		rules: {
			allowedSources: [ 'user' ],
		},
	} );
};

const createProductsTrackingConfig = () => {
	return createArrayFieldTrackingConfig( 'products', 'persistent', {
		rules: {
			allowedSources: [ 'user' ],
		},
	} );
};

const createPaymentOptionsTrackingConfig = () => {
	return createFieldTrackingConfig(
		'areOptionalPaymentMethodsEnabled',
		'persistent',
		{
			transform: ( value ) => ( {
				selected_value: value === true ? 'expanded' : 'no_cards',
			} ),
			rules: {
				allowedSources: [ 'user' ],
			},
		}
	);
};

const createCompletedTrackingConfig = () => {
	return createFieldTrackingConfig( 'completed', 'persistent', {
		transform: ( value ) => ( {
			completed: value === true,
		} ),
		rules: {
			allowedSources: [ 'system' ],
		},
	} );
};

const createConnectionButtonTrackingConfig = () => {
	return createTransientFieldTrackingConfig( 'connectionButtonClicked' );
};

const createSandboxTrackingConfig = () => {
	return createBooleanFieldTrackingConfig(
		'useSandbox',
		'persistent',
		'enabled',
		'disabled'
	);
};

const createManualConnectionTrackingConfig = () => {
	return createBooleanFieldTrackingConfig(
		'useManualConnection',
		'persistent',
		'enabled',
		'disabled'
	);
};

// Main funnel configuration.
export const config = FunnelConfigBuilder.createBasicFunnel( FUNNEL_ID, {
	debug: false,
	adapters: [ 'woocommerce-tracks' ],
	eventPrefix: 'ppcp_onboarding',
	// Only track for onboarding flow (isConnected: false).
	trackingCondition: {
		store: 'wc/paypal/common',
		selector: 'merchant',
		field: 'isConnected',
		expectedValue: false,
	},
} )
	.addEvents( EVENTS )
	.addTranslations( TRANSLATIONS )
	.addStepInfo( STEP_INFO )
	.addStore( 'wc/paypal/onboarding', [
		createStepTrackingConfig(),
		createAccountTypeTrackingConfig(),
		createProductsTrackingConfig(),
		createPaymentOptionsTrackingConfig(),
		createCompletedTrackingConfig(),
		createConnectionButtonTrackingConfig(),
	] )
	.addStore( 'wc/paypal/common', [
		createSandboxTrackingConfig(),
		createManualConnectionTrackingConfig(),
	] )
	.build();
