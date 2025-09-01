import { __ } from '@wordpress/i18n';

import OnboardingHeader from '../Components/OnboardingHeader';
import ConnectionButton from '../Components/ConnectionButton';

const StepCompleteSetup = () => {
	return (
		<div className="ppcp-r-page-products">
			<OnboardingHeader
				title={ __(
					'Complete Your Payment Setup',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'To finalize your payment setup, please log in to PayPal. If you don’t have an account yet, don’t worry - we’ll guide you through the easy process of creating one.',
					'woocommerce-paypal-payments'
				) }
			/>
			<div className="ppcp-r-inner-container ppcp--wide">
				<div className="ppcp-r-onboarding-header__description">
					<ConnectionButton
						title={ __(
							'Connect to PayPal',
							'woocommerce-paypal-payments'
						) }
					/>
				</div>
			</div>
		</div>
	);
};

export default StepCompleteSetup;
