import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { scrollAndHighlight } from '../../../../../utils/scrollAndHighlight';

/**
 * Component to display a payment method dependency message
 *
 * @param {Object} props            - Component props
 * @param {string} props.parentId   - ID of the parent payment method
 * @param {string} props.parentName - Display name of the parent payment method
 * @return {JSX.Element} The formatted message with link
 */
const PaymentDependencyMessage = ( { parentId, parentName } ) => {
	const displayName = parentName || parentId;

	return createInterpolateElement(
		/* translators: %s: payment method name */
		__(
			'This payment method requires <methodLink /> to be enabled.',
			'woocommerce-paypal-payments'
		),
		{
			methodLink: (
				<strong>
					<a
						href="#"
						onClick={ ( e ) => {
							e.preventDefault();
							scrollAndHighlight( parentId );
						} }
					>
						{ displayName }
					</a>
				</strong>
			),
		}
	);
};

export default PaymentDependencyMessage;
