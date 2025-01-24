import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

import { OptionSelector } from '../../../ReusableComponents/Fields';
import { OnboardingHooks, BUSINESS_TYPES } from '../../../../data';
import OnboardingHeader from '../Components/OnboardingHeader';

const getBusinessType = ( isCasualSeller ) => {
	if ( isCasualSeller === null ) {
		return '';
	}

	return isCasualSeller
		? BUSINESS_TYPES.CASUAL_SELLER
		: BUSINESS_TYPES.BUSINESS;
};

const StepBusiness = ( {} ) => {
	const { isCasualSeller, setIsCasualSeller } = OnboardingHooks.useBusiness();
	const [ businessChoice, setBusinessChoice ] = useState(
		getBusinessType( isCasualSeller )
	);

	useEffect( () => {
		setIsCasualSeller( BUSINESS_TYPES.CASUAL_SELLER === businessChoice );
	}, [ businessChoice, setIsCasualSeller ] );

	return (
		<div className="ppcp-r-page-business">
			<OnboardingHeader
				title={ __(
					'Choose your account type',
					'woocommerce-paypal-payments'
				) }
			/>
			<div className="ppcp-r-inner-container">
				<OptionSelector
					multiSelect={ false }
					options={ businessChoices }
					onChange={ setBusinessChoice }
					value={ businessChoice }
				/>
			</div>
		</div>
	);
};

const businessChoices = [
	{
		value: BUSINESS_TYPES.BUSINESS,
		title: __( 'Business', 'woocommerce-paypal-payments' ),
		description: __(
			'Recommended for individuals and organizations that primarily use PayPal to sell goods or services or receive donations, even if your business is not incorporated.',
			'woocommerce-paypal-payments'
		),
	},
	{
		value: BUSINESS_TYPES.CASUAL_SELLER,
		title: __( 'Personal Account', 'woocommerce-paypal-payments' ),
		description: __(
			'Ideal for those who primarily make purchases or send personal transactions to family and friends.',
			'woocommerce-paypal-payments'
		),
	},
];

export default StepBusiness;
