import { __ } from '@wordpress/i18n';

import PricingDescription from './PricingDescription';
import PaymentFlow from './PaymentFlow';

const WelcomeDocs = ( { useAcdc, isFastlane, isPayLater, storeCountry } ) => {
	return (
		<div className="ppcp-r-welcome-docs">
			<h2 className="ppcp-r-welcome-docs__title">
				{ __(
					'Want to know more about PayPal Payments?',
					'woocommerce-paypal-payments'
				) }
			</h2>
			<PaymentFlow
				useAcdc={ useAcdc }
				isFastlane={ isFastlane }
				isPayLater={ isPayLater }
				storeCountry={ storeCountry }
			/>
			<PricingDescription />
		</div>
	);
};

export default WelcomeDocs;
