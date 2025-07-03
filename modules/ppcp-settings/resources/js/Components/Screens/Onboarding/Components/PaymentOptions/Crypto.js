import { __ } from '@wordpress/i18n';

import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const Crypto = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __( 'Crypto', 'woocommerce-paypal-payments' ) }
			imageBadge={ [ 'icon-payment-method-crypto.svg' ] }
			description={ __(
				'Let customers checkout with Crypto while you get paid in cash.',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default Crypto;
