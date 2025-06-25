import { useEffect, useMemo, useState } from '@wordpress/element';
import classNames from 'classnames';

import { OnboardingHooks, CommonHooks } from '../data';
import SpinnerOverlay from './ReusableComponents/SpinnerOverlay';
import SendOnlyMessage from './Screens/SendOnlyMessage';
import OnboardingScreen from './Screens/Onboarding';
import SettingsScreen from './Screens/Settings';
import { getQuery, cleanUrlQueryParams } from '../utils/navigation';

import { initializeTracking } from '../services/tracking';

const SettingsApp = () => {
	const { isReady: onboardingIsReady, completed: onboardingCompleted } =
		OnboardingHooks.useSteps();
	const { isReady: merchantIsReady } = CommonHooks.useStore();
	const {
		merchant: { isSendOnlyCountry },
	} = CommonHooks.useMerchantInfo();

	useEffect( () => {
		initializeTracking();
	}, [] );

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

	const [ activePanel, setActivePanel ] = useState( getQuery().panel );

	const removeUnsupportedArgs = () => {
		const urlWasCleaned = cleanUrlQueryParams( [
			'page',
			'tab',
			'section',
		] );

		if ( urlWasCleaned ) {
			setActivePanel( '' );
		}
	};

	const Content = useMemo( () => {
		if ( ! onboardingIsReady || ! merchantIsReady ) {
			return <SpinnerOverlay asModal={ true } />;
		}

		if ( isSendOnlyCountry ) {
			removeUnsupportedArgs();
			return <SendOnlyMessage />;
		}

		if ( ! onboardingCompleted ) {
			removeUnsupportedArgs();
			return <OnboardingScreen />;
		}

		return (
			<SettingsScreen
				activePanel={ activePanel || 'overview' }
				setActivePanel={ setActivePanel }
			/>
		);
	}, [
		isSendOnlyCountry,
		merchantIsReady,
		onboardingCompleted,
		onboardingIsReady,
		activePanel,
	] );

	return <div className={ wrapperClass }>{ Content }</div>;
};

export default SettingsApp;
