import { __ } from '@wordpress/i18n';
import Todos from '../Components/Overview/Todos/Todos';
import Features from '../Components/Overview/Features/Features';
import { TodosHooks, CommonHooks, FeaturesHooks } from '../../../../data';
import SpinnerOverlay from '../../../ReusableComponents/SpinnerOverlay';
import usePaymentGatewaySync from '../../../../hooks/usePaymentGatewaySync';

const TabOverview = () => {
	const { isReady: areTodosReady } = TodosHooks.useTodos();
	const { isReady: merchantIsReady } = CommonHooks.useMerchantInfo();
	const { isReady: featuresIsReady, features } = FeaturesHooks.useFeatures();

	// Enable payment gateways after onboarding based on relevant flags.
	usePaymentGatewaySync();

	// For countries without features (Brazil, Japan, Russia), consider features ready if array is empty
	const effectiveFeaturesReady =
		featuresIsReady || ( features && features.length === 0 );

	if ( ! areTodosReady || ! merchantIsReady || ! effectiveFeaturesReady ) {
		return (
			<SpinnerOverlay
				asModal={ true }
				ariaLabel={ __(
					'Loading PayPal settings',
					'woocommerce-paypal-payments'
				) }
			/>
		);
	}

	return (
		<div
			className="ppcp-r-tab-overview"
			role="region"
			aria-label={ __(
				'PayPal Overview',
				'woocommerce-paypal-payments'
			) }
		>
			<Todos />
			<Features />
		</div>
	);
};

export default TabOverview;
