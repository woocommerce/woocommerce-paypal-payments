import { useCallback, useMemo } from '@wordpress/element';

import {
	CommonHooks,
	PayLaterMessagingHooks,
	PaymentHooks,
	SettingsHooks,
	StylingHooks,
	TodosHooks,
} from '@ppcp-settings/data';
import { useExtensionStores } from '../extensions';

export const useStoreManager = () => {
	const { withActivity } = CommonHooks.useBusyState();

	const paymentStore = PaymentHooks.useStore();
	const settingsStore = SettingsHooks.useStore();
	const stylingStore = StylingHooks.useStore();
	const todosStore = TodosHooks.useStore();
	const payLaterStore = PayLaterMessagingHooks.useStore();

	// Get all registered extension stores
	const extensionStores = useExtensionStores();

	const coreStoreActions = useMemo(
		() => [
			{
				key: 'methods',
				message: 'Process payment methods',
				store: paymentStore,
			},
			{
				key: 'settings',
				message: 'Process the settings',
				store: settingsStore,
			},
			{
				key: 'styling',
				message: 'Process styling details',
				store: stylingStore,
			},
			{
				key: 'todos',
				message: 'Process todos state',
				store: todosStore,
			},
			{
				key: 'pay-later-messaging',
				message: 'Process pay later messaging details',
				store: payLaterStore,
			},
		],
		[ payLaterStore, paymentStore, settingsStore, stylingStore, todosStore ]
	);

	// Combine core and extension stores
	const allStoreActions = useMemo(
		() => [ ...coreStoreActions, ...extensionStores ],
		[ coreStoreActions, extensionStores ]
	);

	const persistAll = useCallback( () => {
		/**
		 * Executes onSave on TabPayLaterMessaging component.
		 *
		 * Todo: find a better way for this, because it's highly unreliable
		 *       (it only works when the user is still on the "Pay Later Messaging" tab)
		 */
		document.getElementById( 'configurator-publishButton' )?.click();

		allStoreActions.forEach( ( { key, message, store } ) => {
			withActivity( `persist-${ key }`, message, store.persist );
		} );
	}, [ allStoreActions, withActivity ] );

	const refreshAll = useCallback( () => {
		allStoreActions.forEach( ( { key, message, store } ) => {
			withActivity( `refresh-${ key }`, message, store.refresh );
		} );
	}, [ allStoreActions, withActivity ] );

	return {
		persistAll,
		refreshAll,
	};
};
