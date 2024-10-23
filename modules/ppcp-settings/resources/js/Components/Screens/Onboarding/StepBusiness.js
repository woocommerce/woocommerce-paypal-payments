import OnboardingHeader from '../../ReusableComponents/OnboardingHeader.js';
import SelectBoxWrapper from '../../ReusableComponents/SelectBoxWrapper.js';
import SelectBox from '../../ReusableComponents/SelectBox.js';
import { __ } from '@wordpress/i18n';
import PaymentMethodIcons from '../../ReusableComponents/PaymentMethodIcons';

const StepBusiness = () => {
	return (
		<div className="ppcp-r-page-welcome">
			<OnboardingHeader
				title={ __(
					'Tell Us About Your Business',
					'woocommerce-paypal-payments'
				) }
			/>
			<div className="ppcp-r-inner-container">
				<SelectBoxWrapper>
					<SelectBox
						title={ __(
							'Casual Seller',
							'woocommerce-paypal-payments'
						) }
						description={ __(
							'I sell occasionally and mainly use PayPal for personal transactions.',
							'woocommerce-paypal-payments'
						) }
						icon="icon-business-casual-seller.svg"
					>
						<PaymentMethodIcons
							icons={ [
								'paypal',
								'venmo',
								'visa',
								'mastercard',
								'amex',
								'discover',
							] }
						/>
					</SelectBox>
					<SelectBox
						title={ __(
							'Business',
							'woocommerce-paypal-payments'
						) }
						description={ __(
							'I run a registered business and sell full-time.',
							'woocommerce-paypal-payments'
						) }
						icon="icon-business-business.svg"
					>
						<PaymentMethodIcons
							icons={ [
								'paypal',
								'venmo',
								'visa',
								'mastercard',
								'amex',
								'discover',
								'apple-pay',
								'google-pay',
								'ideal',
							] }
						/>
					</SelectBox>
				</SelectBoxWrapper>
			</div>
		</div>
	);
};

export default StepBusiness;
