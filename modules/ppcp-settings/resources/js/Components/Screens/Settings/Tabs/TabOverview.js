import Todos from '../Components/Overview/Todos/Todos';
import Features from '../Components/Overview/Features/Features';
import Help from '../Components/Overview/Help/Help';
import { TodosHooks, CommonHooks, FeaturesHooks } from '../../../../data';
import SpinnerOverlay from '../../../ReusableComponents/SpinnerOverlay';

const TabOverview = () => {
	const { isReady: areTodosReady } = TodosHooks.useTodos();
	const { isReady: merchantIsReady } = CommonHooks.useMerchantInfo();
	const { isReady: featuresIsReady } = FeaturesHooks.useFeatures();

	if ( ! areTodosReady || ! merchantIsReady || ! featuresIsReady ) {
		return <SpinnerOverlay asModal={ true } />;
	}

	return (
		<div className="ppcp-r-tab-overview">
			<Todos />
			<Features />
			<Help />
		</div>
	);
};

export default TabOverview;
