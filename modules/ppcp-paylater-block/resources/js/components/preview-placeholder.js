import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

export function PreviewPlaceholder( { timedOut } ) {
	if ( ! timedOut ) {
		return <Spinner />;
	}
	return (
		<p className="ppcp-paylater-preview-placeholder">
			{ __(
				'Pay Later messaging preview unavailable in editor. Messaging will display on the frontend when eligibility conditions are met.',
				'woocommerce-paypal-payments'
			) }
		</p>
	);
}
