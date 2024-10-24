import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const Navigation = ( { setStep, currentStep } ) => {
	return (
		<div className="ppcp-r-navigation">
			<Button
				variant="tertiary"
				onClick={ () => setStep( currentStep - 1 ) }
			>
				{ __( 'Back', 'woocommerce-paypal-payments' ) }
			</Button>
			<Button
				variant="primary"
				onClick={ () => setStep( currentStep + 1 ) }
			>
				{ __( 'Next', 'woocommerce-paypal-payments' ) }
			</Button>
		</div>
	);
};

export default Navigation;
