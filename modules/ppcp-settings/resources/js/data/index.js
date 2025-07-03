import { addDebugTools } from './debug';
import * as Onboarding from './onboarding';
import * as Common from './common';
import * as Payment from './payment';
import * as Settings from './settings';
import * as Styling from './styling';
import * as Todos from './todos';
import * as PayLaterMessaging from './pay-later-messaging';
import * as Features from './features';
import * as Tracking from './tracking';

// Initialize tracking funnels before any store initialization.
import '../services/tracking/init';

const stores = [
	Onboarding,
	Common,
	Payment,
	Settings,
	Styling,
	Todos,
	PayLaterMessaging,
	Features,
	Tracking,
];

stores.forEach( ( store ) => {
	try {
		if ( false === store.initStore() ) {
			console.error(
				`Store initialization failed for ${ store.STORE_NAME }`
			);
		}
	} catch ( e ) {
		console.error(
			'Error during store initialization:',
			store.STORE_NAME,
			e
		);
	}
} );

export const OnboardingHooks = Onboarding.hooks;
export const CommonHooks = Common.hooks;
export const PaymentHooks = Payment.hooks;
export const SettingsHooks = Settings.hooks;
export const StylingHooks = Styling.hooks;
export const TodosHooks = Todos.hooks;
export const PayLaterMessagingHooks = PayLaterMessaging.hooks;
export const FeaturesHooks = Features.hooks;

export const OnboardingStoreName = Onboarding.STORE_NAME;
export const CommonStoreName = Common.STORE_NAME;
export const PaymentStoreName = Payment.STORE_NAME;
export const SettingsStoreName = Settings.STORE_NAME;
export const StylingStoreName = Styling.STORE_NAME;
export const TodosStoreName = Todos.STORE_NAME;
export const PayLaterMessagingStoreName = PayLaterMessaging.STORE_NAME;
export const FeaturesStoreName = Features.STORE_NAME;
export const TrackingStoreName = Tracking.STORE_NAME;

export * from './configuration';

addDebugTools( window.ppcpSettings, stores );
