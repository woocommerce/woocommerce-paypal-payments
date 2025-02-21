import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import classnames from 'classnames';

const SpinnerOverlay = ( { asModal = false, message = null } ) => {
	const className = classnames( 'ppcp-r-spinner-overlay', {
		'ppcp--is-modal': asModal,
	} );

	if ( null === message ) {
		message = __( 'Loading…', 'woocommerce-paypal-payments' );
	}

	return (
		<div className={ className }>
			{ message && (
				<span className="ppcp--spinner-message">{ message }</span>
			) }
			<Spinner />
		</div>
	);
};

export default SpinnerOverlay;
