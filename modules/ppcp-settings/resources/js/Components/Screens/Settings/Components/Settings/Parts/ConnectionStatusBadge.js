import { __ } from '@wordpress/i18n';

import TitleBadge, {
	TITLE_BADGE_NEGATIVE,
	TITLE_BADGE_POSITIVE,
} from '../../../../../ReusableComponents/TitleBadge';

const ConnectionStatusBadge = ( { isActive, isSandbox, isBusinessSeller } ) => {
	if ( isActive ) {
		let label;

		if ( isBusinessSeller ) {
			label = isSandbox
				? __( 'Business | Sandbox', 'woocommerce-paypal-payments' )
				: __( 'Business | Live', 'woocommerce-paypal-payments' );
		} else {
			label = isSandbox
				? __( 'Sandbox', 'woocommerce-paypal-payments' )
				: __( 'Active', 'woocommerce-paypal-payments' );
		}

		return <TitleBadge type={ TITLE_BADGE_POSITIVE } text={ label } />;
	}

	return (
		<TitleBadge
			type={ TITLE_BADGE_NEGATIVE }
			text={ __( 'Not Connected', 'woocommerce-paypal-payments' ) }
		/>
	);
};

export default ConnectionStatusBadge;
