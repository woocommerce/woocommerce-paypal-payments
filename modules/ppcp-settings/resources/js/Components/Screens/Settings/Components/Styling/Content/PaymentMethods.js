import { __ } from '@wordpress/i18n';

import { StylingHooks } from '../../../../../../data';
import { CheckboxStylingSection } from '../Layout';

const SectionPaymentMethods = ( { location } ) => {
	const { paymentMethods, setPaymentMethods, choices } =
		StylingHooks.usePaymentMethodProps( location );

	return (
		<CheckboxStylingSection
			name="payment-methods"
			title={ __( 'Payment Methods', 'woocommerce-paypal-payments' ) }
			options={ choices }
			value={ paymentMethods }
			onChange={ setPaymentMethods }
		/>
	);
};

export default SectionPaymentMethods;
