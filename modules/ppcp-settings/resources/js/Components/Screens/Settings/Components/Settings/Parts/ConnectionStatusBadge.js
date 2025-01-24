import { __ } from '@wordpress/i18n';

import TitleBadge, {
	TITLE_BADGE_NEGATIVE,
	TITLE_BADGE_POSITIVE,
} from '../../../../../ReusableComponents/TitleBadge';

const ConnectionStatusBadge = ( { isActive, isSandbox } ) => {
	if ( isActive ) {
		const label = isSandbox
			? __( 'Sandbox Mode', 'woocommerce-paypal-payments' )
			: __( 'Active', 'woocommerce-paypal-payments' );

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
