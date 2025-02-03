import { useCallback } from '@wordpress/element';

import {
	CommonHooks,
	PayLaterMessagingHooks,
	PaymentHooks,
	SettingsHooks,
	StylingHooks,
} from '../data';

export const useSaveSettings = () => {
	const { withActivity } = CommonHooks.useBusyState();

	const { persist: persistPayment } = PaymentHooks.useStore();
	const { persist: persistSettings } = SettingsHooks.useStore();
	const { persist: persistStyling } = StylingHooks.useStore();
	const { persist: persistPayLaterMessaging } =
		PayLaterMessagingHooks.useStore();

	const persistAll = useCallback( () => {
		// Executes onSave on TabPayLaterMessaging component.
		document.getElementById( 'configurator-publishButton' )?.click();

		withActivity(
			'persist-methods',
			'Save payment methods',
			persistPayment
		);
		withActivity(
			'persist-settings',
			'Save the settings',
			persistSettings
		);
		withActivity(
			'persist-styling',
			'Save styling details',
			persistStyling
		);
		withActivity(
			'persist-pay-later-messaging',
			'Save pay later messaging details',
			persistPayLaterMessaging
		);
	}, [
		persistPayment,
		persistSettings,
		persistStyling,
		persistPayLaterMessaging,
		withActivity,
	] );

	return { persistAll };
};
