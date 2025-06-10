import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { scrollAndHighlight } from '../../../../../utils/scrollAndHighlight';

/**
 * Component to display a payment method value dependency message
 *
 * @param {Object}  props                     - Component props
 * @param {string}  props.dependentMethodId   - ID of the dependent payment method
 * @param {string}  props.dependentMethodName - Display name of the dependent payment method
 * @param {boolean} props.requiredValue       - Required value (enabled/disabled state) for the dependent method
 * @return {JSX.Element} The formatted message with link
 */
const PaymentMethodValueDependencyMessage = ( {
	dependentMethodId,
	dependentMethodName,
	requiredValue,
} ) => {
	const displayName = dependentMethodName || dependentMethodId;

	// Determine appropriate message template based on the required value
	const template = requiredValue
		? __(
				'Enable <methodLink /> to use this method.',
				'woocommerce-paypal-payments'
		  )
		: __(
				'Disable <methodLink /> to use this method.',
				'woocommerce-paypal-payments'
		  );

	return createInterpolateElement( template, {
		methodLink: (
			<strong>
				<a
					href="#"
					onClick={ ( e ) => {
						e.preventDefault();
						scrollAndHighlight( dependentMethodId );
					} }
				>
					{ displayName }
				</a>
			</strong>
		),
	} );
};

export default PaymentMethodValueDependencyMessage;
