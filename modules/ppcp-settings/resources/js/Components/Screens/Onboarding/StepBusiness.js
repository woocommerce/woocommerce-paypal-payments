import OnboardingHeader from '../../ReusableComponents/OnboardingHeader.js';
import SelectBoxWrapper from '../../ReusableComponents/SelectBoxWrapper.js';
import SelectBox from '../../ReusableComponents/SelectBox.js';
import { __ } from '@wordpress/i18n';
import PaymentMethodIcons from '../../ReusableComponents/PaymentMethodIcons';
import { useState } from '@wordpress/element';
import Navigation from '../../ReusableComponents/Navigation';

const StepBusiness = ( { setStep, currentStep, stepperOrder } ) => {
	const [ businessCategory, setBusinessCategory ] = useState( null );
	const BUSINESS_RADIO_GROUP_NAME = 'business';
	const CASUAL_SELLER_CHECKBOX_VALUE = 'casual_seller';
	const BUSINESS_CHECKBOX_VALUE = 'business';

	return (
		<div className="ppcp-r-page-business">
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
						name={ BUSINESS_RADIO_GROUP_NAME }
						value={ CASUAL_SELLER_CHECKBOX_VALUE }
						changeCallback={ setBusinessCategory }
						currentValue={ businessCategory }
						checked={
							businessCategory ===
							{ CASUAL_SELLER_CHECKBOX_VALUE }
						}
						type="radio"
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
						name={ BUSINESS_RADIO_GROUP_NAME }
						value={ BUSINESS_CHECKBOX_VALUE }
						currentValue={ businessCategory }
						changeCallback={ setBusinessCategory }
						checked={
							businessCategory === { BUSINESS_CHECKBOX_VALUE }
						}
						type="radio"
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
				<Navigation
					setStep={ setStep }
					currentStep={ currentStep }
					stepperOrder={ stepperOrder }
					canProceeedCallback={ () => businessCategory !== null }
				/>
			</div>
		</div>
	);
};

export default StepBusiness;
