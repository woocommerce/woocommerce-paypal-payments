import { __ } from '@wordpress/i18n';

import { Separator } from '@ppcp-settings/Components/ReusableComponents/Elements';
import Accordion from '@ppcp-settings/Components/ReusableComponents/AccordionSection';
import OnboardingHeader from '../Components/OnboardingHeader';
import ConnectionButton from '../Components/ConnectionButton';
import AdvancedOptionsForm from '../Components/AdvancedOptionsForm';

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
			<Separator text={ __( 'or', 'woocommerce-paypal-payments' ) } />
			<Accordion
				title={ __(
					'See advanced options',
					'woocommerce-paypal-payments'
				) }
				className="onboarding-advanced-options"
				noCaps={ true }
				id="advanced-options"
			>
				<AdvancedOptionsForm />
			</Accordion>
		</div>
	);
};

export default StepCompleteSetup;
