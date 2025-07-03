import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const LearnMore = ( { url } ) => {
	if ( ! url || '#' === url ) {
		return null;
	}

	return (
		<Button href={ url } variant="tertiary" target="_blank">
			{ __( 'Learn more', 'woocommerce-paypal-payments' ) }
		</Button>
	);
};

export default LearnMore;
