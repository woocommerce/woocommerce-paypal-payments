import { useEffect, useMemo } from '@wordpress/element';
import classNames from 'classnames';

import { OnboardingHooks, CommonHooks } from '../data';
import SpinnerOverlay from './ReusableComponents/SpinnerOverlay';
import SendOnlyMessage from './Screens/SendOnlyMessage';
import OnboardingScreen from './Screens/Onboarding';
import SettingsScreen from './Screens/Settings';

const SettingsApp = () => {
	const { isReady: onboardingIsReady, completed: onboardingCompleted } =
		OnboardingHooks.useSteps();
	const {
		isReady: merchantIsReady,
		merchant: { isSendOnlyCountry },
	} = CommonHooks.useMerchantInfo();

	// Disable the "Changes you made might not be saved" browser warning.
	useEffect( () => {
		const suppressBeforeUnload = ( event ) => {
			event.stopImmediatePropagation();
			return undefined;
		};
		window.addEventListener( 'beforeunload', suppressBeforeUnload );
		return () => {
			window.removeEventListener( 'beforeunload', suppressBeforeUnload );
		};
	}, [] );

	const wrapperClass = classNames( 'ppcp-r-app', {
		loading: ! onboardingIsReady,
	} );

	const Content = useMemo( () => {
		if ( ! onboardingIsReady || ! merchantIsReady ) {
			return <SpinnerOverlay />;
		}

		if ( isSendOnlyCountry ) {
			return <SendOnlyMessage />;
		}

		if ( ! onboardingCompleted ) {
			return <OnboardingScreen />;
		}

		return <SettingsScreen />;
	}, [
		isSendOnlyCountry,
		merchantIsReady,
		onboardingCompleted,
		onboardingIsReady,
	] );

	return <div className={ wrapperClass }>{ Content }</div>;
};

export default SettingsApp;
