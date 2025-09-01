import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import classnames from 'classnames';

/**
 * Renders a loading spinner.
 *
 * @param {Object}  props                 Component properties.
 * @param {boolean} [props.asModal=false] Whether to display the spinner as a modal overlay.
 * @param {string}  [props.ariaLabel]     Accessible label for screen readers.
 * @return {JSX.Element} The spinner overlay component.
 */
const SpinnerOverlay = ( {
	asModal = false,
	ariaLabel = __( 'Loading…', 'woocommerce-paypal-payments' ),
} ) => {
	const className = classnames( 'ppcp-r-spinner-overlay', {
		'ppcp--is-modal': asModal,
	} );

	return (
		<div className={ className } role="status" aria-label={ ariaLabel }>
			<Spinner />
		</div>
	);
};

export default SpinnerOverlay;
