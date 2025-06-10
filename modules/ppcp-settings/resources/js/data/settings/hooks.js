/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */
import { useDispatch, useSelect } from '@wordpress/data';

import { STORE_NAME } from './constants';
import { createHooksForStore } from '../utils';
import { useMemo } from '@wordpress/element';

/**
 * Single source of truth for access Redux details.
 *
 * This hook returns a stable API to access actions, selectors and special hooks to generate
 * getter- and setters for transient or persistent properties.
 *
 * @return {{select, dispatch, useTransient, usePersistent}} Store data API.
 */
const useStoreData = () => {
	const select = useSelect( ( selectors ) => selectors( STORE_NAME ), [] );
	const dispatch = useDispatch( STORE_NAME );
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );

	return useMemo(
		() => ( {
			select,
			dispatch,
			useTransient,
			usePersistent,
		} ),
		[ select, dispatch, useTransient, usePersistent ]
	);
};

const useHooks = () => {
	const { usePersistent } = useStoreData();

	// Persistent accessors.
	const [ invoicePrefix, setInvoicePrefix ] =
		usePersistent( 'invoicePrefix' );
	const [ brandName, setBrandName ] = usePersistent( 'brandName' );
	const [ softDescriptor, setSoftDescriptor ] =
		usePersistent( 'softDescriptor' );

	const [ subtotalAdjustment, setSubtotalAdjustment ] =
		usePersistent( 'subtotalAdjustment' );
	const [ landingPage, setLandingPage ] = usePersistent( 'landingPage' );
	const [ buttonLanguage, setButtonLanguage ] =
		usePersistent( 'buttonLanguage' );

	const [ authorizeOnly, setAuthorizeOnly ] =
		usePersistent( 'authorizeOnly' );
	const [ captureVirtualOnlyOrders, setCaptureVirtualOnlyOrders ] =
		usePersistent( 'captureVirtualOrders' );
	const [ savePaypalAndVenmo, setSavePaypalAndVenmo ] =
		usePersistent( 'savePaypalAndVenmo' );
	const [ saveCardDetails, setSaveCardDetails ] =
		usePersistent( 'saveCardDetails' );
	const [ payNowExperience, setPayNowExperience ] =
		usePersistent( 'enablePayNow' );
	const [ logging, setLogging ] = usePersistent( 'enableLogging' );
	const [ stayUpdated, setStayUpdated ] = usePersistent( 'stayUpdated' );

	const [ disabledCards, setDisabledCards ] =
		usePersistent( 'disabledCards' );

	return {
		invoicePrefix,
		setInvoicePrefix,
		authorizeOnly,
		setAuthorizeOnly,
		captureVirtualOnlyOrders,
		setCaptureVirtualOnlyOrders,
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
		payNowExperience,
		setPayNowExperience,
		logging,
		setLogging,
		stayUpdated,
		setStayUpdated,
		subtotalAdjustment,
		setSubtotalAdjustment,
		brandName,
		setBrandName,
		softDescriptor,
		setSoftDescriptor,
		landingPage,
		setLandingPage,
		buttonLanguage,
		setButtonLanguage,
		disabledCards,
		setDisabledCards,
	};
};

export const useStore = () => {
	const { select, dispatch, useTransient } = useStoreData();
	const { persist, refresh } = dispatch;
	const [ isReady ] = useTransient( 'isReady' );

	// Load persistent data from REST if not done yet.
	if ( ! isReady ) {
		select.persistentData();
	}

	return { persist, refresh, isReady };
};

export const useSettings = () => {
	const {
		invoicePrefix,
		setInvoicePrefix,
		authorizeOnly,
		setAuthorizeOnly,
		captureVirtualOnlyOrders,
		setCaptureVirtualOnlyOrders,
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
		payNowExperience,
		setPayNowExperience,
		logging,
		setLogging,
		stayUpdated,
		setStayUpdated,
		subtotalAdjustment,
		setSubtotalAdjustment,
		brandName,
		setBrandName,
		softDescriptor,
		setSoftDescriptor,
		landingPage,
		setLandingPage,
		buttonLanguage,
		setButtonLanguage,
		disabledCards,
		setDisabledCards,
	} = useHooks();

	return {
		invoicePrefix,
		setInvoicePrefix,
		authorizeOnly,
		setAuthorizeOnly,
		captureVirtualOnlyOrders,
		setCaptureVirtualOnlyOrders,
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
		payNowExperience,
		setPayNowExperience,
		logging,
		setLogging,
		stayUpdated,
		setStayUpdated,
		subtotalAdjustment,
		setSubtotalAdjustment,
		brandName,
		setBrandName,
		softDescriptor,
		setSoftDescriptor,
		landingPage,
		setLandingPage,
		buttonLanguage,
		setButtonLanguage,
		disabledCards,
		setDisabledCards,
	};
};
