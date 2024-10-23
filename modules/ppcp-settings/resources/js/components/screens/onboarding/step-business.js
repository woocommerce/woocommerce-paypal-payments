import OnboardingHeader from '../../reusable-components/onboarding-header.js';
import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';
import PaymentMethodIcons from '../../reusable-components/payment-method-icons';
import SettingsToggleBlock from '../../reusable-components/settings-toggle-block';
import Separator from '../../reusable-components/separator';

const StepBusiness = () => {
	return (
		<div className="ppcp-r-page-welcome">
			<OnboardingHeader
				title={ __(
					'Tell Us About Your Business',
					'woocommerce-paypal-payments'
				) }
			/>
			<div className="ppcp-r-inner-container"></div>
		</div>
	);
};

export default StepBusiness;
