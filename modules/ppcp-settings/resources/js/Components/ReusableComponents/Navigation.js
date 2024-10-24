import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const Navigation = ( {
	setStep,
	currentStep,
	stepperOrder,
	canProceeedCallback = () => true,
} ) => {
	const setNextStep = ( nextStep ) => {
		let newStep = currentStep + nextStep;
		if ( newStep > stepperOrder.length - 1 ) {
			newStep = currentStep;
		}
		setStep( newStep );
	};

	return (
		<div className="ppcp-r-navigation">
			<Button variant="tertiary" onClick={ () => setNextStep( -1 ) }>
				{ __( 'Back', 'woocommerce-paypal-payments' ) }
			</Button>
			<Button
				variant="primary"
				disabled={ ! canProceeedCallback() }
				onClick={ () => setNextStep( 1 ) }
			>
				{ __( 'Next', 'woocommerce-paypal-payments' ) }
			</Button>
		</div>
	);
};

export default Navigation;
