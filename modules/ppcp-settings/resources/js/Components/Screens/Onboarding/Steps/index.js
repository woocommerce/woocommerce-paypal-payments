import { __ } from '@wordpress/i18n';

import StepWelcome from './StepWelcome';
import StepBusiness from './StepBusiness';
import StepProducts from './StepProducts';
import StepPaymentMethods from './StepPaymentMethods';
import StepCompleteSetup from './StepCompleteSetup';

/**
 * List of all onboarding screens that are available.
 *
 * The screens are displayed in the order in which they appear in this array
 *
 * @type {[{id, StepComponent, title}]}
 */
const ALL_STEPS = [
	{
		id: 'welcome',
		title: __( 'PayPal Payments', 'woocommerce-paypal-payments' ),
		StepComponent: StepWelcome,
		canProceed: () => true,
	},
	{
		id: 'business',
		title: __( 'Set up store type', 'woocommerce-paypal-payments' ),
		StepComponent: StepBusiness,
		canProceed: ( { business } ) => business.isCasualSeller !== null,
	},
	{
		id: 'products',
		title: __( 'Select product types', 'woocommerce-paypal-payments' ),
		StepComponent: StepProducts,
		canProceed: ( { products } ) => products.products.length > 0,
	},
	{
		id: 'methods',
		title: __( 'Choose checkout options', 'woocommerce-paypal-payments' ),
		StepComponent: StepPaymentMethods,
		canProceed: ( { methods } ) => methods.optionalMethods !== null,
	},
	{
		id: 'complete',
		title: __(
			'Connect your PayPal account',
			'woocommerce-paypal-payments'
		),
		StepComponent: StepCompleteSetup,
		canProceed: () => true,
	},
];

const filterSteps = ( steps, conditions ) => {
	return steps.filter( ( step ) =>
		conditions.every( ( condition ) => condition( step ) )
	);
};

export const getSteps = ( flags ) => {
	const steps = filterSteps( ALL_STEPS, [
		// Casual selling: Unlock the "Personal Account" choice.
		( step ) => flags.canUseCasualSelling || step.id !== 'business',
		// Card payments: Unlocks the "Extended Checkout" choice.
		( step ) => flags.canUseCardPayments || step.id !== 'methods',
	] );

	const totalStepsCount = steps.length;

	return steps.map( ( step, index ) => ( {
		...step,
		isFirst: index === 0,
		isLast: index === totalStepsCount - 1,
		showNext: index < totalStepsCount - 1,
		percentage: 100 * ( index / ( totalStepsCount - 1 ) ),
		nextStep: index < totalStepsCount - 1 ? index + 1 : index,
		prevStep: index > 0 ? index - 1 : 0,
	} ) );
};

/**
 * Returns the screen-details of the current step, based on the numeric step-index.
 *
 * @param {number} requestedStep Index of the screen to display.
 * @param {[]}     steps         List of all available steps (see `getSteps()`)
 * @return {{id, StepComponent, title}} The requested screen details, or the first welcome screen.
 */
export const getCurrentStep = ( requestedStep, steps ) => {
	const isValidStep = ( step ) =>
		typeof step === 'number' &&
		Number.isInteger( step ) &&
		step >= 0 &&
		step < steps.length;

	const safeCurrentStep = isValidStep( requestedStep ) ? requestedStep : 0;
	return steps[ safeCurrentStep ];
};
