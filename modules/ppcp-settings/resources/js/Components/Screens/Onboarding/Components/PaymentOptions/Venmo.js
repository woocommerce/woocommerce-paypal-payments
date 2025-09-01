import { __ } from '@wordpress/i18n';

import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const Venmo = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __( 'Venmo', 'woocommerce-paypal-payments' ) }
			imageBadge={ [ 'icon-button-venmo.svg' ] }
			description={ __(
				'Automatically offer Venmo checkout to millions of active users.',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default Venmo;
