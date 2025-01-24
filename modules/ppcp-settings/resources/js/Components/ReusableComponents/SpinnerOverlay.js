import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

const SpinnerOverlay = ( { message = null } ) => {
	if ( null === message ) {
		message = __( 'Loading…', 'woocommerce-paypal-payments' );
	}

	return (
		<div className="ppcp-r-spinner-overlay">
			{ message && (
				<span className="ppcp-r-spinner-overlay__message">
					{ message }
				</span>
			) }
			<Spinner />
		</div>
	);
};

export default SpinnerOverlay;
