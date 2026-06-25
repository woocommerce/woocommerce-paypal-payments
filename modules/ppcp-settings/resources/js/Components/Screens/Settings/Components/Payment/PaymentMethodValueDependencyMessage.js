import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useScrollTo } from '@ppcp-settings/hooks/useScrollHighlight';

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
	const scrollTo = useScrollTo();
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
				<button
					type="button"
					className="ppcp--link-button"
					onClick={ () => scrollTo( dependentMethodId ) }
				>
					{ displayName }
				</button>
			</strong>
		),
	} );
};

export default PaymentMethodValueDependencyMessage;
