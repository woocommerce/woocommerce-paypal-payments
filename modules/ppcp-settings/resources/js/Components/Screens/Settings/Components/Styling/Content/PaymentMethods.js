import { __ } from '@wordpress/i18n';

import { PaymentHooks, StylingHooks } from '../../../../../../data';
import { CheckboxStylingSection } from '../Layout';
import { useMemo } from '@wordpress/element';

const SectionPaymentMethods = ( { location } ) => {
	const { paymentMethods, setPaymentMethods, choices } =
		StylingHooks.usePaymentMethodProps( location );
	const { all: allMethods } = PaymentHooks.usePaymentMethods();

	const filteredChoices = useMemo( () => {
		return choices.filter( ( choice ) => {
			const methodConfig = allMethods.find(
				( i ) => i.id === choice.value
			);
			return methodConfig?.enabled;
		} );
	}, [ choices, allMethods ] );

	return (
		<CheckboxStylingSection
			name="payment-methods"
			title={ __( 'Payment Methods', 'woocommerce-paypal-payments' ) }
			options={ filteredChoices }
			value={ paymentMethods }
			onChange={ setPaymentMethods }
		/>
	);
};

export default SectionPaymentMethods;
